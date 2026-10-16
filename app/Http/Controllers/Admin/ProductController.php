<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductSpec;
use App\Services\CatalogueIndex;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim($request->string('q')->toString());
        $categoryId = $request->integer('category');
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';

        return view('admin.products.index', [
            'products' => Product::query()
                ->with('category')
                ->when($search !== '', fn ($query) => $query->where('name', 'like', $like))
                ->when($categoryId > 0, fn ($query) => $query->where('category_id', $categoryId))
                ->orderBy('name')
                ->get(),
            'categories' => Category::query()->orderBy('name')->get(),
            'search' => $search,
            'categoryId' => $categoryId,
        ]);
    }

    public function create(): View
    {
        return $this->formView(new Product([
            'is_published' => true,
            'color' => '',
            'summary' => '',
            'finishes' => [],
            'applications' => [],
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        $product = $this->save($request, new Product);

        return redirect()->route('admin.products.edit', $product)->with('status', 'Product saved.');
    }

    public function edit(Product $product): View
    {
        $product->load(['specs', 'images']);

        return $this->formView($product);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $this->save($request, $product);

        return redirect()->route('admin.products.edit', $product)->with('status', 'Product updated.');
    }

    public function duplicate(Product $product): RedirectResponse
    {
        $product->load(['specs', 'images']);

        $copy = DB::transaction(function () use ($product): Product {
            $copy = $product->replicate();
            $copy->name = Str::limit($product->name.' copy', 200, '');
            $copy->slug = $this->uniqueCopySlug($product->slug);
            $copy->is_published = false;
            $copy->save();

            foreach ($product->specs as $spec) {
                $copy->specs()->create([
                    'label' => $spec->label,
                    'value' => $spec->value,
                    'position' => $spec->position,
                ]);
            }

            foreach ($product->images as $image) {
                $copy->images()->create([
                    'path' => $image->path,
                    'alt' => $image->alt,
                    'width' => $image->width,
                    'height' => $image->height,
                    'srcset' => $image->srcset,
                    'position' => $image->position,
                ]);
            }

            CatalogueIndex::write();

            return $copy;
        });

        return redirect()
            ->route('admin.products.edit', $copy)
            ->with('status', 'A hidden copy was created. The original stone was not changed.');
    }

    private function formView(Product $product): View
    {
        return view('admin.products.form', [
            'product' => $product,
            'categories' => Category::query()->orderBy('name')->get(),
            'specTemplates' => config('product_spec_templates', []),
            'imageDetails' => $this->imageDetails($product),
        ]);
    }

    /**
     * @return array<int, array{bytes: int|null, label: string|null}>
     */
    private function imageDetails(Product $product): array
    {
        if (! $product->exists) {
            return [];
        }

        $details = [];
        foreach ($product->images as $image) {
            $fullPath = public_path(str_replace('\\', '/', (string) $image->path));
            $bytes = is_file($fullPath) ? filesize($fullPath) : false;
            $details[$image->id] = [
                'bytes' => $bytes === false ? null : $bytes,
                'label' => $this->formatBytes($bytes === false ? null : $bytes),
            ];
        }

        return $details;
    }

    private function formatBytes(?int $bytes): ?string
    {
        if ($bytes === null) {
            return null;
        }

        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1048576) {
            return round($bytes / 1024).' KB';
        }

        return number_format($bytes / 1048576, 1).' MB';
    }

    private function uniqueCopySlug(string $slug): string
    {
        $base = Str::limit($slug, 180, '');
        $candidate = $base.'-copy';
        $suffix = 2;

        while (Product::query()->where('slug', $candidate)->exists()) {
            $candidate = $base.'-copy-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function save(Request $request, Product $product): Product
    {
        $request->merge([
            'slug' => $request->filled('slug')
                ? $request->string('slug')->toString()
                : Str::slug($request->string('name')->toString()),
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'slug' => ['required', 'string', 'max:200', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('products', 'slug')->ignore($product->id)],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'color' => ['nullable', 'string', 'max:120'],
            'summary' => ['nullable', 'string', 'max:180'],
            'detail' => ['nullable', 'string'],
            'application' => ['nullable', 'string'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:2000'],
            'is_published' => ['nullable', 'boolean'],
            'finishes_text' => ['nullable', 'string', 'max:1000'],
            'applications_text' => ['nullable', 'string', 'max:1000'],
            'specs' => ['array'],
            'specs.*.label' => ['nullable', 'string', 'max:120'],
            'specs.*.value' => ['nullable', 'string', 'max:1000'],
            'existing_images' => ['array'],
            'existing_images.*.alt' => ['nullable', 'string', 'max:255'],
            'existing_images.*.position' => ['nullable', 'integer', 'min:0', 'max:999'],
            'remove_images' => ['array'],
            'remove_images.*' => ['integer'],
            'photos' => ['array'],
            'photos.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'photo_alts' => ['array'],
            'photo_alts.*' => ['nullable', 'string', 'max:255'],
        ]);

        $category = Category::query()->findOrFail($data['category_id']);
        $filesToDelete = [];

        $saved = DB::transaction(function () use ($request, $product, $data, $category, &$filesToDelete): Product {
            $product->fill([
                'category_id' => $category->id,
                'name' => $data['name'],
                'slug' => $data['slug'],
                'color' => $data['color'] ?? '',
                'summary' => $data['summary'] ?? '',
                'detail' => $data['detail'] ?? null,
                'application' => $data['application'] ?? null,
                'meta_title' => $data['meta_title'] ?? null,
                'meta_description' => $data['meta_description'] ?? null,
                'is_published' => $request->boolean('is_published'),
                'finishes' => $this->listFromText($data['finishes_text'] ?? ''),
                'applications' => $this->listFromText($data['applications_text'] ?? ''),
            ]);
            $product->save();

            $this->syncSpecs($product, $data['specs'] ?? []);
            $filesToDelete = $this->syncImages($product, $category, $data, $request);

            CatalogueIndex::write();

            return $product;
        });

        foreach ($filesToDelete as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        return $saved;
    }

    /**
     * @param  array<int, array<string, mixed>>  $specs
     */
    private function syncSpecs(Product $product, array $specs): void
    {
        $product->specs()->delete();
        $position = 0;

        foreach ($specs as $spec) {
            $label = trim((string) ($spec['label'] ?? ''));
            $value = trim((string) ($spec['value'] ?? ''));
            if ($label === '' || $value === '') {
                continue;
            }

            ProductSpec::query()->create([
                'product_id' => $product->id,
                'label' => $label,
                'value' => $value,
                'position' => $position++,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function syncImages(Product $product, Category $category, array $data, Request $request): array
    {
        $removeIds = collect($data['remove_images'] ?? [])->map(fn ($id) => (int) $id)->all();
        $filesToDelete = [];

        $product->images()->whereIn('id', $removeIds)->get()->each(function (ProductImage $image) use (&$filesToDelete): void {
            $path = $image->path;
            $image->delete();

            if (ProductImage::query()->where('path', $path)->exists()) {
                return;
            }

            $fullPath = $this->publicProductImagePath($path);
            if ($fullPath !== null) {
                $filesToDelete[] = $fullPath;
            }
        });

        foreach ($data['existing_images'] ?? [] as $id => $fields) {
            if (in_array((int) $id, $removeIds, true)) {
                continue;
            }

            $image = $product->images()->whereKey($id)->first();
            if (! $image instanceof ProductImage) {
                continue;
            }

            $alt = trim((string) ($fields['alt'] ?? ''));
            $image->update([
                'alt' => $alt !== '' ? $alt : $product->name,
                'position' => (int) ($fields['position'] ?? $image->position),
            ]);
        }

        $position = ((int) $product->images()->max('position')) + 1;
        $alts = $data['photo_alts'] ?? [];

        foreach ($request->file('photos', []) as $index => $photo) {
            if (! $photo instanceof UploadedFile) {
                continue;
            }

            $alt = trim((string) ($alts[$index] ?? ''));
            $this->storePhoto($product, $category, $photo, $alt !== '' ? $alt : $product->name, $position);
            $position++;
        }

        return $filesToDelete;
    }

    private function storePhoto(Product $product, Category $category, UploadedFile $photo, string $alt, int $position): void
    {
        $extension = strtolower((string) ($photo->guessExtension() ?: $photo->getClientOriginalExtension()));
        if ($extension === 'jpeg') {
            $extension = 'jpg';
        }
        if (! in_array($extension, ['jpg', 'png', 'webp'], true)) {
            throw ValidationException::withMessages([
                'photos' => 'Each photo must be a JPG, PNG, or WebP file.',
            ]);
        }

        $base = Str::slug(pathinfo($photo->getClientOriginalName(), PATHINFO_FILENAME));
        $filename = ($base !== '' ? $base : 'stone').'-'.Str::lower(Str::random(8)).'.'.$extension;
        $relativeDirectory = 'assets/img/products/'.$category->slug.'/'.$product->slug;
        File::ensureDirectoryExists(public_path($relativeDirectory));
        $photo->move(public_path($relativeDirectory), $filename);

        $relativePath = $relativeDirectory.'/'.$filename;
        $size = getimagesize(public_path($relativePath));

        $product->images()->create([
            'path' => $relativePath,
            'alt' => $alt,
            'width' => is_array($size) ? $size[0] : null,
            'height' => is_array($size) ? $size[1] : null,
            'position' => $position,
        ]);
    }

    private function publicProductImagePath(string $path): ?string
    {
        $path = str_replace('\\', '/', $path);
        if ($path === '' || str_contains($path, '..') || ! str_starts_with($path, 'assets/img/products/')) {
            return null;
        }

        return public_path($path);
    }

    /**
     * @return list<string>
     */
    private function listFromText(string $value): array
    {
        $parts = preg_split('/\s*,\s*/', trim($value)) ?: [];

        return array_values(array_unique(array_filter(
            array_map(trim(...), $parts),
            fn (string $part): bool => $part !== '',
        )));
    }
}
