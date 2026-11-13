# Royal Indian Stones blog replication spec

Inspection source: `D:\LARAVEL-ROYAL-INDIAN-STONES`  
Branch: `main`  
Commit: `02c559725bc65b32eda26e81109c6bc0473a938d` (2 Nov 2026, “bug fix”)  
Working tree at inspection time was dirty. The running application includes these uncommitted files, and this spec describes that working tree:

- `app/Http/Controllers/Admin/BlogStudioController.php`
- `app/Services/BlogStudio/StudioDocumentService.php`
- `public/assets/css/admin.css`
- `public/assets/css/blog-studio.css`
- `public/assets/js/blog-studio.js`
- `resources/views/admin/layout.blade.php`
- `resources/views/admin/posts/index.blade.php`
- `resources/views/admin/posts/studio.blade.php`
- `tests/Feature/BlogStudioTest.php`

Running app: `http://127.0.0.1:8000` (PHP built-in server from that repository). Vite for the site shell was on `[::1]:5173`. Blog Studio and the Journal do not use the Vite bundle. They load static files under `public/assets`.

The Cursor workspace that received this handoff is `Royal-Indian-Handicraft-Laravel`. That project has a different journal. Do not copy it. The reference implementation is Royal Indian Stones.

## Stack and run commands

| Piece | Locked or running value |
| --- | --- |
| PHP | `^8.3` required; running `8.5.10` |
| Laravel | `v13.36.0` (`composer.lock`) |
| Tests | PHPUnit `^12.5.12`. Not Pest. |
| Vite | `8.3.4` |
| Tailwind CSS | `4.3.4` in the site shell; Journal and Studio use hand-written CSS |
| laravel-vite-plugin | `3.2.0` |
| Editor | Vanilla JavaScript in `public/assets/js/blog-studio.js`. No Tiptap, React, Alpine, or Cropper. |
| Icons | Font Awesome 6.7.2 from cdnjs in Studio. Bootstrap Icons in admin. |
| Database | MySQL. Application timezone `Asia/Kolkata`. |
| Local URL | `APP_URL=http://localhost:8000`. Browser inspection used `http://127.0.0.1:8000`. |

Run from the Stones repository:

```bash
composer install
npm install
php artisan migrate
php artisan storage:link
php artisan serve --host=127.0.0.1 --port=8000
npm run dev
```

`composer run dev` calls `php artisan dev`. Blog Studio still works if Vite is down, because `resources/views/admin/posts/studio.blade.php` loads `public/assets/css/blog-studio.css?v=17` and `public/assets/js/blog-studio.js?v=13` directly.

Copied portable files for the destination agent:

- `docs/blog-handoff/source/css/journal.css`
- `docs/blog-handoff/source/css/blog-studio.css`
- `docs/blog-handoff/source/js/blog-studio.js`
- `docs/blog-handoff/source/views/journal.blade.php`
- `docs/blog-handoff/source/views/journal-card.blade.php`
- `docs/blog-handoff/source/views/post.blade.php`
- `docs/blog-handoff/source/views/document.blade.php`

Screenshots: `docs/blog-handoff/screenshots/`.

## Route map

Public pages are a single catch-all. `/blog` is not its own route name. `SiteController::show` treats the slug `blog` as the journal.

| Method | Path | Name | Handler |
| --- | --- | --- | --- |
| GET | `/blogs` | none | 301 to `/blog` plus the original query string. `routes/web.php` lines 110–114 |
| GET | `/blog` | `site.show` | `SiteController::show` → `JournalIndex::respond` |
| GET | `/{slug}` | `site.show` | Published post, CMS page, product, or category. Slug charset `[A-Za-z0-9_-]+` |
| GET | `/{slug}.html` | none | 301 to `/{slug}` |
| POST | `/{slug}/comments` | `comments.store` | `CommentController::store`, throttle `comments` |
| GET | `/sitemap.xml` and `/sitemap-{name}.xml` | `sitemap.xml`, `sitemap.show` | Published posts are a journal sitemap group |

Admin group: prefix `/admin`, name `admin.`, middleware `NoindexAdminResponses`, `auth`, `site.admin` (`User.is_site_admin`).

| Method | Path | Name | Handler |
| --- | --- | --- | --- |
| GET | `/admin/posts` | `admin.posts.index` | `PostController@index` |
| GET | `/admin/posts/studio/create` | `admin.posts.studio.create` | `BlogStudioController@create` |
| GET | `/admin/posts/studio/products` | `admin.posts.studio.products` | product search JSON |
| GET | `/admin/posts/create` | `admin.posts.create` | classic form |
| POST | `/admin/posts` | `admin.posts.store` | classic store |
| GET | `/admin/posts/{post}/studio` | `admin.posts.studio` | Studio shell |
| PUT | `/admin/posts/{post}/studio` | `admin.posts.studio.save` | JSON save |
| POST | `/admin/posts/{post}/studio/preview` | `admin.posts.studio.preview` | saves, then returns preview HTML |
| POST | `/admin/posts/{post}/studio/publish` | `admin.posts.studio.publish` | |
| POST | `/admin/posts/{post}/studio/schedule` | `admin.posts.studio.schedule` | |
| DELETE | `/admin/posts/{post}/studio/schedule` | `admin.posts.studio.schedule.cancel` | |
| POST | `/admin/posts/{post}/studio/convert` | `admin.posts.studio.convert` | |
| POST | `/admin/posts/{post}/studio/media` | `admin.posts.studio.media` | |
| GET | `/admin/posts/{post}/edit` | `admin.posts.edit` | classic editor |
| PUT | `/admin/posts/{post}` | `admin.posts.update` | |
| DELETE | `/admin/posts/{post}` | `admin.posts.destroy` | |
| GET/PUT | `/admin/journal` | `admin.journal.edit`, `admin.journal.update` | journal settings |
| resource-style | `/admin/post-categories` | `admin.post-categories.*` | |
| GET | `/admin/comments` | `admin.comments.index` | approve and delete |

There is no `/admin/posts/11/studio` mismatch. Post 11 exists and that URL opened Blog Studio. Reference post: id `11`, slug `blog-introducing-the-world-of-stone-chips-nature-timeless-artistry-240209`, `content_source=studio`, `status=published`. Local draft used for mixed blocks: id `23`, slug `untitled-article-n1oxz5`, not public.

## Verification status

| Surface | Status |
| --- | --- |
| Public journal at 320, 390, 768, 1440, 1920 | Verified in the browser. Computed layout recorded below. |
| Mobile nav open at 390 | Verified. Screenshot `journal-390x844.png` is the open menu. `journal-390x844-closed.png` is the closed journal. |
| Featured story, latest cards, search, pagination, empty search | Verified. Empty search `?q=zzzznotastory` returns “No stories match…”. Unknown `category` returns 404. |
| Topic pills beyond “All stories” | Verified empty. `post_categories` has 0 rows, so only “All stories” renders. The filter code exists. |
| The Edit, A Closer Look, Maker Feature on the public page | Verified absent. Body text does not contain those headings. Stored columns and hidden admin fields remain. |
| Public article 11: hero, breadcrumbs, date, H3 sections, images, comment form | Verified in the browser. |
| Related posts, table of contents, share buttons | Not implemented. Code-only absence. |
| Studio Write, Design, Republish dialog, mobile Insert drawer | Verified in the browser on post 11. |
| Optimize mode, Layers, Templates dialog, Schedule dialog, slash menu, autosave, undo | Verified in code and by the control labels in the accessibility tree. Not every dialog was clicked, because Preview and Save write data. |
| Image crop | Not implemented. No cropper dependency and no crop control. |
| Classic editor form | Verified in code. Entry point “Classic” is on the admin list. |
| Journal settings Cover tab | Verified in the browser. Featured story tab is in the accessibility tree. |
| Publishing a live post | Not clicked. Post 11 already has a draft; republish was left disabled by 4 blocking checks. |
| Sitemap XML body | Verified in code (`SitemapCatalog::journal`). HTTP body not fetched. |

## Public journal

### User flow

Entry: header “Blogs”, footer “Blog”, or `/blog`. `/blogs` and `/blogs?query` 301 to `/blog` with the query kept.

Landing (`page=1`, no `q`, no `category`, no `stories=1`):

1. Eyebrow, heading, italic emphasis, introduction. Current copy: “The Journal”, “Stories in”, “Indian stones.”, and the Jodhpur sandstone lede.
2. Featured story band when a published story exists. Kicker “Featured story”, optional “ / {category}”, title, summary clamped to 3 lines, “{n} min read”, button “Read the story”.
3. “Latest stories”, topic pills, search. Cards exclude the featured story. 6 per page. Card: image 41/25, kicker, title, excerpt clamped to 3 lines, uppercase minutes, “Read story”.
4. Pagination “Previous”, page numbers, “Next”.

Archive (`page>1`, `?stories=1`, a category, or a search): the featured band is omitted. Heading becomes “Stories matching “…””, the category name, or “Latest stories”. A count line appears. “Clear search”, “All stories”, or “Back to the journal” resets.

Empty site: “Stories are on the way.” with links to `/products` and `/aboutus`.  
Empty search: “No stories match “{q}”.” and “Clear search”.  
Empty category: “No stories in this topic yet.”  
Out-of-range page: redirect to the last page.  
Unknown category slug: 404. Category charset must match `^[A-Za-z0-9_-]+$`.

There is no visitor sort control. Order is `published_on` descending, then `title`. Search matches `title`, `summary`, `body`, and `body_document` with `whereLike`. Search is not cached. Unfiltered pages are cached for 5 minutes. Max search length is 120 in the input and normalized in `JournalIndex`.

### How the featured story is chosen

`journal_settings.edit_post_ids` is a JSON list. The first id that is currently published is the feature. If none qualify, the latest published story that has an existing image file is used, otherwise the latest published story. The public list excludes that id. `closer_post_ids`, `maker_*`, `edit_kicker`, and `closer_*` are stored and are not rendered by `resources/views/journal.blade.php`. The admin copy says the introduction and the featured story are what the public page shows.

The featured photograph is the post’s `image`. `journal_settings.cover_image` is the share image only when the feature has no photograph. The saved caption is not printed. The admin says so.

### Source

- `app/Services/JournalIndex.php` — `respond`, `featuredPost`, `stories`, `filtered`, `PER_PAGE = 6`
- `app/Http/Controllers/SiteController.php` — `show`, slug `blog`
- `resources/views/journal.blade.php`
- `resources/views/components/journal-card.blade.php`
- `public/assets/css/journal.css`
- `app/Models/JournalSetting.php`
- `app/Http/Controllers/Admin/JournalSettingController.php`
- `resources/views/admin/journal/edit.blade.php`
- `public/assets/js/journal-editor.js`
- Tests: `tests/Feature/JournalPageTest.php` (asserts the public page does not show “02 / A closer look”)

### Data

Query: `q` string, `category` slug, `page` integer, `stories` boolean.  
View data includes `copy`, `featured`, `featureImage`, `topics`, `posts`, `pageNumbers`, `listingSchema`, `canonical`, `title`, `description`, `shareImage`.

Public card fields: `slug`, `title`, `summary`, `image`, `featured_image_alt`, `published_on`, `reading_minutes`, category name.

### Destination steps

1. Map this to the destination’s blog index. Keep existing public URLs and redirects.
2. Add a settings record for eyebrow, heading lead, italic emphasis, introduction, featured post id, and a fallback share image.
3. Render the intro, feature, card grid, pills, search, and pager with the copied `journal.css`, scoped under `.journal-page`.
4. Fixture: one published featured post and six newer posts. Acceptance: the feature is the chosen id, the grid starts at the next post, page size is 6, and a nonsense query shows the empty sentence.

## Public article

### User flow

URL is `/{slug}`, not `/blog/{slug}`. Breadcrumb: Home / Blog / title. The page title is `{title} | Royal Indian Stones`. Cover uses `post.image` with alt set to the title in the current template (`post.blade.php` line 22), then an H1, then `<time>` formatted `j F Y` when `published_on` is set. Post 11 showed “9 February 2024”.

Body rule in `post.blade.php`:

- If `content_source` is `studio`, `body_document.type` is `doc`, and it is not the case that every block is only `paragraph` or `heading` while `body` still contains `<img`, render `RichContentRenderer`.
- Otherwise print the stored classic HTML in `body`.

Post 11’s published document is only paragraphs and headings, and `body` still contains images, so readers see the classic HTML. That is why the live article shows H3 sections and `blog240209` images even though Studio is editing a richer draft. The draft is invisible until republish.

Comments: if any approved comments exist, list name and body. Always show “Leave a comment” with Name, Email, Comment (5–2000 characters). Success and error status render above the button. There is no related-post block, TOC, or share row.

### Block render order

`resources/views/rich-content/document.blade.php` walks `body_document.content` in stored order. Missing media or unpublished products are skipped, not replaced with an error on the public page.

| Type key | Public output | Missing reference |
| --- | --- | --- |
| `paragraph` | `<p class="rich-frame …">` with bold, italic, link, `<br>` | empty paragraph still renders |
| `heading` | `<h2>` unless `attrs.level` is 3, then `<h3>` | invalid level is coerced to 2 on save |
| `image` | `<figure>` plus `<x-picture>`, optional figcaption | missing `journal_media` row is omitted |
| `gallery` | `.rich-gallery.is-grid` or `.is-carousel`, max 12 items | missing photo omitted. No per-item caption in the schema |
| `productSpotlight` | `<aside class="rich-stone is-{layout}">` with live product name, image, eyebrow, summary, CTA | unpublished or missing product omitted |
| `workshopStory` | `<section class="rich-quarry">` stage, title, body, photos | missing media omitted |
| `cta` | `<aside class="rich-cta">` and `.ris-button` only when both label and URL exist | unsafe URL becomes null and the button is omitted |

Frame classes come from `BlockFrame::classes`. Defaults: width `wide`, align `left`, ratio `4:3`, scale `editorial`, spacing `standard`, background `none`, radius `soft`, visibility `all`.

Product cards resolve at read time from `products` that are published. The document stores `productId` only. It does not store price, name, or stock.

### Source

- `app/Http/Controllers/SiteController.php` `show`, `findBySlug`, `articleSchema`
- `app/Services/RichContent/RichContentRenderer.php`
- `app/Services/RichContent/BlockFrame.php`
- `resources/views/post.blade.php`
- `resources/views/rich-content/document.blade.php`
- `app/Support/ReadingTime.php`
- `app/Support/ResponsivePicture.php`

### SEO

- `<title>`: `{post title} | Royal Indian Stones`
- Description: `meta_description`, else `summary`
- Canonical: `http://{host}/{slug}` (verified on post 11)
- `og` image: post image when present
- JSON-LD: site Organization from the layout, plus `@graph` with `BreadcrumbList` and `BlogPosting` (`headline`, `datePublished`, `dateModified`, `description`, `image`, `mainEntityOfPage`, `timeRequired` as `PT{n}M`, author and publisher Organization “Royal Indian Stones”)
- No `robots` meta on a published article
- Draft and scheduled-future posts 404, so they are not indexable
- Sitemap journal group includes `Post::published()` at `/{slug}` when the slug is canonical (`SitemapCatalog::journal`)
- Admin responses are noindex
- Case-different slug redirects 301 to the stored slug

`Post::published()` requires `is_published = true`, status `published` or null, and `scheduled_for` null or `<= now()`.

### Destination steps

Render classic HTML and the studio document with the rule above so old articles that still have images in `body` do not lose those images. Acceptance: a text-only studio document with `<img` in `body` shows the classic HTML; a document that contains an image block shows the studio renderer and omits a deleted product.

## Admin article list

Verified at `/admin/posts`.

Labels: kicker “Royal Indian Stones / Content manager”, H1 “Blog”, lead “Articles on the public journal. Studio is the editor. Classic keeps the older form.” Button “New in Studio”. Filters “Title” (`q`) and “Category”, button “Find”. Count “21 articles”. Columns Article, Category, Date, Shown, Comments, actions. Shown is a “SHOWN” chip when `is_published`. Each row links to Studio and offers Studio, Classic, View, Delete. The untitled draft has “No photo” and no View link because it is not public. Clicking the row opens Studio (`data-href`).

Search matches title and slug, escaped for `%` and `_`. Category filter is `post_category_id`. Sort is `published_on` desc, then title. The list is not paginated.

Create in Studio reuses an untouched draft titled “Untitled article” whose document is one empty paragraph. Otherwise it creates `content_source=studio`, `status=draft`, `is_published=false`, slug `untitled-article-` plus 6 random letters, and an empty document.

Classic create and edit: `PostController` validates title required max 200, slug required max 200 matching `^[A-Za-z0-9_-]+$` and unique including the current id, `published_on` nullable date, summary max 180, meta description max 2000, body required, category exists, cover jpg/png/webp max 5120 KB. `JournalHtml` strips dangerous markup. Publishing sets `status=published` and clears `scheduled_for`. Unpublishing a published row sets `status=draft`. Delete removes the row and managed files.

There is one site-admin gate. There is no per-article policy beyond `is_site_admin`.

## Blog Studio

Shell: `resources/views/admin/posts/studio.blade.php`. Bootstrap JSON is `#blog-studio-bootstrap`. Script is an IIFE, not a module. Fonts: Cormorant Garamond 600/700 and Inter 400/500/600/700. Font Awesome 6.7.2.

### Modes and steps

Top: back arrow, “Blog Studio”, modes Write / Design / Optimize, Undo, Redo, status chip, Preview, Schedule, Save or “Save draft”, Publish or “Republish”, Focus / Panels, More.

Path: Template, Build, Design, Optimize, Publish.

Readiness: SEO, Accessibility, Product references, Media, Broken links, Publish. Each says “Clear”, “{n} to review”, or “No checks yet” and is disabled when that section has no items.

Write selects the Content inspector tab and the Insert rail. Design selects Style. Optimize selects Checks and the Layers rail. Template switches the rail to Templates. Publish opens the dialog and does not change mode.

More contains Preview, Schedule, Save now, Copy public link when a public URL exists, and “Open the classic editor”.

Focus adds `.is-focus` and hides the rail, inspector, and path.

### Insert, layers, canvas, inspector

Insert rail: search “Search blocks”, hint “Enter adds the first match after the selected block.” or “at the end” when nothing is selected. Block buttons use the registry name, description, and Font Awesome class.

Layers: “{n}. {name}” plus move up, move down, duplicate, delete. End buttons are disabled.

Canvas: featured image or “Add the featured photograph”, title textarea, excerpt with live `{length} / 200`, a dock “{n} checks block publishing” or “{n} to review” or “All checks pass”, then blocks. An empty single paragraph also offers Image, Gallery, Product Spotlight, Quarry Story, and Call to Action.

Selected block toolbar: Undo, Redo, Bold, Italic, Link, Text, H2, H3, Insert, move up, move down, duplicate, delete. Bold and italic use `document.execCommand`. Link opens a dialog. H2/H3/Text change the block type. Slash: typing `/` in a text block opens `.studio-slash`. Arrow keys move, Enter inserts, Escape closes. The slash text is not stored as paragraph content.

Inspector tabs: Content, Style, Advanced, Checks.

Content fields always include URL slug, SEO title with `{length} / 60` (informational, not a publish block), meta description, tags, category, featured alt, upload or replace, remove. Then block fields.

Style fields: width, align, ratio, scale, spacing, background, radius. Product Spotlight also has layout `image-left`, `image-right`, `stacked`.

Advanced: visibility `all`, `desktop`, `tablet`, `mobile`.

Checks lists every quality item. Choosing one selects that block and opens Optimize.

### Autosave, history, shortcuts

- Debounce 1500 ms after edits, then `PUT` the save URL with `Accept: application/json`, `Content-Type: application/json`, and `X-CSRF-TOKEN`.
- Status text cycles Unsaved, Saving, Saved, and error sentences.
- A second save while one is in flight is queued.
- `localStorage` key `blog-studio-{id}` stores `{document, meta, baseVersion}`. On load, if `baseVersion` equals `lockVersion`, a banner offers Restore and Discard.
- Undo stack max 40. Redo stack is cleared on a new edit. Header Redo’s disabled state uses the undo stack length (`history.length`) for both buttons. The block toolbar uses the redo stack. Treat the block toolbar as the intended behavior: Redo is enabled only when a redo entry exists.
- Title Enter moves focus to the excerpt.
- There is no drag-and-drop reorder. Order changes are the up and down buttons.
- There is no crop UI.
- `beforeunload` is not registered. Unsaved recovery is the localStorage banner.
- Classic articles do not autosave. The shell shows “Convert to Blog Studio”, radios “Build a new Studio version” and, when a previous studio document exists, “Go back to the earlier Studio version”, plus a confirm checkbox. Live articles warn that readers will see the Studio version immediately. Conversion keeps the original `body` HTML.

### Save payload

```json
{
  "baseVersion": 1,
  "document": { "type": "doc", "schemaVersion": 1, "content": [] },
  "metadata": {
    "title": "Untitled article",
    "slug": "untitled-article-ab12cd",
    "excerpt": "",
    "metaDescription": "",
    "tags": ["sandstone"],
    "featuredImageAlt": "",
    "categoryId": null
  }
}
```

Success JSON: `lockVersion`, `status`, `isLive`, `hasDraft`, `document` (media URLs injected, not persisted), `quality`, `publicUrl`.

409 when `baseVersion` does not equal `lock_version`. Message: “This article was saved in another tab. Reload before saving over it.” The banner then offers “Load the latest version” and “Keep my version”. Keep my version adopts the server lock and saves again.

Validation errors use Laravel’s 422 shape. Document errors are on `document`. Duplicate slug: “Another article already uses that address.”

Live posts: the document and title, slug, excerpt, meta, tags, and featured alt go to `post_drafts`. The live row’s `post_category_id` is updated immediately when `categoryId` is present. Featured-image upload with `role=featured` writes `posts.image` immediately. Readers keep the previous title and body until republish. The banner “Readers still see the published article until you republish.” was visible on post 11.

### Preview, schedule, publish

Preview POSTs the same payload, saves first, then returns `{saved, html}`. The overlay title is “Preview”. Do not call it on a live article unless a draft write is intended.

Schedule dialog: “Publish at” `datetime-local`, button “Schedule”. Server rule: `scheduledFor` required, date, `after:now`. Blocked checks reject with “Resolve the blocked checks before scheduling.” Status becomes `scheduled`, `is_published=false`. Cancel asks “Cancel this schedule and return the article to draft?” and DELETEs. Status returns to `draft`.

Publish dialog title is “Publish” or “Republish”. It prints `/blogs/{slug}` (this path is wrong; the public URL is `/{slug}`), “{n} blocking checks. {n} stones linked.”, and buttons for each blocked check. “Publish now” or “Republish” is disabled while any check is blocked. On post 11 the button was disabled with “4 blocking checks. 0 stones linked.”

Publish copies the draft onto the post, sets `status=published`, `is_published=true`, `scheduled_for=null`, keeps `published_on` or sets today, increments `lock_version`, deletes the draft, and syncs `post_references`.

`publishDue()` runs on Studio create, Studio show, the public journal, and public article requests. Due scheduled studio posts publish. If checks are blocked, status becomes `schedule_failed` and the post stays unpublished. There is no queue job and no `routes/console.php` schedule for this. Timezone is `Asia/Kolkata`.

### Registered blocks

Registry: `app/Services/RichContent/BlockRegistry.php`. Schema version 1. Allowed marks: `bold`, `italic`, `link`. Process stages: `selection`, `cutting`, `finishing`, `packing`.

Every saved block receives `attrs.blockId` (`[A-Za-z0-9_-]{8,40}`, generated when missing or duplicated) and `attrs.frame` sanitized to the eight frame keys. Unknown block types are rejected and dropped. An empty document becomes one empty paragraph. `src`, `html`, `onclick`, `style`, and copied product price or name are forbidden and cause validation errors.

| Type | Group | Defaults from `blank()` | Inspector | Public template |
| --- | --- | --- | --- | --- |
| `paragraph` | Writing | `{type:paragraph, content:[]}` | text toolbar only | `document.blade.php` paragraph |
| `heading` | Writing | level 2, text “Section heading” | Level H2/H3 | h2 or h3 |
| `image` | Media | `mediaId null`, alt `""`, decorative false, caption `""`, credit `""` | alt, decorative checkbox, caption, upload/replace | figure. Credit is stored by the validator but the inspector does not edit it |
| `gallery` | Media | mode `grid`, items `[]` | mode grid/carousel, alt per item, add photograph. Decorative exists in the schema and is not in the inspector. Max 12 | `.rich-gallery` |
| `productSpotlight` | Catalogue | layout `image-left`, cta “View stone”, eyebrow “Stone”, summary `""` | Choose a stone, eyebrow, summary. Layout is on the Style tab | `.rich-stone` |
| `workshopStory` | Craft | title `""`, stage `selection`, body `""`, mediaIds `[]`, caption `""` | title, stage, story, add process photograph | `.rich-quarry`. UI label “Quarry story” |
| `cta` | Close | title, body, buttonText, buttonUrl empty | heading, support, button, link | `.rich-cta` |

Image alt max 140. Decorative and alt together are invalid. Caption max 300. Gallery alt max 140. Product eyebrow max 80, summary max 400, CTA label max 40. Workshop title max 160, body max 4000, caption max 300. CTA title max 120, body max 400, button max 40. Links must be a site path starting with `/` but not `//`, or `http`/`https`. `javascript:` and other schemes become null.

Product search: GET `admin.posts.studio.products?q=`, limit 12, published products, JSON `{products:[{id,name,category,image,url}]}`.

Media: POST multipart `file` required, `mimes:jpg,jpeg,png,webp`, max 6144 KB, optional `alt` max 180, `role` `featured` or `block`. Stored under `journal/{postId}/photo-{8 chars}.{ext}`. Responsive derivatives are generated. Response `{id,url,path,alt}`.

### Quality checks

`app/Services/RichContent/QualityChecker.php`.

| Id | Section | Blocked when |
| --- | --- | --- |
| `title` | SEO | empty or “untitled article” |
| `excerpt` | SEO | under 40 or over 200 characters (`ArticleExcerpt`) |
| `body` | Publish | no visible text and no media/product/workshop/cta block |
| `featured-alt` | Accessibility | a featured image exists and alt is empty |
| `image-{blockId}` | Media | no library id, or not decorative and alt empty |
| `gallery-{blockId}` | Media | no items, or any item missing library id or alt |
| `product-{blockId}` | Product references | product id missing or not published |
| `workshop-{blockId}` | Media | a media id is missing. Words without a photo are `attention`, not blocked |
| `cta-{blockId}` | Broken links | button label set and URL empty |

Publish and schedule require `blocked === 0`. Attention does not block.

### Templates

`app/Services/BlogStudio/ArticleTemplates.php`:

| Id | Name | Shape |
| --- | --- | --- |
| `blank` | Blank article | one empty paragraph |
| `buying-guide` | Buying guide | H2, paragraphs, H3s, spotlight, CTA to `/products` |
| `quarry-story` | Quarry story | H2, paragraph, workshop stage `selection`, H3, paragraph, gallery |
| `stone-story` | Stone story | H2, paragraph, H3, paragraph, image, spotlight |
| `care-guide` | Care guide | H2, paragraphs, H3s, CTA |
| `room-guide` | Room guide | H2, paragraphs, H3s, gallery, spotlight layout `image-left`, CTA |

Choosing a template opens a dialog with the description and an outline. Actions: “Append” and “Replace article”.

### Studio responsive behavior

From `blog-studio.css`, verified at 1440 and 390:

- At `min-width: 1181px` the Insert and Inspector header buttons are hidden. Rail and inspector are columns. Canvas scrolls (`.studio-canvas`).
- At `max-width: 1180px` rail and inspector are `position:fixed` drawers, width `min(22rem, 88vw)`, z-index 35. Backdrop is `rgba(38, 32, 28, 0.45)`, z-index 34. Header Insert and Inspector toggles show. Verified: opening Insert at 390 covered the left side and exposed Close, search, and all seven blocks.
- At `max-width: 1400px` the header Schedule button is hidden. It remains under More.
- At `max-width: 720px` header Undo, Redo, and Preview are hidden.
- At `max-width: 640px` title is `2rem`, canvas padding shrinks, tabs scroll horizontally.
- At `max-height: 560px` the shell stops using a fixed viewport height and the top bar is sticky.
- Focus outline: `2px solid #1A4D3E`, offset 2px.
- Reduced motion disables rail, inspector, banner, and slash transitions.

Studio tokens in `:root` of `blog-studio.css`: brand Cormorant Garamond / Georgia / serif; sans Inter / ui-sans-serif / system-ui / sans-serif; primary `#1A4D3E`; primary hover `#2B5E4B`; secondary `#F5F3F0`; accent `#D4A843`; warm border `#EADFCF`; text `#262626`; muted `#5c5c5c`; error `#b91c1c` on `#fef2f2`; warning `#92400e` on `#fffbeb`; info `#1A4D3E` on `#f0f7f4`; success wash `#ecfdf3`; radius `0.85rem`.

## Journal settings admin

Verified at `/admin/journal#cover`.

Visible tabs: “01 Cover” and “02 Featured story”. Lead: “The introduction and the featured story are what the public journal shows. Both save together.” Status “Existing content loaded”. Actions: Preview, “View public journal”, “Save Journal Page”.

Cover fields: Eyebrow, Heading, Italic line, Introduction, Photograph caption (saved, not printed), Photograph description, file “Replace the cover photograph”. Help text says the story photograph comes from the featured article and the share image is only the fallback. JPG, PNG, or WebP. The chosen file is kept while switching tabs and is stored on save. Discard drops the unsaved file.

Featured story: one select stored as `edit_post_ids[0]`. Blank means “latest published story that has a photograph”, not id 0 and not the word “latest”. The picker is “Select a published story” with search, publish state, and sort Newest / Title. The side preview shows up to 3 latest stories and says the public page lists 6 per page.

`closer_*` and `maker_*` columns still exist and the form still posts some of those fields from hidden inputs in `admin/journal/edit.blade.php`. They are not separate visible tabs in the current editor, and the public template does not render them. Do not invent public sections for them.

## Categories and comments

`/admin/post-categories` creates, updates, and deletes `post_categories` (`name`, `slug`). The public journal lists every category as a pill linking to `/blog?category={slug}`. With zero categories, only “All stories” shows. A category with no published posts yields the empty topic state, not a 404, once the slug exists.

`/admin/comments` lists comments. Approve sets `approved`. Delete removes the row. The public article only prints approved comments. The store endpoint requires name max 120, email, and body 5–2000, and is throttled.

## Data model

### `posts`

Important columns: `id`, `slug` unique, `title`, `meta_description`, `published_on` date, `image`, `summary`, `body` longtext classic HTML, `post_category_id` nullable FK, `is_published` boolean, `body_document` json nullable, `content_source` string default `classic`, `lock_version` unsigned int default 1, `status` string default `published`, `scheduled_for` timestamp nullable, `tags` json nullable, `featured_image_alt`, `reading_minutes`.

Statuses used by code: `draft`, `published`, `scheduled`, `schedule_failed`. A published classic row can have `status` null and still match `scopePublished`.

### `post_drafts`

One row per post (`post_id` unique). Columns: `document`, `title`, `slug`, `excerpt`, `meta_description`, `tags`, `featured_image_alt`, `image`.

### `journal_media`

`path`, `alt`, `width`, `height`.

### `post_references`

`post_id`, `kind` (`product` or `media`), `reference_id`, `block_id`. Rebuilt on save and publish. A reference is skipped when `blockId` is empty.

### `journal_settings`

Single current row. Visible copy fields plus `edit_post_ids` json, `cover_image`, `cover_image_alt`, `caption`. Legacy `closer_post_ids`, `closer_kicker`, `closer_heading`, `maker_post_id`, `maker_eyebrow`, `maker_heading`, `maker_body`, `maker_label`, `maker_path`, `maker_image`, `maker_image_alt`.

### `post_categories`

`name`, `slug`.

### `comments`

`post_id`, `name`, `email`, `body`, `approved`.

### Fixtures

Empty draft, after create:

```json
{
  "title": "Untitled article",
  "slug": "untitled-article-ab12cd",
  "summary": "",
  "status": "draft",
  "is_published": false,
  "content_source": "studio",
  "body_document": {
    "type": "doc",
    "schemaVersion": 1,
    "content": [
      {
        "type": "paragraph",
        "attrs": {
          "blockId": "abcdefgh1234",
          "frame": {
            "width": "wide",
            "align": "left",
            "ratio": "4:3",
            "scale": "editorial",
            "spacing": "standard",
            "background": "none",
            "radius": "soft",
            "visibility": "all"
          }
        },
        "content": []
      }
    ]
  }
}
```

Published text article: `status=published`, `is_published=true`, `published_on` a date, `content_source` either `classic` with HTML in `body`, or `studio` with heading and paragraph blocks. Public URL `/{slug}`.

Mixed studio document, in this order, matching local draft 23: `paragraph`, `heading`, `image`, `gallery`, `productSpotlight`, `workshopStory`, `cta`. Keep it unpublished until checks pass. Public acceptance is a preview render, not a live URL, until the destination explicitly publishes a disposable fixture.

## Visual specification

Journal page scope is `body.journal-page` and `.journal`. Tokens in `journal.css` fall back when the site shell is absent:

| Token | Value |
| --- | --- |
| `--journal-band` / `--ris-footer` | `#1c1916` |
| `--journal-on-band` | `#f6f1ea` |
| `--journal-on-band-soft` | `#f0e2d4` |
| `--journal-accent` / `--ris-accent` | `#bd4c2c` |
| `--journal-accent-hover` | `#9d3d22` |
| `--journal-ink` | `#202520` |
| `--journal-muted` | `#6a645c` |
| `--journal-line` | `#e4ddd4` |
| `--journal-paper` | `#f7f4ef` |
| `--ris-radius` | `12px` |
| Serif | Fraunces, `"Times New Roman", serif`. Studio uses Cormorant Garamond |
| Text | `"Source Sans 3", "Segoe UI", sans-serif` |
| Logo | Poppins 500, 22px, tracking `0.16em`, uppercase |
| Ease | `cubic-bezier(0.22, 1, 0.36, 1)` |

Site font stylesheet: Google `Fraunces` opsz 9–144 weights 500 and 600 italic 500, `Open Sans` 400 and 600, `Poppins` 300 and 400, `Source Sans 3` 400, 500, 600. Also loaded: Bootstrap, Bootstrap Icons, Boxicons, `assets/css/style.css?v=2`, `assets/css/improvements.css?v=93`.

Measured journal layout:

| Viewport | Intro | Feature columns | Card grid | H1 |
| --- | --- | --- | --- | --- |
| 1920×1080 | row, lede fixed 34rem | `1088px 816px` | 3 × `488px` | `53.6px` |
| 1440×900 | row | `814px 611px`, min-height 384px | 3 columns | `48.24px`, weight 500, line-height `52.1px`, color `rgb(32, 37, 32)` |
| 768×1024 | block | 1 × `768px` | 2 × `330px` | `34.4px`, `white-space: normal` |
| 390×844 | block, hamburger | stacked, image then copy | 1 column | wraps |
| 320×568 (measured inner width 331) | block | 1 × `320px` | 1 × `296px` | `34.4px` |

Breakpoints in `journal.css`: cards 2-up at `min-width: 720px`, 3-up at `min-width: 1100px`, intro and feature stack at `max-width: 860px`. Feature image hover scale `1.045` over `0.7s`. Card hover shadow `0 16px 32px rgba(28, 25, 22, 0.12)`. Focus outline `3px solid` accent, offset 3px. Search field min-height 48px, radius 6px. Pills radius 999px, min-height 44px. Current pill background `#1c1916`, text `#f6f1ea`. Feature button radius 6px, min-height 44px, background `#bd4c2c`. Card image ratio `41 / 25`, radius 12px. Below 860px the feature media ratio is `16 / 10`.

Header is 88px, white, shadow `0 2px 12px #2b23200d`. Desktop nav is 13px. The hamburger replaces it below the site header breakpoint (still a hamburger at 768).

Public scripts are the site bundle (`assets/js` plus vendor bootstrap, aos, glightbox, swiper). Journal pages add only `journal.css`. They do not add `blog.js` behavior for filtering. Search is a GET form.

## Source defects to preserve or correct

Record these. The destination should reproduce reader-visible behavior and may correct the labeled defects if the prompt’s “intended behavior” note is followed.

1. Republish dialog prints `/blogs/{slug}`. The live URL is `/{slug}`. Intended: show the real public path.
2. Header Redo uses the undo-stack length, so it stays disabled together with Undo. Intended: enable Redo only when the redo stack has entries.
3. A live save writes `post_category_id`, and a featured upload writes `posts.image`, before republish. Intended: those fields should ride in the draft until republish if the destination can do that without breaking classic posts.
4. Text-only studio documents fall back to classic HTML when `body` contains `<img`. Intended: keep that fallback so converted articles do not lose photographs.
5. SEO title shows `{n} / 60` and does not block publish when the title is longer. Post 11 showed `64 / 60` while SEO was “Clear”.
6. Image `credit` and gallery `decorative` are in the schema and not in the inspector.
7. There is no crop, no drag reorder, and no TOC, related posts, or share buttons.
8. `closer_*` and `maker_*` are stored and are not public sections.
9. Scheduling depends on the next public or studio request calling `publishDue()`. There is no cron entry.

## Screenshot index

| File | Viewport | What is open |
| --- | --- | --- |
| `docs/blog-handoff/screenshots/journal-1920x1080.png` | 1920×1080 | Journal landing, 3-column grid starting |
| `docs/blog-handoff/screenshots/journal-1440x900.png` | 1440×900 | Journal hero and feature |
| `docs/blog-handoff/screenshots/journal-768x1024.png` | 768×1024 | Stacked feature, hamburger |
| `docs/blog-handoff/screenshots/journal-390x844-closed.png` | 390×844 | Closed mobile journal |
| `docs/blog-handoff/screenshots/journal-390x844.png` | 390×844 | Mobile nav open |
| `docs/blog-handoff/screenshots/journal-320x568.png` | 320×568 | Narrow journal |
| `docs/blog-handoff/screenshots/article-1440x900.png` | 1440×900 | Post 11 classic-rendered article |
| `docs/blog-handoff/screenshots/admin-posts-1440.png` | 1440×900 | Admin Blog list |
| `docs/blog-handoff/screenshots/admin-journal-1440.png` | 1440×900 | Journal Cover editor |
| `docs/blog-handoff/screenshots/studio-post11-1440-write.png` | 1440×900 | Studio Write |
| `docs/blog-handoff/screenshots/studio-post11-1440-design.png` | 1440×900 | Studio Design, Style inspector |
| `docs/blog-handoff/screenshots/studio-post11-republish-dialog.png` | 1440×900 | Republish dialog |
| `docs/blog-handoff/screenshots/studio-390-insert-drawer.png` | 390×844 | Insert drawer open |

## Acceptance checklist

- `/blogs` 301s to `/blog`. A published story is at `/{slug}`. A draft slug 404s.
- Landing shows the intro, one feature, then other stories, 6 per page, newest first, feature excluded.
- Search, unknown category 404, empty search copy, and empty site copy match this spec.
- Studio document with an image block renders that block. Text-only studio document whose classic `body` contains an image renders the classic HTML.
- A missing product id omits the spotlight.
- Save returns a new `lockVersion`. A stale `baseVersion` returns 409.
- Reload offers the localStorage recovery banner when the base version still matches.
- Publish stays disabled while a check is blocked. After a clean publish, the public page shows the new title without a second save.
- A scheduled time in the past is rejected. A future time stays hidden until `publishDue()`.
- Screenshots at 320, 390, 768, 1440, and 1920 match the column counts in the table above.
- Generated title, description, canonical, and `BlogPosting` JSON-LD match the rules above.
