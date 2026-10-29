<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\HomeImage;
use App\Models\HomeSection;
use App\Support\HomeContent;
use App\Support\JournalDestination;
use App\Support\ResponsivePicture;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class HomePageController extends Controller
{
    public function __construct(private HomeContent $content, private ResponsivePicture $pictures) {}

    public function edit(): View
    {
        return view('admin.home.edit', [
            'groups' => $this->content->groups(Category::query()->orderBy('position')->get()),
        ]);
    }

    public function update(Request $request, string $section): RedirectResponse
    {
        if (! in_array($section, ['hero', 'supplier', 'explore', 'stones', 'journal', 'enquiry'], true)) {
            abort(404);
        }

        $categories = Category::query()->orderBy('position')->get();
        $slots = $this->content->slots($section, $categories);
        $rules = [];
        $messages = [];
        foreach ($this->sectionFields($section) as $field => $max) {
            $rules['copy.'.$section.'.'.$field] = $this->plainText($max);
            $messages['copy.'.$section.'.'.$field.'.max'] = 'Keep this under '.$max.' characters.';
        }
        foreach ($slots as $slot) {
            $rules['alt.'.$section.'.'.$slot] = ['nullable', 'string', 'max:180'];
            $rules['photo.'.$section.'.'.$slot] = ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'];
            $rules['restore.'.$section.'.'.$slot] = ['nullable', 'boolean'];
            $messages['photo.'.$section.'.'.$slot.'.mimes'] = 'Use a JPG, PNG, or WebP image.';
            $messages['photo.'.$section.'.'.$slot.'.max'] = 'Use an image smaller than 5 MB.';
            $messages['alt.'.$section.'.'.$slot.'.max'] = 'Keep the description under 180 characters.';
            foreach ($this->slotFields($section, $slot) as $field => $max) {
                $rules[$field.'.'.$section.'.'.$slot] = $field === 'link'
                    ? $this->pageLink()
                    : $this->plainText($max);
                $messages[$field.'.'.$section.'.'.$slot.'.max'] = 'Keep this under '.$max.' characters.';
            }
        }

        $request->validate($rules, $messages);

        if ($this->sectionFields($section) !== []) {
            $this->saveSection($request, $section);
        }
        if ($slots !== []) {
            $this->saveSlots($request, $section, $slots);
        }

        $status = match ($section) {
            'hero' => 'Opening slides saved.',
            'supplier' => 'Supplier line saved.',
            'explore' => 'Explore the collection saved.',
            'stones' => 'Natural Indian Stones saved.',
            'journal' => 'Journal heading saved.',
            'enquiry' => 'Closing band saved.',
        };

        return redirect()
            ->route('admin.home.edit')
            ->with('status', $status)
            ->with('home_section', $section);
    }

    /**
     * @return array<string, int>
     */
    private function sectionFields(string $section): array
    {
        return match ($section) {
            'supplier' => ['heading' => 160, 'body' => 1200, 'button_label' => 80],
            'explore', 'stones' => ['heading' => 160, 'body' => 1200],
            'journal' => ['heading' => 160, 'button_label' => 80],
            'enquiry' => ['eyebrow' => 80, 'heading' => 160, 'body' => 500, 'button_label' => 80],
            default => [],
        };
    }

    /**
     * @return array<string, int>
     */
    private function slotFields(string $section, string $slot): array
    {
        return match ($section) {
            'hero' => array_filter([
                'eyebrow' => 80,
                'title' => 160,
                'subtitle' => $slot === 'sandstone' ? 120 : null,
                'body' => 500,
                'button_label' => 80,
                'link' => 200,
            ], fn (?int $max): bool => $max !== null),
            'stones' => ['title' => 160, 'link' => 200],
            default => [],
        };
    }

    /**
     * @return list<string|Closure>
     */
    private function plainText(int $max): array
    {
        return ['nullable', 'string', 'max:'.$max, function (string $attribute, mixed $value, Closure $fail): void {
            if (is_string($value) && preg_match('/[<>]/', $value) === 1) {
                $fail('Use plain text.');
            }
        }];
    }

    /**
     * @return list<string|Closure>
     */
    private function pageLink(): array
    {
        return ['nullable', 'string', 'max:200', function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value) || trim($value) === '') {
                return;
            }
            if (! JournalDestination::exists($value)) {
                $fail('Choose a page that already exists on the site.');
            }
        }];
    }

    private function saveSection(Request $request, string $section): void
    {
        $bag = $request->input('copy');
        if (! is_array($bag) || ! isset($bag[$section]) || ! is_array($bag[$section])) {
            return;
        }

        $record = HomeSection::query()->firstOrNew(['key' => $section]);
        foreach (array_keys($this->sectionFields($section)) as $field) {
            if (! array_key_exists($field, $bag[$section])) {
                continue;
            }
            $value = trim((string) $bag[$section][$field]);
            $record->{$field} = $value === '' ? null : $value;
        }

        if ($this->sectionIsBlank($record)) {
            if ($record->exists) {
                $record->delete();
            }

            return;
        }

        $record->save();
    }

    private function sectionIsBlank(HomeSection $record): bool
    {
        foreach (['eyebrow', 'heading', 'body', 'button_label'] as $field) {
            if (is_string($record->{$field}) && trim($record->{$field}) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<string>  $slots
     */
    private function saveSlots(Request $request, string $section, array $slots): void
    {
        $retired = [];
        DB::transaction(function () use ($request, $section, $slots, &$retired): void {
            foreach ($slots as $slot) {
                $image = HomeImage::query()->firstOrNew([
                    'section' => $section,
                    'slot' => $slot,
                ]);
                $previous = is_string($image->path) ? $image->path : null;
                $path = $previous;
                $uploaded = $request->file('photo.'.$section.'.'.$slot);

                if ($uploaded instanceof UploadedFile) {
                    $path = $this->storeUpload($uploaded, $section, $slot);
                } elseif ($request->boolean('restore.'.$section.'.'.$slot) || ($previous !== null && ! is_file(public_path($previous)))) {
                    $path = null;
                }

                $image->path = $path;
                $this->fillSlotText($request, $image, $section, $slot);

                if ($this->slotIsBlank($image)) {
                    if ($image->exists) {
                        $image->delete();
                    }
                } else {
                    $image->save();
                }

                if ($previous !== null && $previous !== $image->path) {
                    $retired[] = $previous;
                }
            }
        });

        foreach ($retired as $path) {
            $this->deleteManaged($path);
        }
    }

    private function fillSlotText(Request $request, HomeImage $image, string $section, string $slot): void
    {
        foreach (array_keys($this->slotFields($section, $slot)) as $field) {
            $column = $field === 'body' ? 'body' : $field;
            $bag = $request->input($field);
            if (! is_array($bag) || ! isset($bag[$section]) || ! is_array($bag[$section]) || ! array_key_exists($slot, $bag[$section])) {
                continue;
            }
            if ($field === 'subtitle' && $slot !== 'sandstone') {
                continue;
            }
            $value = trim((string) $bag[$section][$slot]);
            if ($field === 'link' && $value !== '') {
                $value = JournalDestination::normalize($value) ?? $value;
            }
            $image->{$column} = $value === '' ? null : $value;
        }

        $alts = $request->input('alt');
        if (is_array($alts) && isset($alts[$section]) && is_array($alts[$section]) && array_key_exists($slot, $alts[$section])) {
            $alt = trim((string) $alts[$section][$slot]);
            $image->alt = $alt === '' ? null : $alt;
        }
    }

    private function slotIsBlank(HomeImage $image): bool
    {
        foreach (['path', 'alt', 'eyebrow', 'title', 'subtitle', 'body', 'button_label', 'link'] as $field) {
            if (is_string($image->{$field}) && trim($image->{$field}) !== '') {
                return false;
            }
        }

        return true;
    }

    private function storeUpload(UploadedFile $photo, string $section, string $slot): string
    {
        $extension = strtolower((string) ($photo->guessExtension() ?: $photo->getClientOriginalExtension()));
        if ($extension === 'jpeg') {
            $extension = 'jpg';
        }
        if (! in_array($extension, ['jpg', 'png', 'webp'], true)) {
            throw ValidationException::withMessages([
                'photo.'.$section.'.'.$slot => 'Use a JPG, PNG, or WebP image.',
            ]);
        }

        $name = Str::slug($slot);
        if ($name === '') {
            $name = 'photograph';
        }

        $directory = 'assets/img/home/'.$section;
        File::ensureDirectoryExists(public_path($directory));
        $filename = $name.'-'.Str::lower(Str::random(8)).'.'.$extension;
        $photo->move(public_path($directory), $filename);
        $path = $directory.'/'.$filename;
        $this->pictures->ensure($path);

        return $path;
    }

    private function deleteManaged(?string $path): void
    {
        $path = str_replace('\\', '/', (string) $path);
        if ($path === '' || str_contains($path, '..') || ! str_starts_with($path, 'assets/img/home/')) {
            return;
        }

        $fullPath = public_path($path);
        if (is_file($fullPath)) {
            unlink($fullPath);
        }
    }
}
