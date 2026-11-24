# Blog Studio migration

Blog Studio is the only editor. Classic create and edit routes redirect into Studio. A classic article is migrated the first time an admin opens it, or when you run the command below.

## Command

```bash
php artisan blogs:migrate-classic
php artisan blogs:migrate-classic 14
php artisan blogs:migrate-classic --dry-run
php artisan blogs:migrate-classic 14 --force
```

- With no id, every article whose `content_source` is `classic` is migrated.
- An id migrates that article. An article already in Studio is skipped.
- `--dry-run` prints the block and photograph counts and writes nothing.
- `--force` rebuilds a Studio article from `classic_body_backup` when that backup exists. It does not invent a backup for an article that was written in Studio.

The command is safe to run again. A second run skips articles already marked `studio` unless `--force` is set.

## What the migration keeps

- Headings become H2 or H3. An old H1 becomes an H2, because the article title is the only H1.
- Paragraphs, quotes, and list items become paragraphs. Bold, italic, and safe links stay on the text.
- Two or more photographs in a row become a gallery. A photograph on its own becomes an image block. Alt text is kept.
- The file stays where it already lives (`storage/journal/...` or `assets/img/journal/...`). A `journal_media` row points at that path. A remote image address is left out and counted as missing.
- The classic cover path stays on `posts.image` and is registered in `journal_media` when the file is on disk.

The original HTML is copied into `posts.classic_body_backup` before the Studio document is saved. `posts.body` is left as it was, so the backup and the body match on the first migration.

## Rollback

Studio is what readers see when `content_source` is `studio` and the document has visible content. To put one article back on its classic HTML:

1. Copy `classic_body_backup` into `body` if `body` was changed later.
2. Set `content_source` to `classic`.
3. Clear `body_document`, or leave it unused while the source is classic.

The public article template still prints `body` when the article is not a Studio article. There is no classic editing screen. Opening the article in admin migrates it again.

## Schedule failures

If a scheduled article fails the quality checks when its time arrives, `status` becomes `schedule_failed` and `schedule_failure_reason` stores the blocking explanations. It is not retried. Fix the checks in Studio, then publish or schedule it again. Publishing clears the reason.

## Journal page

The public journal shows the featured story and the article list. The cover introduction fields in the journal admin are kept for the editor preview and are not printed on `/blog`.
