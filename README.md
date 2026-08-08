# SplitEven

SplitEven is a Splitwise-style expense splitting web app. Track shared costs with friends, split bills equally or by custom rules, record settlements, and see who owes whom at a glance.

## Features

- User accounts with email and unique username
- **Circle** — add friends via username search, accept or reject requests
- **Expenses** — split 1-on-1 or in groups
- **Split types** — equal, by shares, by percentage, or exact amounts
- **Settlements** — record payments between friends
- **Dashboard** — summary of what you owe and what you're owed
- **Activity feed** — recent expense and settlement history
- Mobile-first UI with sidebar navigation on desktop and bottom nav on mobile

## Tech Stack

| Layer | Technology |
| --- | --- |
| Backend | Laravel 13, PHP 8.4 |
| Frontend | Vue 3, Inertia.js v3 |
| Styling | Tailwind CSS 4, shadcn-vue |
| Database | MySQL (or SQLite for local dev) |
| Testing | Pest |
| Build | Vite |

## Requirements

- PHP 8.3+
- Composer
- Node.js 20+ and npm
- MySQL 8+ (recommended) or SQLite

## Getting Started

### 1. Clone and install

```bash
git clone <repository-url> spliteven
cd spliteven
composer setup
```

The `composer setup` script installs PHP dependencies, creates `.env` from `.env.example`, generates an app key, runs migrations, installs npm packages, and builds frontend assets.

### 2. Configure environment

Copy or edit `.env` and set your database credentials:

```env
APP_NAME=SplitEven
APP_URL=http://spliteven.test

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=spliteven
DB_USERNAME=root
DB_PASSWORD=
```

Create the database if it does not exist:

```sql
CREATE DATABASE spliteven;
```

Then run migrations:

```bash
php artisan migrate
```

### 3. Run the app

**With Laravel Herd** (recommended on macOS):

If the project lives in your Herd directory, it is served automatically at:

```
http://spliteven.test
```

Start the Vite dev server for hot reload:

```bash
npm run dev
```

**Without Herd:**

```bash
composer run dev
```

This starts the Laravel server, queue worker, log tail, and Vite together.

Or run them separately:

```bash
php artisan serve
npm run dev
```

Visit `http://localhost:8000` (or your Herd URL).

### 4. Production build

```bash
npm run build
```

## Testing

Run the full test suite:

```bash
php artisan test
```

Or use the composer script (includes linting and static analysis):

```bash
composer test
```

## Project Structure

```
app/
  Http/Controllers/   # Route handlers
  Models/             # Eloquent models
  Services/           # Balance and split calculation logic
resources/js/
  pages/              # Inertia Vue pages
  components/         # Shared UI components
  layouts/            # App, auth, and settings layouts
routes/web.php        # Application routes
public/logo/          # App logo and favicon assets
```

## License

MIT
