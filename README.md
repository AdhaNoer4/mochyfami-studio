# MochyFami Content Studio

A Laravel 13 application for turning one content idea into a finished production
package: research, script, visual plan, an asset library with real media files
attached to it, and the production handoff built from what is actually there.

The backend is a JSON API authenticated with Sanctum bearer tokens. The frontend
is a React single page application built with Vite, Tailwind CSS and TypeScript,
served from the Laravel shell.

## Requirements

| Tool | Version | Why |
| --- | --- | --- |
| PHP | 8.3 or newer (8.4 recommended) | `composer.json` requires `^8.3` |
| Composer | 2.x | PHP dependencies |
| MySQL | 8.x, or MariaDB 10.6+ | The application targets MySQL, not SQLite |
| Node.js | 20 or newer | Vite build and dev server |
| npm | 10 or newer | Frontend dependencies |
| FFprobe | any recent build | Asset technical metadata inspection |

FFprobe is part of the FFmpeg distribution. It must be on `PATH`, because
`config/assets.php` resolves it from the `ASSET_FFPROBE_BINARY` environment
variable and falls back to the bare command name `ffprobe`. Check it with:

```bash
ffprobe -version
```

If it is installed somewhere unusual, point the variable at the full path in
`.env`:

```dotenv
ASSET_FFPROBE_BINARY=C:/tools/ffmpeg/bin/ffprobe.exe
```

A missing FFprobe does not break the application. Asset uploads still succeed and
the asset still works, but `metadata_status` is recorded as `failed` with a
`metadata_error` instead of being measured. The user can retry from the Analyze
action on the asset once FFprobe is available.

## Setup

```bash
git clone <repository-url>
cd mochyfami-studio

composer install
npm install

cp .env.example .env
php artisan key:generate
```

`.env.example` still ships Laravel's defaults, which point at SQLite. This
application runs on MySQL, so set the database block in `.env` to match your
server before migrating:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=mochyfami_studio
DB_USERNAME=root
DB_PASSWORD=
```

Create the database, then build the schema and the starter content:

```bash
php artisan migrate
php artisan db:seed
```

Or use the single setup script, which runs `composer install`, copies `.env`,
generates the key, migrates and builds the frontend:

```bash
composer run setup
```

`composer run setup` does not seed, so run `php artisan db:seed` yourself if you
want the categories and sample ideas.

### Seeded credentials

The seeder creates one account, and it is for local development only:

```text
email:    creator@mochyfami.studio
password: password123
```

Never run this seeder against a production or any shared database. The password
is in version control, which is exactly why it is only acceptable on a machine
you control.

## Running

```bash
composer run dev
```

`composer run dev` starts the Vite dev server, the queue worker and
`php artisan serve` together. Open <http://127.0.0.1:8000>.

To run the pieces separately:

```bash
npm run dev          # Vite dev server with hot module replacement
php artisan serve    # Laravel on http://127.0.0.1:8000
```

For a production-style local run, build the assets once and serve them from
`public/build`:

```bash
npm run build
php artisan serve
```

After a frontend change, if the browser does not show it, either `npm run dev` is
still running and will pick it up, or `npm run build` needs to run again. The
HTML shell only loads what the last build produced.

## Frontend routes and deep links

Every web path returns the same application shell from `routes/web.php`, and React
resolves the path itself. That means deep links and browser refreshes work: pasting
`/projects/1` into the address bar, or refreshing on `/dashboard`, loads the studio
instead of returning a 404.

An unmatched path under `/api/` is still a JSON 404. The API never answers with the
HTML shell.

## Asset files are private

Uploaded asset files are stored on a private disk and are never published. There is
no public URL and no `storage:link` step.

The only way to see a file is through the authenticated preview endpoint, which
streams the bytes after checking the asset's ownership. The API publishes
`preview_url` — the application route that does this — and never publishes
`file_path`, so a client never learns where the file lives on disk.

## Tests

```bash
php artisan test
composer test          # the same, after clearing the cached config
vendor/bin/phpunit tests/Feature/AssetStatusWorkflowTest.php   # one file
```

The suite runs against a **separate** `mochyfami_studio_testing` database and
`RefreshDatabase`, so it truncates and rebuilds that database on every run.

**Point it at a database you are willing to lose.** The credentials in
`phpunit.xml` (`mochyfami_studio_testing`, user `root`) are test-suite settings and
are not production configuration. Do not point `DB_DATABASE` at a real database and
expect to keep it: every test run empties whatever it is connected to.

Formatting:

```bash
vendor/bin/pint
```

Static analysis and the production frontend build:

```bash
npx tsc --noEmit
npm run build
```

## Where things are

```text
app/Enums/                  Status and type vocabularies, including the state machines
app/Http/Controllers/Api/   JSON API controllers
app/Http/Requests/          Form requests holding validation and nothing else
app/Http/Resources/         API response shaping
app/Policies/               Ownership rules
app/Services/               Domain logic; controllers stay thin
resources/js/components/    React components
resources/js/services/      Typed API client calls
routes/api.php              API routes
routes/web.php              The SPA shell and its fallback
tests/Feature/              Feature tests over HTTP
tests/Unit/                 Unit tests for logic that needs no framework
docs/                       Phase specifications, audit records, implementation notes
```