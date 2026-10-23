# Stones Git and deployment hygiene

This note is for the Royal Indian Stones Laravel app (`TwoSpartans/laravel-royal-indian-stones`). Production deploys from `main` with the server script `/home/u412714025/deploy.sh stones`. That script is not in this repository.

The live site does not serve the repository `public/` directory directly. The script copies `public/` into the real document root `/home/u412714025/domains/royalindianstones.com/public_html` with `rsync -a --delete`.

## What must be pushed

Push application source and the public files the site serves without a build step:

- PHP application code, routes, config, migrations, factories, and tests
- Blade templates and public CSS/JS under `public/assets/` (the catalogue and admin UI are not the Vite bundle)
- `composer.lock` and `package-lock.json`
- `.env.example` (placeholders only)
- `public/assets/data/products.json` and `public/assets/data/images.json`
- Product, category, project, and journal images the pages reference, including `public/assets/img/products/`
- `public/assets/css/admin.css` and the other committed stylesheets and vendor assets
- `public/assets/img/optimized/` image variants (see below)

## Catalogue files

`public/assets/data/images.json` is a source manifest. The catalogue importer reads it when copying historical image variants. Do not delete it and do not gitignore it.

`public/assets/data/products.json` is the file the public catalogue search loads. It is rewritten from the database by `CatalogueIndex::write()` when an admin saves a product, when the catalogue importer finishes, and at the end of `php artisan images:variants`. The deploy script does not regenerate it, so the committed copy is what production serves after `rsync`. Commit an updated `products.json` when product data that should appear in search has changed.

`public/assets/img/optimized/` holds resized AVIF and WebP files written by `php artisan images:variants`. The deploy script does not run that command. Those files are committed on purpose. If they were removed from Git, the next deploy’s `rsync --delete` would delete them from the live document root and the site would fall back to larger originals. Regenerate locally with `php artisan images:variants`, then commit the new files under `public/assets/img/optimized/`.

## What must not be pushed

- `.env` and any copy of it (`.env.backup`, `.env.production`). Database passwords, mail passwords, and `APP_KEY` live there.
- `auth.json`, Composer and npm credentials
- `vendor/` and `node_modules/` (the server installs them)
- Vite output: `public/build/` (including `manifest.json` and hashed CSS/JS) and `public/hot`
- The Bunny font dev registry `public/fonts-manifest.dev.json`. The production font manifest is inside `public/build/` and is created by `npm run build`.
- SQLite files, PHPUnit caches, IDE folders
- Laravel runtime files already covered by the `storage/` and `bootstrap/cache/` ignore files: logs, sessions, compiled views, cache data, and the maintenance-mode `down` file
- PHP `error_log` at the repository root or in `public/`
- Database dumps and the deploy script’s backups under `~/backups/deploy-history/`. Those directories are outside this repo.

Vendor icon fonts in `public/assets/vendor/` are real site assets. Do not ignore fonts as a group.

## How a Stones deploy runs

`deploy.sh stones` does the following:

1. Checks the repo, branch `main`, remote, `.env`, Laravel directories, `public/index.php`, and the separate live document root.
2. Fetches `origin/main` and accepts only a fast-forward update.
3. Compares local changes with the incoming commit. Vite build output is treated as generated, not as a reason to block the update.
4. Writes a database dump and an archive of the live document root to `~/backups/deploy-history/stones/<timestamp>/` before changing the app. A failed deploy can leave the site in maintenance mode. The script does not restore that database dump by itself.
5. Enables Laravel maintenance mode.
6. Sets aside conflicting generated Vite files, updates the code, installs Composer dependencies, clears and rebuilds Laravel caches, and discovers packages.
7. Runs `npm ci --ignore-scripts` and `RAYON_NUM_THREADS=1 npm run build`.
8. Runs migrations and caches config, routes, and views.
9. Publishes `public/` to the live document root with `rsync -a --delete`.
10. Leaves these live paths alone during that sync: `index.php`, `.htaccess`, `storage`, `.well-known/`, and `.user.ini`.
11. Sets public directories to `755` and regular public files to `644`.
12. Leaves maintenance mode and requests the homepage, products page, admin login, sitemap, and catalogue JSON.

`npm run build` must finish before the `rsync`. The hashed files in `public/build/` are created on the server. They do not need to be in Git. The only Blade view that calls `@vite` today is `resources/views/welcome.blade.php`. The public catalogue uses `public/assets/`, which is committed.

## Why the live `index.php` stays custom

The repository `public/index.php` assumes the application root is the parent of `public/`. On this host the document root is a different directory, `/home/u412714025/domains/royalindianstones.com/public_html`. The live `index.php` points at `/home/u412714025/royalindianstones`. Replacing it with the repository file would boot Laravel from the wrong path. The live `storage` entry is a symlink to `storage/app/public` in the application directory. The deploy exclude list keeps both of those, plus `.htaccess`, `.well-known/`, and `.user.ini`.

## If Git reports a build-file conflict

`public/build/` is gitignored, so a normal `git pull` should not try to merge those files. If an older commit still tracked them, or a local build was force-added, Git can refuse the fast-forward because the same path changed locally and upstream.

Safe recovery on a machine that can rebuild:

```bash
git rm -r --cached --ignore-unmatch public/build
git status
```

`--cached` removes the paths from the index only. The files stay on disk. Do not `git clean` the build directory if you still need it for a local page that uses Vite. After the index no longer tracks `public/build/`, commit that removal, then deploy. The server build recreates the manifest.

Do not delete `public/assets/img/optimized/` or `public/assets/data/products.json` to clear a conflict. Those are the files the live catalogue requests.

## Review before you commit

```bash
git status --short
git diff -- .gitignore docs/deployment-git-hygiene.md
git ls-files public/build
git check-ignore -v --no-index public/build/manifest.json public/hot error_log public/error_log
git check-ignore -v --no-index public/assets/data/products.json public/assets/data/images.json public/assets/css/admin.css .env.example composer.lock package-lock.json
```

The last command should print nothing. Those paths are supposed to stay trackable. Then commit only the hygiene change plus any source you meant to ship. Do not commit `.env`.
