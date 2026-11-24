<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\ProductImage;
use App\Services\CatalogueIndex;
use App\Support\ResponsivePicture;
use App\Support\ServedUpload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function __construct(private ResponsivePicture $pictures, private ServedUpload $uploads) {}

    public function index(): View
    {
        return view('admin.categories.index', [
            'categories' => $this->orderedCategories()->loadCount('products'),
        ]);
    }

    public function create(): View
    {
        return view('admin.categories.form', [
            'category' => new Category([
                'position' => $this->nextPosition(),
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $category = $this->save($request, new Category);

        return redirect()->route('admin.categories.edit', $category)->with('status', 'Category saved.');
    }

    public function edit(Category $category): View
    {
        return view('admin.categories.form', [
            'category' => $category,
        ]);
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $this->save($request, $category);

        return redirect()->route('admin.categories.edit', $category)->with('status', 'Category updated.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($category->products()->exists()) {
            return redirect()
                ->route('admin.categories.index')
                ->with('error', 'Move or delete the stones in this category before deleting it.');
        }

        $cover = $category->image;
        $category->delete();
        $this->forgetCover(is_string($cover) ? $cover : null);
        CatalogueIndex::write();

        return redirect()->route('admin.categories.index')->with('status', 'Category deleted.');
    }

    public function reorder(Request $request): RedirectResponse
    {
        $categories = $this->orderedCategories();

        if ($request->exists('ids')) {
            $ordered = $this->orderFromIds($request, $categories);
        } else {
            $ordered = $this->orderFromMove($request, $categories);
            if ($ordered === null) {
                return back();
            }
        }

        DB::transaction(function () use ($ordered): void {
            foreach ($ordered as $position => $category) {
                if ((int) $category->position === $position) {
                    continue;
                }

                $category->position = $position;
                $category->save();
            }
        });

        CatalogueIndex::write();

        return back()->with('status', 'Order saved.');
    }

    private function save(Request $request, Category $category): Category
    {
        $request->merge([
            'slug' => $request->filled('slug')
                ? $request->string('slug')->toString()
                : Str::slug($request->string('name')->toString()),
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:200', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('categories', 'slug')->ignore($category->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'introduction' => ['nullable', 'string', 'max:5000'],
            'position' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'cover' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_cover' => ['nullable', 'boolean'],
        ]);

        $previousCover = $category->image;
        $position = $data['position'] ?? null;

        $saved = DB::transaction(function () use ($request, $category, $data, $position): Category {
            $category->fill([
                'name' => $data['name'],
                'slug' => $data['slug'],
                'description' => $this->nullableText($data['description'] ?? null),
                'introduction' => $this->nullableText($data['introduction'] ?? null),
                'position' => $position !== null ? (int) $position : ($category->exists ? (int) $category->position : $this->nextPosition()),
            ]);
            $category->save();

            $cover = $request->file('cover');
            if ($cover instanceof UploadedFile) {
                $category->image = $this->storeCover($category, $cover);
                $category->save();
            } elseif ($request->boolean('remove_cover')) {
                $category->image = null;
                $category->save();
            }

            return $category;
        });

        if (is_string($previousCover) && $previousCover !== $saved->image) {
            $this->forgetCover($previousCover);
        }

        CatalogueIndex::write();

        return $saved;
    }

    private function storeCover(Category $category, UploadedFile $photo): string
    {
        $extension = strtolower((string) ($photo->guessExtension() ?: $photo->getClientOriginalExtension()));
        if ($extension === 'jpeg') {
            $extension = 'jpg';
        }
        if (! in_array($extension, ['jpg', 'png', 'webp'], true)) {
            throw ValidationException::withMessages([
                'cover' => 'The cover must be a JPG, PNG, or WebP file.',
            ]);
        }

        $base = Str::slug(pathinfo($photo->getClientOriginalName(), PATHINFO_FILENAME));
        $filename = ($base !== '' ? $base : 'cover').'-'.Str::lower(Str::random(8)).'.'.$extension;
        $relativePath = $this->uploads->store($photo, 'products/categories/'.$category->slug, $filename);
        $this->pictures->ensure($relativePath);

        return $relativePath;
    }

    private function forgetCover(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        if (Category::query()->where('image', $path)->exists() || ProductImage::query()->where('path', $path)->exists()) {
            return;
        }

        $this->uploads->delete($path);
    }

    /**
     * @param  Collection<int, Category>  $categories
     * @return Collection<int, Category>
     */
    private function orderFromIds(Request $request, Collection $categories): Collection
    {
        $data = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'distinct', 'exists:categories,id'],
        ]);

        $submitted = array_map(intval(...), $data['ids']);
        $existing = $categories->pluck('id')->map(fn ($id) => (int) $id)->all();
        sort($existing);
        $check = $submitted;
        sort($check);

        if ($check !== $existing) {
            throw ValidationException::withMessages([
                'ids' => 'Send every category, each once.',
            ]);
        }

        $byId = $categories->keyBy('id');

        return collect($submitted)->map(fn (int $id) => $byId->get($id))->values();
    }

    /**
     * @param  Collection<int, Category>  $categories
     * @return Collection<int, Category>|null
     */
    private function orderFromMove(Request $request, Collection $categories): ?Collection
    {
        $data = $request->validate([
            'id' => ['required', 'integer', 'exists:categories,id'],
            'direction' => ['required', Rule::in(['up', 'down'])],
        ]);

        $index = $categories->search(fn (Category $category): bool => $category->id === (int) $data['id']);
        if ($index === false) {
            return null;
        }

        $swapWith = $data['direction'] === 'up' ? $index - 1 : $index + 1;
        if ($swapWith < 0 || $swapWith >= $categories->count()) {
            return null;
        }

        $reordered = $categories->values();
        $moving = $reordered->get($index);
        $reordered->put($index, $reordered->get($swapWith));
        $reordered->put($swapWith, $moving);

        return $reordered->values();
    }

    /**
     * @return Collection<int, Category>
     */
    private function orderedCategories(): Collection
    {
        return Category::query()->orderBy('position')->orderBy('name')->orderBy('id')->get();
    }

    private function nextPosition(): int
    {
        $highest = Category::query()->max('position');

        return $highest === null ? 0 : ((int) $highest) + 1;
    }

    private function nullableText(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
