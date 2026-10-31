<?php

namespace App\Services;

use App\Models\JournalSetting;
use App\Models\Post;
use App\Models\PostCategory;
use App\Services\BlogStudio\StudioDocumentService;
use App\Support\JournalDestination;
use App\Support\ServedUpload;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\View\View;

final class JournalIndex
{
    private const PER_PAGE = 6;

    private const PREVIEW_STORY_LIMIT = 3;

    private const COVER = 'assets/img/slide/slide-1.jpg';

    public function __construct(private ServedUpload $uploads) {}

    public function respond(Request $request): View|RedirectResponse
    {
        $search = $this->search($request);
        $categorySlug = trim($request->string('category')->toString());
        if ($categorySlug !== '' && preg_match('/^[A-Za-z0-9_-]+$/', $categorySlug) !== 1) {
            abort(404);
        }

        $category = null;
        if ($categorySlug !== '') {
            $category = PostCategory::query()->where('slug', $categorySlug)->first();
            if (! $category instanceof PostCategory) {
                abort(404);
            }
        }

        app(StudioDocumentService::class)->publishDue();

        $page = max(1, $request->integer('page'));
        $explicitArchive = $request->boolean('stories');
        $publishedTotal = Post::query()->published()->count();
        $browsing = $search !== '' || $category instanceof PostCategory || $explicitArchive;
        $settings = JournalSetting::current();
        $featured = $publishedTotal > 0 && ! $browsing ? $this->featuredPost($settings) : null;
        $posts = null;

        if ($publishedTotal > 0) {
            $query = $this->filtered($category, $search);
            if ($featured instanceof Post) {
                $query->whereKeyNot($featured->id);
            }
            $posts = $query->paginate(self::PER_PAGE)->withQueryString();
            if ($posts->currentPage() > $posts->lastPage()) {
                return redirect()->to($posts->url(max(1, $posts->lastPage())));
            }
        }

        $showFeature = $featured instanceof Post && $page === 1;
        $mode = $publishedTotal === 0 ? 'empty' : ($showFeature ? 'landing' : 'archive');
        $listed = new SupportCollection;
        if ($posts instanceof LengthAwarePaginator) {
            $listed = new SupportCollection($posts->getCollection()->all());
        }
        if ($showFeature) {
            $listed->prepend($featured);
        }
        $cover = $this->cover($settings);
        $featureImage = $featured instanceof Post ? $this->existingImage($featured->image) : null;

        return view('journal', [
            'settings' => $settings,
            'copy' => $this->copy($settings),
            'cover' => $cover,
            'featured' => $showFeature ? $featured : null,
            'featureImage' => $featureImage,
            'topics' => PostCategory::query()->orderBy('name')->get(['id', 'name', 'slug']),
            'category' => $category,
            'search' => $search,
            'mode' => $mode,
            'posts' => $posts,
            'pageNumbers' => $posts instanceof LengthAwarePaginator ? $this->pageNumbers($posts->currentPage(), $posts->lastPage()) : [],
            'resultSummary' => $mode === 'archive' && $posts instanceof LengthAwarePaginator ? $this->summary($posts, $search) : null,
            'resetUrl' => $this->resetUrl($mode, $category, $search, $explicitArchive, $page),
            'resetLabel' => $search !== '' ? 'Clear search' : ($category instanceof PostCategory ? 'All stories' : 'Back to the journal'),
            'publishedTotal' => $publishedTotal,
            'active' => 'blog',
            'bodyClass' => 'journal-page',
            'title' => $this->title($mode, $category, $search, $page),
            'description' => $this->description($mode, $settings, $category, $search),
            'canonical' => $this->canonical($category, $search, $explicitArchive, $page, $mode),
            'shareImage' => asset($featureImage ?: $cover['path']),
            'listingSchema' => $this->schema($mode, $category, $search, $listed),
        ]);
    }

    /**
     * Saved or drafted landing data for the journal editor preview.
     *
     * Uses the same published-story rules as the public landing: the featured
     * story is the first published choice in The Edit, otherwise the latest
     * published story. The preview lists the following published stories and
     * does not include hidden, future, or invented articles.
     *
     * @return array{
     *     featured: array<string, mixed>|null,
     *     stories: list<array<string, mixed>>,
     *     more: int,
     *     empty: bool,
     *     cards: \stdClass,
     *     order: list<int>,
     *     fallback_id: int|null,
     *     story_limit: int,
     *     page_size: int,
     *     standard_cover: string|null
     * }
     */
    public function editorPreview(JournalSetting $settings): array
    {
        $published = $this->stories()->get();
        $cards = [];
        $order = [];
        foreach ($published as $post) {
            $order[] = $post->id;
            $cards[(string) $post->id] = $this->previewCard($post);
        }

        $fallbackSettings = clone $settings;
        $fallbackSettings->edit_post_ids = [];
        $fallback = $published->isEmpty() ? null : $this->featuredPost($fallbackSettings);
        $featured = $published->isEmpty() ? null : $this->featuredPost($settings);
        $stories = [];
        $more = 0;
        $standardCover = $this->existingImage(self::COVER);

        if (! $published->isEmpty()) {
            $query = $this->stories();
            if ($featured instanceof Post) {
                $query->whereKeyNot($featured->id);
            }
            $remaining = (clone $query)->count();
            foreach ($query->limit(self::PREVIEW_STORY_LIMIT)->get() as $post) {
                $stories[] = $cards[(string) $post->id] ?? $this->previewCard($post);
            }
            $more = max(0, $remaining - count($stories));
        }

        return [
            'featured' => $featured instanceof Post ? ($cards[(string) $featured->id] ?? $this->previewCard($featured)) : null,
            'stories' => $stories,
            'more' => $more,
            'empty' => $published->isEmpty(),
            'cards' => (object) $cards,
            'order' => $order,
            'fallback_id' => $fallback instanceof Post ? $fallback->id : null,
            'story_limit' => self::PREVIEW_STORY_LIMIT,
            'page_size' => self::PER_PAGE,
            'standard_cover' => $standardCover !== null ? asset($standardCover) : null,
        ];
    }

    /**
     * @return array{id: int, title: string, summary: string, image: string|null, alt: string, decorative: bool, minutes: int, category: string|null, url: string}
     */
    private function previewCard(Post $post): array
    {
        $image = $this->existingImage($post->image);
        $storedAlt = trim((string) $post->featured_image_alt);
        $category = $post->postCategory?->name;

        return [
            'id' => $post->id,
            'title' => $post->title,
            'summary' => trim((string) $post->summary),
            'image' => $image !== null ? asset($image) : null,
            'alt' => $storedAlt !== '' ? $storedAlt : $post->title,
            'decorative' => $storedAlt === '' || strcasecmp($storedAlt, $post->title) === 0,
            'minutes' => $post->readingMinutes(),
            'category' => is_string($category) && $category !== '' ? $category : null,
            'url' => url('/'.$post->slug),
        ];
    }

    private function featuredPost(JournalSetting $settings): ?Post
    {
        $chosen = $this->chosen($this->ids($settings->edit_post_ids), [], 1);
        if ($chosen->isNotEmpty()) {
            $post = $chosen->first();

            return $post instanceof Post ? $post : null;
        }

        $latest = $this->latest([], 8);
        foreach ($latest as $post) {
            if ($this->existingImage($post->image) !== null) {
                return $post;
            }
        }

        $fallback = $latest->first();

        return $fallback instanceof Post ? $fallback : null;
    }

    /**
     * @return Builder<Post>
     */
    private function stories(): Builder
    {
        return Post::query()
            ->published()
            ->with('postCategory:id,name,slug')
            ->select([
                'id',
                'slug',
                'title',
                'summary',
                'image',
                'featured_image_alt',
                'published_on',
                'post_category_id',
                'reading_minutes',
            ])
            ->orderByDesc('published_on')
            ->orderBy('title');
    }

    /**
     * @return Builder<Post>
     */
    private function filtered(?PostCategory $category, string $search): Builder
    {
        $stories = $this->stories();
        if ($category instanceof PostCategory) {
            $stories->where('post_category_id', $category->id);
        }
        if ($search !== '') {
            $stories->where(function (Builder $query) use ($search): void {
                $query->whereLike('title', '%'.$search.'%', false)
                    ->orWhereLike('summary', '%'.$search.'%', false)
                    ->orWhereLike('body', '%'.$search.'%', false)
                    ->orWhereLike('body_document', '%'.$search.'%', false);
            });
        }

        return $stories;
    }

    /**
     * @return array{0: Collection<int, Post>, 1: Collection<int, Post>}
     */
    private function curated(JournalSetting $settings): array
    {
        $configuredEdit = $this->ids($settings->edit_post_ids);
        $edit = $configuredEdit === []
            ? $this->latest([], 3)
            : $this->chosen($configuredEdit, [], 3);
        if ($edit->isEmpty()) {
            $edit = $this->latest([], 3);
        }

        $except = $edit->modelKeys();
        $configuredCloser = $this->ids($settings->closer_post_ids);
        $closer = $configuredCloser === []
            ? $this->latest($except, 2)
            : $this->chosen($configuredCloser, $except, 2);

        return [$edit, $closer];
    }

    /**
     * @param  list<int>  $ids
     * @param  list<int|string>  $except
     * @return Collection<int, Post>
     */
    private function chosen(array $ids, array $except, int $limit): Collection
    {
        $except = array_map(intval(...), $except);
        $ids = array_values(array_filter(
            $ids,
            fn (int $id): bool => $id > 0 && ! in_array($id, $except, true),
        ));
        if ($ids === []) {
            return new Collection;
        }

        $posts = $this->stories()->whereIn('id', $ids)->get()->keyBy('id');
        $chosen = new Collection;
        foreach ($ids as $id) {
            $post = $posts->get($id);
            if ($post instanceof Post && $chosen->count() < $limit) {
                $chosen->push($post);
            }
        }

        return $chosen;
    }

    /**
     * @param  list<int|string>  $except
     * @return Collection<int, Post>
     */
    private function latest(array $except, int $limit): Collection
    {
        $except = array_values(array_filter(array_map(intval(...), $except)));

        return $this->stories()
            ->when($except !== [], fn (Builder $query) => $query->whereNotIn('id', $except))
            ->limit($limit)
            ->get();
    }

    /**
     * @return list<int>
     */
    private function ids(mixed $ids): array
    {
        if (! is_array($ids)) {
            return [];
        }

        $clean = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id > 0 && ! in_array($id, $clean, true)) {
                $clean[] = $id;
            }
        }

        return $clean;
    }

    /**
     * @return array{eyebrow: string, heading: string, body: string, label: string, url: string, image: ?string, alt: string}|null
     */
    private function maker(JournalSetting $settings): ?array
    {
        $defaults = JournalSetting::defaults();
        $postId = (int) $settings->maker_post_id;
        if ($postId > 0) {
            $post = $this->stories()->whereKey($postId)->first();
            if (! $post instanceof Post) {
                return null;
            }

            $heading = $this->text($settings->maker_heading, $post->title);
            $image = $this->existingImage($settings->maker_image) ?? $this->existingImage($post->image);
            $alt = $this->text($settings->maker_image_alt, $this->text($post->featured_image_alt, $heading));

            return [
                'eyebrow' => $this->text($settings->maker_eyebrow, $defaults['maker_eyebrow']),
                'heading' => $heading,
                'body' => $this->text($settings->maker_body, (string) $post->summary),
                'label' => $this->text($settings->maker_label, $defaults['maker_label']),
                'url' => url('/'.$post->slug),
                'image' => $image,
                'alt' => $alt,
            ];
        }

        $path = JournalDestination::normalize((string) $settings->maker_path);
        $heading = trim((string) $settings->maker_heading);
        if ($path === null || $heading === '' || ! JournalDestination::exists($path)) {
            return null;
        }

        $image = $this->existingImage($settings->maker_image);

        return [
            'eyebrow' => $this->text($settings->maker_eyebrow, $defaults['maker_eyebrow']),
            'heading' => $heading,
            'body' => trim((string) $settings->maker_body),
            'label' => $this->text($settings->maker_label, $defaults['maker_label']),
            'url' => url($path),
            'image' => $image,
            'alt' => $this->text($settings->maker_image_alt, $heading),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function copy(JournalSetting $settings): array
    {
        $defaults = JournalSetting::defaults();
        $copy = [];
        foreach (['eyebrow', 'heading_lead', 'heading_emphasis', 'introduction', 'caption', 'edit_kicker', 'edit_heading', 'closer_kicker', 'closer_heading'] as $field) {
            $copy[$field] = $this->text($settings->{$field} ?? null, $defaults[$field] ?? '');
        }

        return $copy;
    }

    /**
     * @return array{path: string, alt: string}
     */
    private function cover(JournalSetting $settings): array
    {
        $defaults = JournalSetting::defaults();

        return [
            'path' => $this->existingImage($settings->cover_image) ?? self::COVER,
            'alt' => $this->text($settings->cover_image_alt, $defaults['cover_image_alt']),
        ];
    }

    private function text(?string $value, string $fallback): string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : $fallback;
    }

    private function existingImage(?string $path): ?string
    {
        $path = str_replace('\\', '/', trim((string) $path));
        if ($path === '' || str_contains($path, '..') || str_contains($path, '://')) {
            return null;
        }

        $absolute = $this->uploads->absolute($path);

        return $absolute !== null && is_file($absolute) ? $path : null;
    }

    private function search(Request $request): string
    {
        $search = trim(preg_replace('/\s+/u', ' ', $request->string('q')->toString()) ?? '');

        return mb_substr($search, 0, 120);
    }

    private function summary(LengthAwarePaginator $posts, string $search): ?string
    {
        if ($posts->total() === 0) {
            return null;
        }

        $count = $posts->total().' '.($posts->total() === 1 ? 'story' : 'stories');
        if ($posts->lastPage() > 1) {
            $count = 'Showing '.$posts->firstItem().'–'.$posts->lastItem().' of '.$count;
        }
        if ($search !== '') {
            $count .= ' matching “'.$search.'”';
        }

        return $count;
    }

    private function resetUrl(string $mode, ?PostCategory $category, string $search, bool $explicitArchive, int $page): ?string
    {
        if ($mode === 'empty') {
            return null;
        }
        if ($search !== '' && $category instanceof PostCategory) {
            return url('/blog').'?'.http_build_query(['category' => $category->slug]);
        }
        if ($search !== '' || $category instanceof PostCategory || $explicitArchive || $page > 1) {
            return url('/blog');
        }

        return null;
    }

    private function title(string $mode, ?PostCategory $category, string $search, int $page): string
    {
        $brand = 'The Journal | Royal Indian Stones';
        if ($search !== '') {
            return 'Search stories | '.$brand;
        }
        if ($category instanceof PostCategory) {
            $title = $category->name.' | '.$brand;

            return $page > 1 ? $title.', page '.$page : $title;
        }
        if ($mode === 'archive') {
            return ($page > 1 ? 'All stories, page '.$page : 'All stories').' | '.$brand;
        }

        return $brand;
    }

    private function description(string $mode, JournalSetting $settings, ?PostCategory $category, string $search): string
    {
        if ($search !== '') {
            return 'Published stories matching “'.$search.'”.';
        }
        if ($category instanceof PostCategory) {
            return 'Published stories filed under '.$category->name.'.';
        }
        if ($mode === 'archive') {
            return 'All published stories from the Royal Indian Stones journal.';
        }

        $introduction = trim((string) $settings->introduction);
        if ($introduction === '') {
            $introduction = JournalSetting::defaults()['introduction'];
        }

        return mb_substr($introduction, 0, 160);
    }

    private function canonical(?PostCategory $category, string $search, bool $explicitArchive, int $page, string $mode): string
    {
        if ($mode === 'empty') {
            return url('/blog');
        }

        $params = [];
        if ($category instanceof PostCategory) {
            $params['category'] = $category->slug;
        }
        if ($search !== '') {
            $params['q'] = $search;
        }
        if ($explicitArchive && $category === null && $search === '') {
            $params['stories'] = 1;
        }
        if ($page > 1) {
            $params['page'] = $page;
        }

        $canonical = url('/blog');
        if ($params !== []) {
            $canonical .= '?'.http_build_query($params);
        }

        return $canonical;
    }

    /**
     * @return list<int>
     */
    private function pageNumbers(int $current, int $last): array
    {
        if ($last <= 1) {
            return [];
        }
        if ($last <= 9) {
            return range(1, $last);
        }

        $start = max(1, $current - 2);
        $end = min($last, $start + 4);
        $start = max(1, $end - 4);

        return range($start, $end);
    }

    /**
     * @param  SupportCollection<int, Post>  $posts
     * @return array<string, mixed>
     */
    private function schema(string $mode, ?PostCategory $category, string $search, SupportCollection $posts): array
    {
        $crumbs = [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => url('/')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'The Journal', 'item' => url('/blog')],
        ];
        if ($category instanceof PostCategory) {
            $crumbs[] = [
                '@type' => 'ListItem',
                'position' => 3,
                'name' => $category->name,
                'item' => url('/blog').'?'.http_build_query(['category' => $category->slug]),
            ];
        } elseif ($search !== '') {
            $crumbs[] = ['@type' => 'ListItem', 'position' => 3, 'name' => 'Search'];
        } elseif ($mode === 'archive') {
            $crumbs[] = [
                '@type' => 'ListItem',
                'position' => 3,
                'name' => 'All stories',
                'item' => url('/blog').'?'.http_build_query(['stories' => 1]),
            ];
        }

        $graph = [
            ['@type' => 'BreadcrumbList', 'itemListElement' => $crumbs],
        ];
        if ($posts->isNotEmpty()) {
            $graph[] = [
                '@type' => 'ItemList',
                'itemListElement' => $posts->values()->map(fn (Post $post, int $index): array => [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'name' => $post->title,
                    'url' => url('/'.$post->slug),
                ])->all(),
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@graph' => $graph,
        ];
    }
}
