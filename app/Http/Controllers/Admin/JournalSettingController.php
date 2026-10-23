<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\JournalSetting;
use App\Models\Post;
use App\Support\JournalDestination;
use App\Support\ResponsivePicture;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class JournalSettingController extends Controller
{
    public function __construct(private ResponsivePicture $pictures) {}

    public function edit(): View
    {
        return view('admin.journal.edit', [
            'journal' => JournalSetting::current(),
            'posts' => Post::query()->orderBy('title')->get(['id', 'title', 'is_published']),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'eyebrow' => ['required', 'string', 'max:80'],
            'heading_lead' => ['required', 'string', 'max:80'],
            'heading_emphasis' => ['required', 'string', 'max:80'],
            'introduction' => ['required', 'string', 'max:500'],
            'caption' => ['nullable', 'string', 'max:180'],
            'cover_image_alt' => ['nullable', 'string', 'max:180'],
            'edit_kicker' => ['required', 'string', 'max:80'],
            'edit_heading' => ['required', 'string', 'max:160'],
            'edit_posts' => ['array', 'max:3'],
            'edit_posts.*' => ['nullable', 'integer', 'exists:posts,id'],
            'closer_kicker' => ['required', 'string', 'max:80'],
            'closer_heading' => ['required', 'string', 'max:160'],
            'closer_posts' => ['array', 'max:2'],
            'closer_posts.*' => ['nullable', 'integer', 'exists:posts,id'],
            'maker_post_id' => ['nullable', 'integer', 'exists:posts,id'],
            'maker_eyebrow' => ['nullable', 'string', 'max:80'],
            'maker_heading' => ['nullable', 'string', 'max:160'],
            'maker_body' => ['nullable', 'string', 'max:500'],
            'maker_label' => ['nullable', 'string', 'max:40'],
            'maker_path' => ['nullable', 'string', 'max:200', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! is_string($value) || trim($value) === '') {
                    return;
                }
                if (! JournalDestination::exists($value)) {
                    $fail('Choose a page that already exists on the site.');
                }
            }],
            'maker_image_alt' => ['nullable', 'string', 'max:180'],
            'cover' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'maker_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_cover' => ['nullable', 'boolean'],
            'remove_maker_image' => ['nullable', 'boolean'],
        ]);

        $settings = JournalSetting::current();
        $previousCover = $settings->cover_image;
        $previousMaker = $settings->maker_image;
        $cover = $previousCover;
        $makerImage = $previousMaker;
        $uploadedCover = $request->file('cover');
        if ($uploadedCover instanceof UploadedFile) {
            $cover = $this->storeUpload($uploadedCover, 'cover');
        } elseif ($request->boolean('remove_cover')) {
            $cover = null;
        }

        $uploadedMaker = $request->file('maker_image');
        if ($uploadedMaker instanceof UploadedFile) {
            $makerImage = $this->storeUpload($uploadedMaker, 'maker');
        } elseif ($request->boolean('remove_maker_image')) {
            $makerImage = null;
        }

        $path = trim((string) ($data['maker_path'] ?? ''));
        $settings->fill([
            'eyebrow' => $data['eyebrow'],
            'heading_lead' => $data['heading_lead'],
            'heading_emphasis' => $data['heading_emphasis'],
            'introduction' => $data['introduction'],
            'caption' => $data['caption'] ?? null,
            'cover_image' => $cover,
            'cover_image_alt' => $data['cover_image_alt'] ?? null,
            'edit_kicker' => $data['edit_kicker'],
            'edit_heading' => $data['edit_heading'],
            'edit_post_ids' => $this->postIds($data['edit_posts'] ?? [], 3),
            'closer_kicker' => $data['closer_kicker'],
            'closer_heading' => $data['closer_heading'],
            'closer_post_ids' => $this->postIds($data['closer_posts'] ?? [], 2),
            'maker_post_id' => $data['maker_post_id'] ?? null,
            'maker_eyebrow' => $data['maker_eyebrow'] ?? null,
            'maker_heading' => $data['maker_heading'] ?? null,
            'maker_body' => $data['maker_body'] ?? null,
            'maker_label' => $data['maker_label'] ?? null,
            'maker_path' => $path === '' ? null : JournalDestination::normalize($path),
            'maker_image' => $makerImage,
            'maker_image_alt' => $data['maker_image_alt'] ?? null,
        ]);
        $settings->edit_post_ids = $settings->edit_post_ids === [] ? null : $settings->edit_post_ids;
        $settings->closer_post_ids = $settings->closer_post_ids === [] ? null : $settings->closer_post_ids;
        $settings->save();

        if ($previousCover !== $settings->cover_image) {
            $this->deleteManaged($previousCover);
        }
        if ($previousMaker !== $settings->maker_image) {
            $this->deleteManaged($previousMaker);
        }

        return redirect()->route('admin.journal.edit')->with('status', 'Journal page saved.');
    }

    /**
     * @return list<int>|null
     */
    private function postIds(mixed $ids, int $limit): ?array
    {
        if (! is_array($ids)) {
            return null;
        }

        $clean = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id > 0 && ! in_array($id, $clean, true)) {
                $clean[] = $id;
            }
            if (count($clean) >= $limit) {
                break;
            }
        }

        return $clean === [] ? null : $clean;
    }

    private function storeUpload(UploadedFile $photo, string $name): string
    {
        $extension = strtolower((string) ($photo->guessExtension() ?: $photo->getClientOriginalExtension()));
        if ($extension === 'jpeg') {
            $extension = 'jpg';
        }
        if (! in_array($extension, ['jpg', 'png', 'webp'], true)) {
            throw ValidationException::withMessages([
                $name => 'Use a JPG, PNG, or WebP image.',
            ]);
        }

        $directory = 'assets/img/journal/page';
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
        if ($path === '' || str_contains($path, '..') || ! str_starts_with($path, 'assets/img/journal/page/')) {
            return;
        }

        $fullPath = public_path($path);
        if (is_file($fullPath)) {
            unlink($fullPath);
        }
    }
}
