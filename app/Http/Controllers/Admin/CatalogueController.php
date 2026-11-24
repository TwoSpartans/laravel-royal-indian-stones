<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Catalogue;
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

class CatalogueController extends Controller
{
    private const PDF_MAX_KILOBYTES = 40960;

    public function __construct(private ResponsivePicture $pictures, private ServedUpload $uploads) {}

    public function index(): View
    {
        return view('admin.catalogues.index', [
            'catalogues' => $this->orderedCatalogues(),
        ]);
    }

    public function create(): View
    {
        return view('admin.catalogues.form', [
            'catalogue' => new Catalogue([
                'position' => $this->nextPosition(),
            ]),
            'pdfReady' => false,
            'maxMegabytes' => $this->maxMegabytes(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $catalogue = $this->save($request, new Catalogue);

        return redirect()->route('admin.catalogues.edit', $catalogue)->with('status', 'Catalogue saved.');
    }

    public function edit(Catalogue $catalogue): View
    {
        return view('admin.catalogues.form', [
            'catalogue' => $catalogue,
            'pdfReady' => $catalogue->pdfFileExists(),
            'maxMegabytes' => $this->maxMegabytes(),
        ]);
    }

    public function update(Request $request, Catalogue $catalogue): RedirectResponse
    {
        $this->save($request, $catalogue);

        return redirect()->route('admin.catalogues.edit', $catalogue)->with('status', 'Catalogue updated.');
    }

    public function destroy(Catalogue $catalogue): RedirectResponse
    {
        $paths = array_values(array_filter([
            $catalogue->pdf_path,
            $catalogue->cover_path,
        ], fn (mixed $path): bool => is_string($path) && $path !== ''));

        $catalogue->delete();

        foreach ($paths as $path) {
            $this->forgetFile($path);
        }

        return redirect()->route('admin.catalogues.index')->with('status', 'Catalogue deleted.');
    }

    public function reorder(Request $request): RedirectResponse
    {
        $catalogues = $this->orderedCatalogues();
        $ordered = $request->exists('ids')
            ? $this->orderFromIds($request, $catalogues)
            : $this->orderFromMove($request, $catalogues);

        if ($ordered === null) {
            return back();
        }

        DB::transaction(function () use ($ordered): void {
            foreach ($ordered as $position => $catalogue) {
                if ((int) $catalogue->position === $position) {
                    continue;
                }

                $catalogue->position = $position;
                $catalogue->save();
            }
        });

        return back()->with('status', 'Order saved.');
    }

    private function save(Request $request, Catalogue $catalogue): Catalogue
    {
        $request->merge([
            'slug' => $request->filled('slug')
                ? $request->string('slug')->toString()
                : Str::slug($request->string('title')->toString()),
        ]);

        $pdfRequired = ! $catalogue->exists || ! $catalogue->pdfFileExists();
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:200', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('catalogues', 'slug')->ignore($catalogue->id)],
            'description' => ['nullable', 'string', 'max:500'],
            'version_label' => ['nullable', 'string', 'max:40'],
            'position' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'cover' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_cover' => ['nullable', 'boolean'],
            'pdf' => [$pdfRequired ? 'required' : 'nullable', 'file', 'mimes:pdf', 'max:'.self::PDF_MAX_KILOBYTES],
        ], [
            'pdf.required' => 'Choose a PDF to upload.',
            'pdf.mimes' => 'Upload a PDF file.',
            'pdf.max' => 'The PDF must be '.$this->maxMegabytes().' MB or smaller.',
            'cover.mimes' => 'The cover must be a JPG, PNG, or WebP file.',
            'cover.max' => 'The cover must be 5 MB or smaller.',
        ]);

        $previousPdf = $catalogue->pdf_path;
        $previousCover = $catalogue->cover_path;
        $position = $data['position'] ?? null;

        $saved = DB::transaction(function () use ($request, $catalogue, $data, $position): Catalogue {
            $catalogue->fill([
                'title' => $data['title'],
                'slug' => $data['slug'],
                'description' => $this->nullableText($data['description'] ?? null),
                'version_label' => $this->nullableText($data['version_label'] ?? null),
                'position' => $position !== null ? (int) $position : ($catalogue->exists ? (int) $catalogue->position : $this->nextPosition()),
            ]);
            $catalogue->save();

            $pdf = $request->file('pdf');
            if ($pdf instanceof UploadedFile) {
                $bytes = $pdf->getSize();
                $original = $this->originalName($pdf);
                $catalogue->pdf_path = $this->storePdf($catalogue, $pdf);
                $catalogue->pdf_original_name = $original;
                $catalogue->pdf_bytes = $bytes === false ? null : $bytes;
            }

            $cover = $request->file('cover');
            if ($cover instanceof UploadedFile) {
                $catalogue->cover_path = $this->storeCover($catalogue, $cover);
            } elseif ($request->boolean('remove_cover')) {
                $catalogue->cover_path = null;
            }

            $catalogue->save();

            return $catalogue;
        });

        if (is_string($previousPdf) && $previousPdf !== $saved->pdf_path) {
            $this->forgetFile($previousPdf);
        }
        if (is_string($previousCover) && $previousCover !== $saved->cover_path) {
            $this->forgetFile($previousCover);
        }

        return $saved;
    }

    private function storePdf(Catalogue $catalogue, UploadedFile $pdf): string
    {
        return $this->uploads->store(
            $pdf,
            'catalogue/'.$catalogue->slug,
            'catalogue-'.Str::lower(Str::random(8)).'.pdf',
        );
    }

    private function storeCover(Catalogue $catalogue, UploadedFile $photo): string
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

        $relativePath = $this->uploads->store(
            $photo,
            'catalogue/'.$catalogue->slug,
            'cover-'.Str::lower(Str::random(8)).'.'.$extension,
        );
        $this->pictures->ensure($relativePath);

        return $relativePath;
    }

    private function forgetFile(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        $stillUsed = Catalogue::query()
            ->where('pdf_path', $path)
            ->orWhere('cover_path', $path)
            ->exists();
        if ($stillUsed) {
            return;
        }

        $this->uploads->delete($path);
    }

    /**
     * @param  Collection<int, Catalogue>  $catalogues
     * @return Collection<int, Catalogue>
     */
    private function orderFromIds(Request $request, Collection $catalogues): Collection
    {
        $data = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'distinct', 'exists:catalogues,id'],
        ]);

        $submitted = array_map(intval(...), $data['ids']);
        $existing = $catalogues->pluck('id')->map(fn ($id) => (int) $id)->all();
        sort($existing);
        $check = $submitted;
        sort($check);

        if ($check !== $existing) {
            throw ValidationException::withMessages([
                'ids' => 'Send every catalogue, each once.',
            ]);
        }

        $byId = $catalogues->keyBy('id');

        return collect($submitted)->map(fn (int $id) => $byId->get($id))->values();
    }

    /**
     * @param  Collection<int, Catalogue>  $catalogues
     * @return Collection<int, Catalogue>|null
     */
    private function orderFromMove(Request $request, Collection $catalogues): ?Collection
    {
        $data = $request->validate([
            'id' => ['required', 'integer', 'exists:catalogues,id'],
            'direction' => ['required', Rule::in(['up', 'down'])],
        ]);

        $index = $catalogues->search(fn (Catalogue $catalogue): bool => $catalogue->id === (int) $data['id']);
        if ($index === false) {
            return null;
        }

        $swapWith = $data['direction'] === 'up' ? $index - 1 : $index + 1;
        if ($swapWith < 0 || $swapWith >= $catalogues->count()) {
            return null;
        }

        $reordered = $catalogues->values();
        $moving = $reordered->get($index);
        $reordered->put($index, $reordered->get($swapWith));
        $reordered->put($swapWith, $moving);

        return $reordered->values();
    }

    /**
     * @return Collection<int, Catalogue>
     */
    private function orderedCatalogues(): Collection
    {
        return Catalogue::query()->orderBy('position')->orderBy('title')->orderBy('id')->get();
    }

    private function nextPosition(): int
    {
        $highest = Catalogue::query()->max('position');

        return $highest === null ? 0 : ((int) $highest) + 1;
    }

    private function originalName(UploadedFile $pdf): string
    {
        $name = trim(basename(str_replace('\\', '/', $pdf->getClientOriginalName())));

        return $name !== '' ? Str::limit($name, 180, '') : 'catalogue.pdf';
    }

    private function nullableText(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function maxMegabytes(): int
    {
        return (int) (self::PDF_MAX_KILOBYTES / 1024);
    }
}
