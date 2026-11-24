# Blog and Blog Studio

This document explains how articles are written, saved, published, and shown. The first half is the path from a blank draft to a public page. The rest is the admin side: the blog list, Blog Studio, the classic editor, categories, comments, and the journal page.

The public journal is `/blog`. Each article is a row in `posts` and, once published, is served at `/{slug}`. `/blogs` redirects to `/blog`.

Blog Studio is the only editor. **New article** on the blog list opens it. The article body is a JSON document in `posts.body_document`, and `content_source` is `studio`.

Older articles that were still classic HTML are migrated by `php artisan blogs:migrate-classic`, or automatically the first time an admin opens them. The original HTML is kept in `classic_body_backup`. The steps and the rollback are in [blog-migration.md](blog-migration.md).

The classic form is no longer linked. `/admin/posts/create` and `/admin/posts/{id}/edit` redirect into Studio.

Every admin blog screen requires a signed-in site admin (`auth` and `site.admin`). Those pages send `noindex`. A staff portal account does not enter this area unless that admin flag is set.

## The path from a blank article to the public site

1. An admin opens **Blog** and chooses **New article**.
2. The server reuses an untouched “Untitled article” draft when one exists. Otherwise it creates a draft and opens Blog Studio.
3. The admin writes the title and excerpt, adds blocks, and uploads photographs. Studio autosaves.
4. **Publish** runs a quality check. A blocked check stops the publish. A clean article becomes public at `/{slug}`.
5. `/blog` lists it. The home page can show it among the latest stories. The sitemap includes it. Readers can leave a comment, which stays hidden until an admin approves it.

A scheduled article stays hidden until its time. The command `blogs:publish-scheduled` runs every five minutes. Opening `/blog`, opening an article URL, or opening Studio also publishes anything already due.

## Where the admin work lives

The admin navigation group **Content** has four blog screens.

| Screen | Address | What it is for |
| --- | --- | --- |
| Blog | `/admin/posts` | Every article. Edit opens Studio. View opens the public page. Delete removes the article and its library photographs. |
| Journal page | `/admin/journal` | The lead story on `/blog`, plus cover wording stored with the journal. |
| Categories | `/admin/post-categories` | Topic names used by articles and by the public category filter. |
| Comments | `/admin/comments` | Approve or delete reader comments. |

The dashboard links to the blog list and shows recent articles. The header bell links to comments that are still waiting.

## The blog list

`/admin/posts` is the catalogue of every article, published or not. The page title is **Blog**. Every row opens in Blog Studio.

**New in Studio** is the only create button. It does not open a blank form on this page. It goes to `/admin/posts/studio/create`, which creates or reopens a draft and then redirects into Studio.

### Filters

The filter form is a GET to the same page.

- **Title** matches the title or the slug.
- **Category** limits the list to one `post_categories` id.
- **Status** is Draft, Scheduled, Schedule failed, or Published. Published uses the same public rule as the site: shown, status published or empty, and not waiting on a future schedule. Draft, Scheduled, and Schedule failed match `status` exactly.

The count under the filters is the number of rows after those filters. The table is not paginated.

### Each row

Columns are Article, Category, Date, Shown, and Comments.

- **Article** is the cover thumbnail, or “No photo”, plus the title. The cover is shown only when the file exists under `public/`.
- **Category** is the category name, or “None”.
- **Date** is `published_on`, formatted like `4 Nov 2026`, or “No date”.
- **Shown** is **Scheduled** when `status` is `scheduled`. Otherwise it is **Shown** or **Hidden** from `is_published`.
- **Comments** is the total comment count, approved and pending together.

Clicking the row, or pressing Enter on it, opens Studio. The action links do not follow that click.

Row actions:

- **Open Studio** goes to `/admin/posts/{id}/studio`. A classic article opens the conversion screen there, not the writing canvas.
- **Classic** goes to `/admin/posts/{id}/edit`.
- **View** appears only when the article is shown. It opens `/{slug}` in a new tab.
- **Delete** asks for confirmation, then `PostController::destroy` deletes the row and the cover and inline files the classic editor stored for it. The database cascade removes the Studio draft and the block references.

Order is newest `published_on` first, then title.

## Creating an article in Studio

`BlogStudioController::create` does three things before the editor appears.

1. It publishes any Studio article whose schedule has already passed.
2. It looks for the newest Studio draft titled **Untitled article**. If that draft is still untouched, the browser is sent back to it. Untouched means an empty excerpt, no cover, no category, no tags, and a document that is one empty paragraph. This stops a pile of empty drafts when **New in Studio** is clicked more than once.
3. If no untouched draft exists, it inserts one.

The new row is:

| Field | Starting value |
| --- | --- |
| Title | Untitled article |
| Slug | `untitled-article`, or `untitled-article-2`, `untitled-article-3`, and so on |
| Excerpt | empty |
| HTML body | empty |
| `content_source` | `studio` |
| `status` | `draft` |
| `is_published` | false |
| `body_document` | one empty paragraph, schema version 1 |
| `lock_version` | 1 |

The browser is then redirected to `/admin/posts/{id}/studio`.

## How Studio boots

The Studio page is a full-window shell, `resources/views/admin/posts/studio.blade.php`. It does not use the normal admin layout. Laravel prints one JSON object into `#blog-studio-bootstrap`. Vite loads `resources/js/blog-studio/main.tsx`, and React mounts on `#blog-studio-root`.

That JSON is the whole starting state: the article, the document, the latest quality report, known photographs, categories, the block catalogue, the five templates, frame options, excerpt rules, the display timezone, permissions, and the URLs Studio will call. The CSRF token travels in the same object.

Permissions for a site admin are fixed in that payload: publish, schedule, unpublish, and convert are on. Submit for review and JSON export are off. There is no separate author role inside the blog. The “Submit for review” button exists in the editor code and stays hidden because `submitForReview` is false.

If `content_source` is still `classic`, React renders the conversion screen and does not mount the writing canvas. That screen is described later.

Studio needs the Vite build (`npm run build`) or a running `npm run dev`. The classic form does not. It loads `public/assets/js/journal-studio.js` by itself.

## The Studio screen

The screen is a top bar, a left rail, the writing canvas, and a right inspector. Below 1024px the rail and the inspector become drawers, opened with **Blocks** and **Settings**. **Focus mode**, in the more menu, hides both side panels.

### Top bar

From left to right:

- **Back** returns to the blog list.
- **Write**, **Design**, and **Optimize** switch the working mode. The document is the same in all three.
- A status chip: **Draft**, **Scheduled** with the time and timezone, **Schedule needs attention** after a failed schedule, or **Live**, which opens the public article.
- A save label: Saved, Draft saved, Unsaved changes, Saving…, Offline, Conflict, or Save failed. On a live article the label says **Draft saved** once a revision exists.
- Undo and redo.
- **Preview**.
- **Schedule**.
- **Save**, or **Save draft** when the article is already live.
- **Publish…**, **Schedule…** when a time is already set, or **Republish** when the article is live.

The more menu has **Save now**, **Copy public link**, **Focus mode**, and **Open the classic editor**.

### Write mode

Write mode is where the article is composed.

The canvas is a Tiptap editor. The title and the excerpt sit with the article, outside the block document. The title field is the only H1 readers will see. Body headings are H2 and H3.

The formatting toolbar can:

- undo and redo
- turn the current block into text, H2, or H3
- toggle bold and italic
- add or remove a link, after text is selected
- insert a block
- move the current block up or down
- duplicate or delete the current block

A link must be a site path such as `/products`, or a full `http://` or `https://` address. An empty link field removes the link.

Typing `/` opens the slash menu. It lists the same blocks as the insert rail, filtered as the admin types. Choosing one inserts it at the cursor.

The left rail has three tabs.

**Insert** lists every block, grouped. The groups are Writing, Media, Commerce, Craft, and Conversion. A search box filters them. Inserting a heading starts it as “Section heading”. A gallery starts empty, in grid mode. A text-and-image block starts with the photograph on the right.

**Layers** is the outline. Headings and blocks appear in document order. Choosing one scrolls to it and selects it. The outline also counts product blocks. An empty document says the layers fill in as the admin writes.

**Templates** replaces the document with a starter. Studio asks before replacing work that is already there.

| Template | What it inserts |
| --- | --- |
| Blank Article | One empty paragraph |
| Buying Guide | An H2, a paragraph, a product spotlight, another H2, and a call to action |
| Workshop Story | An H2, a paragraph, and a quarry story |
| Product Story | An H2, a paragraph, and a product spotlight |
| Care Guide | An H2, a paragraph, an H3, a paragraph, and a call to action |

### The eight blocks

`BlockRegistry` is the catalogue. The server refuses any other block type on save.

| Block | What the admin edits | What readers get |
| --- | --- | --- |
| Paragraph | Body text, with bold, italic, and links | A paragraph |
| Heading | H2 or H3 | An `h2` or `h3`. An H1 in the body is rejected |
| Image | A library photograph, alt text or “decorative”, caption, credit | A figure. The file is loaded from `journal_media` by id |
| Text and image | A heading, paragraphs, a photograph, alt text, caption, and image on the left or the right | Copy beside the photograph |
| Gallery | Up to 12 photographs, each with its own alt text, shown as a grid or a carousel | Those pictures |
| Product spotlight | A published stone, layout, button label, eyebrow, and a short summary | A live catalogue card. The name, photo, and URL are read from the product when the page is rendered |
| Quarry story | Title, process stage, body, and photographs | A workshop section. Stages are selection, seasoning, joinery, carving, finishing, and assembly |
| Call to action | Title, supporting line, button label, and one link | An aside with that link |

Product spotlights store the product id. They do not store a copied name, price, or image address. If the stone is later unpublished, the public card says it is not available.

Decorative photographs are skipped by screen readers. Alt text and the decorative flag cannot both be set. The server clears one of them if a save tries to send both.

### Inspector

The right side has four tabs: **Content**, **Style**, **Advanced**, and **Checks**. Checks shows a count when something is blocking publish.

**Content**, with no block selected, asks the admin to place the cursor in a block or pick one from the outline. With a block selected, this tab is that block’s fields: the text style for a paragraph or heading, the photograph and alt text for an image, the stone picker for a product, the stage and photos for a quarry story, and so on.

**Style** edits the frame on the selected block. The same allowlist is used in the editor and on the public page. Unknown values fall back to the defaults. Raw CSS is not stored.

| Setting | Choices | Default |
| --- | --- | --- |
| Width | Text, wide, full | Wide |
| Alignment | Left, center, right | Left |
| Ratio | 4:3, 1:1, 16:9, 3:2 | 4:3 |
| Scale | Editorial, standard, compact | Editorial |
| Spacing | Compact, standard, relaxed | Standard |
| Background | None, ivory, forest, sand, gold | None |
| Radius | None, soft, round | Soft |
| Visibility | All, desktop, tablet, mobile | All |

**Advanced** is the article record, not the selected block:

- **URL slug**, with the public path shown beside it
- **SEO title**, 60 characters, falling back to the article title on the public page
- **Meta description**, 160 characters, falling back to the excerpt
- **Tags**, separated by commas
- **Categories**, one checkbox per category
- **Featured image**, upload, replace, or remove, plus alt text

The category list can show more than one tick. The server stores a single category: the first selected id. The public filter and the related-posts query use that one category.

Tags are trimmed, lowercased, cut at 40 characters, and stored unique.

**Checks** is the same quality report as Optimize. Each row can jump to the field or block that caused it. The report is refreshed from the server after every successful save. Until that response arrives, the panel can mark the list as stale.

### Design mode

Design mode selects a block and opens its Style tab. It is the same frame data as the inspector. Moving between Write and Design does not copy or convert the document.

### Optimize mode

Optimize is the publishing checklist for the whole article. Each item is ready, needs attention, or blocked.

Blocked items stop publish and schedule:

- The title is empty or still “Untitled article”.
- The excerpt is outside 160–200 characters, after tags are stripped.
- The body has no visible text and no image, split, gallery, product, quarry, or call-to-action block.
- A body heading is H1.
- A featured image exists and its alt text is empty.
- An image or text-and-image block has no library photograph, or is missing alt text while it is not decorative.
- A gallery is empty, or one of its photographs has no library file or no alt text.
- A product spotlight does not point at a published stone.
- A quarry story points at a photograph that no longer exists.
- A call to action has a button label and no safe URL.

Attention does not block:

- The first body heading is an H3. The checker prefers an H2 first.
- A text-and-image block has a photograph and alt text, and no heading or paragraph beside it.
- A quarry story has words and no photograph yet.

Ready items are the same checks once they pass. The publish dialog repeats the blocked rows and links back to them.

The excerpt field shows the count while typing. Zero characters, fewer than 160, and more than 200 are explained with the same sentences the server uses. A length inside 160–200 is marked ready. The excerpt can still be saved when it is short. It cannot be published until it is in range.

## Saving

Studio does not wait for a button. About 1.5 seconds after a change, it PUTs the article to `/admin/posts/{id}/studio`. **Save** and **Save now** send the same request immediately.

The payload is:

- `baseVersion`, the lock version Studio last received
- `schemaVersion`, always 1
- `intent`, `autosave` or `manual`
- `metadata`, title, slug, excerpt, SEO fields, featured image, tags, and categories
- `content`, the Tiptap document
- `clientSavedAt`

Only one save runs at a time. Edits made while a save is in flight are folded into one follow-up save.

### What the server accepts

`StudioDocumentService::save` refuses the request when the article is still classic. The message is to convert it first.

It compares `baseVersion` with `posts.lock_version`. A mismatch is HTTP 409: the article was saved somewhere else, and this tab must reload or explicitly overwrite. The banner says the local version is kept on this device. **Load the latest version** reloads. **Keep my version** confirms, then saves again using the server’s current lock, replacing the other tab’s edits.

Schema version must be 1. The document then passes through `DocumentValidator`. That step:

- requires a doc of schema version 1
- drops unknown block types
- strips HTML from plain fields
- rejects raw `src`, `html`, `style`, and `onclick` on blocks that must not carry them
- assigns a `blockId` when one is missing, duplicated, or the wrong shape
- writes a frame from the allowlist
- keeps an empty document as one empty paragraph

A document that fails validation is not saved. Studio shows the error and can jump to the field.

Metadata limits on the way in: title 200, slug 200, excerpt 255, SEO title 60, meta description 160, featured alt 180. Tags are normalised as described above. A category id must exist. A featured media id must exist in `journal_media`. `removeFeaturedImage` clears the cover.

### Slug rules

While the article is still an `untitled-article` draft, the first real title becomes the slug. Spaces and punctuation are turned into a normal slug. If that slug is taken, the server appends `-2`, `-3`, and so on.

Once the article has been published, is currently published, or is scheduled, the slug no longer follows the title. It changes only when the admin edits the slug field. A colliding slug still gets a numeric suffix.

### Where a save is written

An unpublished article, including a scheduled one, is updated in place on `posts`. `body_document` is replaced, metadata is copied onto the row, `lock_version` goes up by one, and `post_references` is rebuilt. Each reference is the post, a kind (`product` or `media`), the target id, and the block id.

A live article is different. The public row stays as readers see it. The save increments `lock_version` and upserts one `post_drafts` row: document, title, slug, excerpt, meta title, meta description, tags, featured alt, image, and category. The live references are left alone until the next publish. The save label becomes **Draft saved**.

### If the network fails

The editor writes a recovery copy to `localStorage` under `blog-studio:recovery:{id}` until the server confirms the latest change. Offline saves retry after 3 seconds, then 10, then 30. The banner offers **Retry now**. A session timeout (HTTP 419) tells the admin to reload and sign in again, and says the changes are still on this device. When a recovery copy is present on the next open, Studio offers restore and discard.

Preview and publish both try to save first. If that save fails, they stop, so the preview or the live page is not behind the screen.

## Photographs

Block images and the featured image use `POST /admin/posts/{id}/studio/media`.

The file must be JPG, PNG, or WebP, and at most 6 MB. The classic editor’s cover limit is 5 MB. Studio’s limit is 6 MB.

The file is stored as `journal/{post id}/photo-{random}.{ext}`. A responsive picture set is generated. A `journal_media` row stores the path, optional alt, width, and height. The editor receives the new id and URL and puts the id on the block or on the featured image.

Blocks never store a typed image address. On the way back to the editor, the server fills `url` from `journal_media`. On the public page, the renderer looks the id up again. A missing file becomes the sentence “This photograph is unavailable.”

The featured image is article metadata, not a block. It is the picture at the top of the article and on journal cards. Publishing with a featured image and an empty alt text is blocked.

## Stones inside an article

The product picker calls `GET /admin/posts/studio/products`. The query matches the product name. Results are 12 to a page. A stone that is not on the catalogue is marked blocked and cannot be treated as ready.

Cards already placed in the document are refreshed from `GET /admin/posts/studio/product-cards?ids=1,2`. A missing id comes back as “Unavailable stone”.

On the public page the card is resolved again from the catalogue, so a renamed stone or a new catalogue photo appears without another save.

## Preview, publish, and schedule

### Preview

**Preview** saves, then asks `POST /admin/posts/{id}/studio/preview` for the preview URL. That URL is `GET /admin/posts/{id}/preview`.

The preview renders the same article template as the public page. If a draft revision exists, the preview uses the draft title, excerpt, meta description, cover, category, tags, and document. The visible title is prefixed with `[Preview]`. Comments and the comment form are omitted. JSON-LD is omitted. The response is `noindex, nofollow` and `Cache-Control: private, no-store`. The canonical URL is still the public `/{slug}`.

### The publish dialog

**Publish…**, **Schedule**, and **Republish** open the same dialog.

The dialog shows:

- the public path, including a slug that has not been published yet
- how many product blocks are linked
- the check counts: ready, to review, and blocking

When anything is blocked, the primary buttons are disabled and each blocked check is a button back to that field. When every required check passes, the dialog says so.

For an article that is not live, the dialog offers **Publish now** and a **Schedule** form. The schedule input is `datetime-local`, minimum “now” in the display timezone (`config('app.display_timezone')`), labelled with `config('app.display_timezone_label')`.

For a live article, the dialog explains that readers still see the live version and that republishing replaces it with this draft. The button is **Republish**. The schedule form is hidden while the article is live. Cancel the public state from Classic if the article must leave the site, or keep editing the draft and republish.

If the last schedule failed, the dialog says the article will not be retried on its own. The checks have to be fixed, then the admin publishes or schedules again.

### Publish now

`POST /admin/posts/{id}/studio/publish` loads the editing copy. For a live article that is the draft. For anything else it is the post itself. The quality checker runs again on the server. The buttons in the dialog are not trusted on their own.

A blocked report returns HTTP 422 with the problems and the report. Nothing is published.

A clean publish, in one database transaction:

- copies title, slug, excerpt, SEO fields, tags, cover, alt text, and category onto `posts`
- copies the document onto `body_document`
- sets `status` to `published` and `is_published` to true
- clears `scheduled_for`
- sets `published_on` to today when it was empty, and leaves an existing date in place
- increments `lock_version`
- deletes the draft row
- rebuilds `post_references`

The public URL is `/{slug}`. The browser title is `{SEO title or article title} | Royal Indian Stones`. The meta description is the meta description, otherwise the excerpt. JSON-LD is a `BlogPosting` plus a breadcrumb: Home, Blog, and the article. `timeRequired` is `PT{minutes}M`. The author and publisher are the organisation Royal Indian Stones.

### Schedule

`POST /admin/posts/{id}/studio/schedule` requires a future `publishAt`. An empty or past time is rejected. The same quality gate runs first.

The row becomes `status = scheduled`, `scheduled_for` set, `is_published` false. The article disappears from `/blog` and from `/{slug}` until the time arrives. The chip in Studio shows the formatted time.

**Cancel schedule** sets `status` back to `draft`, clears `scheduled_for`, and leaves `is_published` false.

When the time arrives, `publishDue()` publishes with the same gate. A due article that is now blocked becomes `schedule_failed` and stays hidden. The scheduler command is `blogs:publish-scheduled`, every five minutes, without overlapping, logged to `storage/logs/blogs-publish-scheduled.log`. The same publisher also runs when an admin opens Studio or when a visitor opens `/blog` or an article URL.

### What “live” means

`isLive()` is `is_published` and `status === published`. The public scope is slightly wider for older rows: `is_published`, status `published` or null, and `scheduled_for` empty or already past. New Studio articles always set `status`, so both rules agree.

Reading time is recalculated whenever the body, summary, document, or content source changes. Studio counts words in the excerpt plus the text inside the document. The stored value is at least one minute.

`view_count` increments when a visitor opens the real article. Preview does not increment it.

## Editing a live article

This is the part that is easiest to misread in the admin.

Saving a live article does not change what readers see. The public page keeps reading `posts`. Studio, the quality checker, and the preview read `post_drafts` when that row exists.

The admin can change the title, excerpt, slug, cover, category, tags, and every block. Those values sit on the draft. The top bar says **Draft saved**. **Republish** is what copies them onto the live row and deletes the draft.

Until republish, the public slug stays the live slug. The draft may hold a different slug. The publish dialog shows the draft path. After republish, that slug is the public address, unless another article already owns it, in which case the server adds a numeric suffix at save time.

Opening Studio on a live article with a draft shows the draft document, not the live document. The bootstrap flag `hasDraftRevision` is true.

## Classic editor

The classic form is retired. `/admin/posts/create` and `/admin/posts/{id}/edit` redirect into Blog Studio, and saving the old form no longer writes HTML. The notes below describe the form that used to save classic articles. Migration of those articles is in [blog-migration.md](blog-migration.md).

**Classic** on a row, or **Open the classic editor** inside Studio, opens `/admin/posts/{id}/edit`. `/admin/posts/create` is the same form for a brand-new classic article. The blog list no longer links to that create route. New work is expected to start in Studio.

The form is a normal admin page with a sticky bar: the title, **Shown** or **Hidden**, a save state, **Preview**, and **Save**. Preview here is local. It builds a desktop or mobile frame in the browser from the form. It is not the Studio preview URL.

Four sections:

1. **Cover.** JPG, PNG, or WebP, up to 5 MB. An existing cover can be removed. The file is stored for that article and replaces the previous cover when a new one is saved.
2. **Writing.** A contenteditable area with text, heading, subheading, bold, italic, link, list, quote, and image. The HTML is copied into a hidden `body` field on submit. Inline images are JPG, PNG, or WebP, up to 5 MB.
3. **Details.** Title (required, 200), date, visibility (Hidden or Shown), one category, summary (180), and address. A blank address is built from the title. The slug must match `[A-Za-z0-9_-]+` and be unique.
4. **Search.** A meta description up to 2000 characters, plus a snippet preview of title, URL, and description.

On save, `JournalHtml` rejects scripts and other dangerous markup, then cleans the HTML. A body that is empty after cleaning is rejected. The summary may be empty. Classic does not enforce the 160–200 excerpt rule. That rule belongs to Studio publish.

Visibility maps onto the shared columns. Shown sets `is_published`, `status = published`, and clears `scheduled_for`. Hidden, when the article was published or had no status, sets `status = draft`. A scheduled Studio article that is saved from Classic keeps a non-published status unless the admin chooses Shown.

Classic does not write `body_document`. If the article is still `content_source = studio`, the public page prefers the Studio document whenever that document has visible content. Use the conversion action to switch the source. Opening Classic from the more menu does not by itself flip the source.

Deleting managed files happens when the cover changes and when the article is deleted.

## Converting between Classic and Studio

Opening Studio on a classic article shows a conversion card.

The card names the article and says readers currently see the classic body. It counts the blocks a conversion would create. The note on the card says the classic HTML stays in place until a Studio version is published. For a live article, the confirm line is explicit: readers will see the Studio version as soon as the live article is converted. For a draft, the confirm line says the article will be edited in Studio from now on.

If `body_document` already has blocks, a second choice appears:

- build a new Studio version from the current classic HTML, replacing the earlier Studio document
- return to that earlier Studio document

**Keep using the Classic editor** goes back to the HTML form. **Convert to Blog Studio** POSTs to `/admin/posts/{id}/studio/convert`.

The converter parses the classic HTML. It keeps H2 and H3 as headings, and paragraphs, quotes, and list items as paragraphs. Other tags are walked for their text. Photographs in the classic HTML are not turned into image blocks. The result is validated like any other Studio document, so every block receives an id and a frame. The original HTML remains in `body`.

For a draft, conversion sets `content_source` to `studio`, stores the document, and sets status to draft. For a live article, conversion stores the document and leaves the article live. The next public view then uses the Studio document, because the source is `studio` and the document has visible content.

Going back is `to: classic` on the same endpoint. It sets `content_source` to `classic` and redirects to the classic form. It does not delete `body_document`. A later conversion can restore that document with the “earlier Studio version” choice.

## Categories

`/admin/post-categories` is a short list. The description on the page is “Groups used by blog articles.”

Adding a category requires a name of up to 120 characters. The slug is built from the name. Saving a new name rebuilds the slug. The table shows the name, an edit field, the number of articles, and delete.

Delete removes the category. The confirm text says its articles stay on the blog. Article rows keep their own `post_category_id`. The draft table’s category column is set null when the category disappears.

Studio loads every category into the Advanced tab, ordered by name. The public journal lists a category only when at least one published article uses it. The filter is ` /blog?category={slug}`. An unknown slug is a 404.

Related articles on a public post prefer the same category, then shared tags ordered by view count, then other published articles by view count. Up to four related and four recent articles are shown. A story already in Recent is not repeated in Related.

## Comments

A published article shows approved comments and a form: name, email, and comment (5 to 2000 characters). The form posts to `/{slug}/comments` and is throttled. The comment is stored unapproved. The visitor sees “Thank you. Your comment will appear after it is reviewed.” Preview pages do not show the form.

`/admin/comments` lists comments newest first. The filter is All, Pending, or Approved. Each card shows the article title, the name, the email, the status, and the comment. **Approve** sets `approved`. **Delete** removes the row after confirmation. Pending comments are also counted on the admin nav and on the header alert.

Unapproved comments are never printed on the public article. The article query loads only `approved = true`.

## The journal page in admin

`/admin/journal` is not the article editor. It is the settings page for `/blog`. The public article list and the lead story are still made from published posts.

Two tabs are on the page: **Cover** and **Featured story**. Save is **Save Journal Page**. **View public journal** opens `/blog`. **Preview** uses the journal editor’s own preview, built from published stories only. Hidden, future, and invented articles are left out.

### Cover

The cover panel stores the introduction fields: eyebrow (80), heading (80), italic line (80), and introduction (500). It also stores a share photograph, a caption, and a photograph description. The caption field says it is saved with the page and that the public journal does not print it.

The current public template, `resources/views/journal.blade.php`, renders the featured story and the story grid. It does not print the eyebrow, heading, italic line, or introduction. Those values stay on `journal_settings` and in the admin preview. The share photograph is the image used when a featured story has no picture of its own.

### Featured story

This panel chooses the large story at the top of `/blog`.

One slot is saved in `edit_post_ids`. The picker lists every article, and a hidden one is labelled “(hidden)”. The status line says which published story the public page will actually feature.

Rules used by the public page:

- If the chosen article is published, it is the feature.
- A hidden choice is skipped.
- An empty slot uses the latest published story that has a cover image, then the latest published story.
- Search and category pages feature the first matching story on page 1 instead of this choice.

**Use latest story** clears the slot.

The public grid then lists other matching stories, 12 to a page, excluding the feature. Categories in the browse bar are those with at least one published article. A search (`?q=`) is `noindex`. An empty category result is `noindex`. The landing page and a category that has stories are indexable.

Journal settings are cached under `journal.settings` until the row is saved.

The home page is separate. It shows the three latest published articles. It does not use the featured-story slot.

## After the article is public

`/{slug}` is rendered by `SiteController` only when the slug is not a static page. The article must pass the published scope. A mismatch of slug capitalisation redirects to the stored slug.

The page shows the title as the only H1, the byline “By Royal Indian Stones”, the date, the featured image, then the body. A Studio article with visible document content renders `body_document` through `RichContentRenderer`. Any other article renders the classic HTML in `body`. Tags are printed under the body. Recent and related stories follow. Then approved comments and the comment form.

Published articles are included in the XML sitemap. Drafts, scheduled articles, previews, and admin URLs are not.

## Records the admin is writing

| Store | What an admin action changes |
| --- | --- |
| `posts` | The article. Studio drafts write here until the article is live. After that, publish is what updates the public columns. |
| `post_drafts` | The unpublished revision of a live Studio article. One row per article. Republish deletes it. |
| `journal_media` | Photographs uploaded inside Studio. |
| `post_references` | Which block points at which stone or photograph. Rebuilt on save of an unpublished article, and on publish. |
| `post_categories` | Topic names from the Categories screen. |
| `journal_settings` | Cover copy and the featured-story id from the Journal page screen. |
| `comments` | Reader comments. Public only after Approve. |

`lock_version` starts at 1 and increases on Studio save, convert, and publish. It is the token that stops two tabs from silently overwriting each other.

## Code map

| Piece | Path |
| --- | --- |
| Blog list and classic form | `app/Http/Controllers/Admin/PostController.php`, `resources/views/admin/posts/index.blade.php`, `resources/views/admin/posts/form.blade.php` |
| Studio HTTP | `app/Http/Controllers/Admin/BlogStudioController.php` |
| Save, publish, schedule, convert | `app/Services/BlogStudio/StudioDocumentService.php` |
| Classic HTML to blocks | `app/Services/BlogStudio/ClassicToStudioConverter.php` |
| Templates and the 160–200 excerpt rule | `app/Services/BlogStudio/ArticleTemplates.php`, `app/Services/BlogStudio/ArticleExcerpt.php` |
| Validation, quality, frames, public HTML | `app/Services/RichContent/` |
| Studio interface | `resources/js/blog-studio/` |
| Journal page admin | `app/Http/Controllers/Admin/JournalSettingController.php`, `resources/views/admin/journal/edit.blade.php` |
| Public `/blog` | `app/Services/JournalIndex.php`, `resources/views/journal.blade.php` |
| Public article | `app/Http/Controllers/SiteController.php`, `resources/views/post.blade.php` |
| Schedule command | `app/Console/Commands/PublishScheduledBlogs.php`, `routes/console.php` |
| Tests | `tests/Feature/BlogStudioTest.php`, `tests/Feature/JournalPageTest.php` |

`BLOG_REPLICATION_SPEC.md` and `public/assets/js/blog-studio.js` describe an earlier vanilla-JavaScript studio. The editor that ships now is the React application under `resources/js/blog-studio/`.
