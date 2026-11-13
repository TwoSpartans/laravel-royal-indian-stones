# Implement the Royal Indian Stones Journal and Blog Studio

You are implementing the public Journal, the public article, the admin article list, and Blog Studio in this repository. The reference site is Royal Indian Stones. You do not have that repository. Use this prompt, `BLOG_REPLICATION_SPEC.md`, and the files under `docs/blog-handoff/` as the source of truth. Read the spec before editing. The copied CSS, JavaScript, and Blade files are the portable implementation, not suggestions.

Do not invent The Edit, A Closer Look, or a Maker Feature on the public page. Those names exist only as unused stored columns. The public page is an introduction, one featured story, latest-story cards, topic pills, search, and pagination.

## 1. Inspect this repository first

Before creating files, find the destination’s blog or journal routes, page templates, admin, auth, media library, product or catalogue models, styles, scripts, and build pipeline. Map each source feature onto an existing route, component, or column when one already does the job.

If a blog already exists:

- Keep published URLs, slugs, media paths, and redirects.
- Add Studio as an editor on the existing post record. Do not create a second public blog.
- Classic HTML bodies remain readable.
- Reuse this app’s authentication, authorization, file storage, image pipeline, and asset build. Do not add Tiptap, React, Alpine, or Cropper. The reference editor is the vanilla script `docs/blog-handoff/source/js/blog-studio.js`.

Write down the mapping of source route to destination route before coding. Where the destination already uses `/blog/{slug}` or `/blogs/{slug}`, keep that public shape and adapt only the Studio dialog text. On the reference site the public article is `/{slug}` and `/blogs` 301s to `/blog`.

## 2. Implement in this order

### Data and routes

Add only the missing columns and tables. Prefer extending the existing post model.

Posts need: slug unique, title, excerpt or summary, classic HTML body, studio JSON document, schema version, lock version, content source (`classic` or `studio`), status (`draft`, `published`, `scheduled`, `schedule_failed`), published flag, published date, scheduled datetime, featured image path, featured image alt, meta description, tags JSON, category id, reading minutes.

Also add: one draft row per post, a media table (`path`, `alt`, `width`, `height`), a reference table (`post`, `kind` of `product` or `media`, reference id, block id), categories, comments with an approved flag, and one journal-settings row (eyebrow, heading lead, italic emphasis, introduction, caption, cover image, cover alt, featured post id list).

`closer_*` and `maker_*` columns are legacy. You may store them, but do not render them publicly.

Document shape:

```json
{
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
      "content": [{ "type": "text", "text": "First sentence." }]
    }
  ]
}
```

Allowed blocks: `paragraph`, `heading`, `image`, `gallery`, `productSpotlight`, `workshopStory`, `cta`. Allowed marks: `bold`, `italic`, `link`. Headings are level 2 or 3. Frame enums: width `text|wide|full`; align `left|center|right`; ratio `4:3|1:1|16:9|3:2`; scale `editorial|standard|compact`; spacing `compact|standard|relaxed`; background `none|ivory|forest|sand|gold`; radius `none|soft|round`; visibility `all|desktop|tablet|mobile`.

Block defaults when inserted:

- heading: level 2, text “Section heading”
- image: `mediaId` null, alt empty, decorative false, caption empty
- gallery: mode `grid`, items empty, maximum 12
- productSpotlight: layout `image-left`, button “View stone”, eyebrow “Stone”, summary empty. Store only the destination product id
- workshopStory: stage `selection` (also `cutting`, `finishing`, `packing`), empty title, body, and media ids
- cta: empty title, body, button label, and URL

Reject `src`, inline HTML, event handlers, and copied product price or name. Links must be a same-site path starting with a single `/`, or `http`/`https`. Give every block a `blockId` of 8–40 URL-safe characters. An empty document is one empty paragraph.

A reversible migration adds nullable columns and new tables. Do not rewrite existing bodies. `content_source` defaults to `classic`.

Routes to provide, named to match this app’s style:

- Public index and article, plus a 301 from any legacy blog index you already expose
- Comment POST, throttled
- Admin list, classic create and edit, Studio create, Studio show, JSON save, preview, publish, schedule, cancel schedule, convert, media upload, product search
- Journal settings edit and update
- Category and comment moderation if those screens do not already exist

Admin routes use this app’s existing admin auth. The reference uses a single `is_site_admin` flag and no per-post policy.

### Public pages

Build the index from `docs/blog-handoff/source/views/journal.blade.php` and `journal-card.blade.php`, and the article from `post.blade.php` plus `document.blade.php`. Load `docs/blog-handoff/source/css/journal.css` only on journal and article pages. Scope it so it does not restyle the rest of the site. If the destination has no Fraunces or Source Sans 3, load them from the same Google CSS URL the spec lists, with the fallbacks `Times New Roman` and `Segoe UI`.

Index behavior:

- Page size 6. Order by published date descending, then title.
- Featured story is the first published id in the settings list. If none, use the latest published story that has an image file, else the latest published story.
- The card list excludes the feature on page 1 of an unfiltered landing.
- Search matches title, excerpt, classic body, and the studio JSON. Keep the category filter in the query string.
- Empty, search-miss, and unknown-category behavior is in the spec. Copy those sentences.
- Do not add a visitor sort control.

Article behavior:

- If the studio document is only paragraphs and headings and the classic body still contains `<img`, render the classic HTML. This is required. Post 11 on the reference site depends on it.
- Otherwise render studio blocks in stored order. Skip a missing image or an unpublished product. Do not show an error box.
- Product spotlights read the live catalogue name, image, and URL at request time.
- Show the date as `j F Y`, reading time in minutes, breadcrumb Home / Blog / title, and the comment form. Do not add related posts, a table of contents, or share buttons.
- Drafts and posts scheduled in the future return 404.

### Admin and Studio

The article list matches `docs/blog-handoff/screenshots/admin-posts-1440.png`: title search, category filter, “New in Studio”, columns Article, Category, Date, Shown, Comments, and row actions Studio, Classic, View, Delete.

Blog Studio is `docs/blog-handoff/source/js/blog-studio.js` and `docs/blog-handoff/source/css/blog-studio.css`. Mount it on an empty `#blog-studio` node with a JSON bootstrap script `#blog-studio-bootstrap` and a csrf meta tag. Serve the script as a static asset or through the existing build, but do not rewrite it into a framework unless a destination constraint makes that impossible. If you must adapt it, keep the labels, debounce, and payload below.

Bootstrap includes the post, the editing document (the draft when the post is live), quality report, block registry, the six templates from the spec, frame option lists, process stages, categories, excerpt limits 40 and 200, and the route URLs.

Editor behavior to keep:

- Modes Write, Design, Optimize. Steps Template, Build, Design, Optimize, Publish.
- Insert, Layers, Templates in the left rail. Search filters by block name and description. Enter inserts the first match after the selection.
- Canvas title, excerpt counter, featured image, block toolbar, slash menu (`/`, arrows, Enter, Escape).
- Inspector Content, Style, Advanced, Checks.
- Undo and redo, 40 levels. Redo is enabled only when a redo entry exists. The reference header button wrongly uses the undo-stack length. Implement the intended behavior.
- Autosave 1500 ms after an edit. `PUT` JSON `{baseVersion, document, metadata}`. Metadata fields: title, slug, excerpt, metaDescription, tags array, featuredImageAlt, categoryId.
- `localStorage` key `blog-studio-{id}`. If the stored base version still equals the server lock, show Restore and Discard after reload.
- Stale `baseVersion` returns HTTP 409. The banner offers reload and “Keep my version”, which adopts the server lock and saves again.
- Preview saves, then shows returned HTML in a dialog. Do not preview against a production post.
- Publish and schedule are refused while any quality item is `blocked`. The dialog must show the real public path, not the reference bug `/blogs/{slug}`.
- A live article’s title, body, excerpt, meta, tags, alt, and featured image stay on the draft until republish. The reference currently writes category and featured image onto the live row early. Keep those on the draft too.
- Schedule requires a datetime after now in the application timezone. Cancel returns the post to draft. Run the due-publisher on public reads and on Studio open, and also register a scheduler command if this app already has a scheduler.
- Classic-to-studio conversion keeps the original HTML and asks for confirmation. A live conversion is visible immediately only after the editor confirms that readers will see it.
- Upload JPG, PNG, or WebP, maximum 6 MB. No crop tool.

Quality rules: title not empty and not “untitled article”; excerpt 40–200 characters; some visible body; featured alt when a featured image exists; every image and gallery item has a library id and alt or is decorative; spotlight product is published; quarry photo ids exist; a CTA with a label must have a safe URL. A quarry story with words and no photo is attention, not a block.

Journal settings match `docs/blog-handoff/screenshots/admin-journal-1440.png`: Cover and Featured story only. The cover image is the share fallback. The featured story’s own image is the public feature. The caption is saved and not printed. Blank featured choice means “latest published story with a photograph”.

### Styles

Match the measured layout:

| Width | Feature | Cards |
| --- | --- | --- |
| 1920 and 1440 | two columns, about 1.2fr and 0.9fr, min-height 24rem | 3 columns |
| 768 | one column, image ratio 16/10 | 2 columns |
| 390 and 320 | one column, hamburger site nav | 1 column |

Journal breakpoints in the CSS are 720px (2 cards), 860px (stack the intro and feature), and 1100px (3 cards). Colors: paper `#f7f4ef`, ink `#202520`, muted `#6a645c`, accent `#bd4c2c`, accent hover `#9d3d22`, band `#1c1916`, band text `#f6f1ea`, line `#e4ddd4`, radius 12px on cards and 6px on buttons and the search field. Heading font Fraunces 500. Body Source Sans 3. Card image ratio 41/25.

Studio breakpoints: side-by-side panels from 1181px; fixed drawers and a dim backdrop at 1180px and below; hide header Schedule at 1400px; hide header Undo, Redo, and Preview at 720px. Studio colors are in the top of `blog-studio.css`: green `#1A4D3E`, paper `#F5F3F0`, gold `#D4A843`.

### Publishing and SEO

Public visibility: published flag true, status `published` or null, and no future `scheduled_for`.

Title `{article} | {site name}`. Description is the meta description, else the excerpt. Canonical is the public article URL. JSON-LD is a `BlogPosting` plus a breadcrumb list, with `timeRequired` of `PT{minutes}M` and the organization as author and publisher. Published posts enter the existing sitemap. Drafts and previews do not. Admin pages stay noindex.

## 3. Match the reference, and record real differences

Use the screenshots in `docs/blog-handoff/screenshots/` as the visual target. Scope blog CSS to the journal and to the Studio document. Keep the destination’s header, footer, and admin chrome, but match the journal and Studio regions.

Correct the reference defects listed in the spec: public path in the publish dialog, redo enabled state, and draft isolation for category and featured image. Keep the classic-HTML fallback for text-only studio documents.

If branding, product names, or the public URL prefix must differ, list those differences in the implementation notes. Do not silently restyle the journal.

## 4. Verify every feature

Create disposable local posts. Do not republish existing production articles.

Fixtures:

- An empty studio draft titled “Untitled article” with one empty paragraph.
- A published classic or text-only article whose body HTML contains an image.
- An unpublished studio article with paragraph, heading, image, gallery, product spotlight, quarry story, and call to action, using real destination media and product ids.

Check:

- Draft 404s. After publish, the public URL shows the new title and the studio blocks. Unpublish or delete the fixture when finished.
- The text-only-plus-classic-image fixture shows the classic HTML, not a text-only studio render.
- A spotlight whose product is unpublished disappears.
- Save, reload, and confirm the draft is still there. Change the document in another request so the lock conflicts and the 409 banner appears.
- Empty journal, empty search, and a missing category slug.
- Keyboard: slash menu arrows and Escape; title Enter focuses the excerpt. Touch: at 390px the Insert drawer opens over a backdrop and Close dismisses it.
- SEO: view source for title, description, canonical, and `BlogPosting`.
- Screenshots at 320×568, 390×844, 768×1024, 1440×900, and 1920×1080 CSS pixels, plus the 720, 860, 1100, and 1180 boundaries. Capture the mobile nav, Insert drawer, Design inspector, and publish dialog while open.
- Run the destination’s relevant tests and production build. Fix mismatches and repeat the affected check.

Read `BLOG_REPLICATION_SPEC.md` for the route table, validation limits, quality ids, template outlines, and the screenshot index when a detail above is not enough.
