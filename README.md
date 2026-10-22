# Client Tracker

A web app for a **single admin** to keep track of clients, the companies they own, the projects built for those companies, and every **edit** a client asks for afterwards, including prices, payments and attached files.

> **Naming note:** on screen these are called **Edits**. In the code and the database they are still called **change requests** (`ChangeRequest` model, `change_requests` table, `/change-requests/...` routes). They are the same thing.

---

## Features

- **Clients, companies, projects, edits and attachments**, each with its own page and full add / edit / delete.
- **Dashboard** with money cards (outstanding, collected this month), pending edits, clients to follow up, a 6-month revenue chart, a status breakdown, and one-click actions (_Done today_, _Mark paid_, _Mark done_).
- **All Edits** page: search, filters (status, priority, payment), sorting, totals and quick actions across every project.
- **All Attachments** page: card grid with image thumbnails, file-type tabs, search, sorting, upload, replace file and delete.
- **Reports** page: Edits, Clients, Companies and Projects tabs, with a date range and searchable multi-select filters. **Export to Excel** downloads exactly the rows currently shown (one tab, or all four tabs as sheets).
- **Follow-up tracking:** clients not contacted for too long are flagged, and a phone icon marks a client as followed up today in one click.
- Light and dark mode with a custom color palette.
- Demo data seeder and an automated test suite.

## Business rules

| Rule             | Details                                                                                                                                                                                                                                                                |
| ---------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Deleting         | Clients, companies, projects and edits are **soft deleted**. A parent that still has children **cannot** be deleted (a client with companies, a company with projects, a project with edits). Attachments are deleted for real, and their files are removed from disk. |
| Unique values    | A client's **phone** and **email** are unique. A company name is unique **per client** (a deleted company's name can be reused).                                                                                                                                       |
| Payments         | An edit is paid when it has a **paid date**, and a paid date requires a **price**. Cancelled edits are left out of every money total.                                                                                                                                  |
| Follow-ups       | A client needs a follow-up when never contacted, or when the last follow-up is older than **360 days** (`Client::FOLLOW_UP_ALERT_DAYS`).                                                                                                                               |
| Attachment types | PDF, Word (`.doc`, `.docx`) and images (JPG, PNG, WEBP, GIF).                                                                                                                                                                                                          |
| Attachment sizes | **Images up to 1 MB. PDF and Word files up to 2 MB.** Checked in the browser and again on the server.                                                                                                                                                                  |
| File privacy     | Uploaded files are **not public**. They are served through a route that requires login.                                                                                                                                                                                |
| Access           | Login is required everywhere. There is a single admin account, public registration is disabled, and there is no "delete account" feature.                                                                                                                              |

## Tech stack

- **Backend:** Laravel (PHP), MySQL
- **Frontend:** Vue 3 with TypeScript, connected through **Inertia.js**
- **UI:** Tailwind CSS 3 and shadcn-vue components
- **File uploads:** [Spatie Laravel Media Library](https://spatie.be/docs/laravel-medialibrary)
- **Excel export:** [Laravel Excel](https://laravel-excel.com) (`maatwebsite/excel`)
- **Tests:** PHPUnit
- **Package managers:** Composer and pnpm

## Requirements

- PHP (see the `require` section of `composer.json` for the minimum version) with the extensions `gd`, `zip`, `mbstring`, `xml`, `fileinfo` and `pdo_mysql`
- Composer
- Node.js and pnpm
- **MySQL** (SQLite will not work: the dashboard uses MySQL date functions)
- On Windows, [Laragon](https://laragon.org) provides all of the above

## Installation

```bash
# 1. Get the code and install dependencies
git clone https://github.com/Mustafa21102005/client-tracker-VioletPro.git
cd client-tracker
composer install
pnpm install

# 2. Create the environment file and the app key
copy .env.example .env        # on macOS / Linux: cp .env.example .env
php artisan key:generate
```

**3. Create an empty MySQL database** (for example with HeidiSQL in Laragon):

```sql
CREATE DATABASE client_tracker CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

**4. Edit `.env`:**

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=client_tracker
DB_USERNAME=root
DB_PASSWORD=

APP_TIMEZONE=Your/Timezone

# The one admin account (created by the seeder)
ADMIN_NAME="Admin"
ADMIN_EMAIL=admin@example.com
ADMIN_PASSWORD=choose-a-strong-password
```

Set the three `ADMIN_*` values before the next step. The seeder refuses to run without them.

**5. Create the tables, the admin account and the demo data:**

```bash
php artisan migrate --seed
```

**6. Start the app:**

```bash
pnpm dev
```

Then open your site (a Laragon address like `http://client-tracker.test`, or run `php artisan serve` and open `http://127.0.0.1:8000`) and log in with the `ADMIN_EMAIL` and `ADMIN_PASSWORD` you chose.

### Important notes

- **Do not run `php artisan storage:link`.** Uploaded files must only be reachable through the login-protected route. A public `storage` link would let anyone with a file's address open it.
- **PHP upload limits:** in `php.ini`, set `upload_max_filesize` to at least `2M` (for example `10M`) and `post_max_size` slightly larger (for example `12M`). Otherwise PHP can refuse a file before the app's friendlier size messages appear. Restart Apache afterwards.
- **Demo data** (about 18 clients with companies, projects and edits) is added by `php artisan migrate --seed` everywhere **except** in production. Reset everything with `php artisan migrate:fresh --seed`.

## Useful commands

| Command                            | What it does                                       |
| ---------------------------------- | -------------------------------------------------- |
| `pnpm dev`                         | Starts the front-end development server            |
| `pnpm build`                       | Builds the front end for production                |
| `pnpm exec vue-tsc --noEmit`       | Type-checks the Vue / TypeScript code              |
| `php artisan migrate:fresh --seed` | Rebuilds the database with the admin and demo data |
| `php artisan test`                 | Runs the automated tests                           |

## Tests

The suite has **183 tests** covering access control, every CRUD feature and its validation rules, delete protection, search and filters, the dashboard numbers, attachment rules and size limits, the reports and Excel export, the relationships between models, and the seeders.

The tests wipe their database on every run, so they use a **separate** one:

1. Create an empty database named `client_tracker_test`.
2. Check that `phpunit.xml` contains `DB_CONNECTION=mysql` and `DB_DATABASE=client_tracker_test`.
3. Run `php artisan test`.

As a safety net, the tests refuse to run on any database whose name does not end in `_test`, so your real data can never be wiped by accident.

## How the Excel export works

The Reports page and the Excel file are built from the **same code** (`app/Reports/Report.php`). It defines, for each tab, which records to fetch with the filters applied, which columns to show, and how a record becomes a row. The page displays those rows and the export writes those same rows, so a filtered report always exports exactly what is on screen. A test checks this for every tab.

## License

This project is open-sourced under the [MIT License](LICENSE).

## Author

**Mustafa Azmi Khalil** — © 2026
