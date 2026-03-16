# Plan: Environment Variable Configuration

## Overview
Move hardcoded database credentials and sensitive configuration out of PHP source files into a `.env` file. This prevents credential leakage through version control, file exposure, or accidental sharing.

## Current State
- Database credentials are hardcoded in `api/db.php`:
  ```php
  $host = "localhost";
  $dbname = "analytics";
  $username = "root";
  $password = "CSE135ajseb@";
  ```
- Same pattern likely exists in model files or a shared config
- Credentials are committed to the repository (if version controlled)
- No `.env` file or environment-based configuration exists

## Implementation

### Step 1: Create `.env` File
**File:** `.env` (project root)

```
DB_HOST=localhost
DB_NAME=analytics
DB_USER=root
DB_PASS=CSE135ajseb@
APP_ENV=production
APP_DEBUG=false
```

### Step 2: Create `.env.example` File
**File:** `.env.example` (project root, safe to commit)

```
DB_HOST=localhost
DB_NAME=analytics
DB_USER=root
DB_PASS=your_password_here
APP_ENV=development
APP_DEBUG=true
```

### Step 3: Create Env Loader
**File:** `app/core/Env.php`

Create a simple `.env` parser (no third-party library needed):
- `Env::load(string $path)` — Read the `.env` file line by line, skip comments (`#`) and empty lines, parse `KEY=VALUE` pairs, and set them via `putenv()` and into `$_ENV`
- `Env::get(string $key, $default = null)` — Return `getenv($key)` or `$default` if not set

The parser should handle:
- Lines with `=` separator
- Quoted values (strip surrounding quotes)
- Comment lines starting with `#`
- Empty lines (skip)

### Step 4: Load `.env` Early in Bootstrap
**File:** `index.php`

Add at the very top (before Router or any DB calls):
```php
require_once __DIR__ . '/app/core/Env.php';
Env::load(__DIR__ . '/.env');
```

**File:** `api/router.php`

Same — load env at the top since API routes have their own entry point.

### Step 5: Update Database Connections
**File:** `api/db.php`

Replace hardcoded values:
```php
$host = Env::get('DB_HOST', 'localhost');
$dbname = Env::get('DB_NAME', 'analytics');
$username = Env::get('DB_USER', 'root');
$password = Env::get('DB_PASS', '');
```

**Any other files** that create PDO connections (check models or config files) — update similarly.

### Step 6: Protect `.env` from Web Access
**File:** `.htaccess`

Add a rule to block direct access to `.env`:
```apache
<Files .env>
    Order allow,deny
    Deny from all
</Files>
```

### Step 7: Add `.env` to `.gitignore`
**File:** `.gitignore`

Add:
```
.env
```

This ensures credentials are never committed. The `.env.example` file serves as a template for anyone setting up the project.

## Files Created/Modified
| File | Action |
|------|--------|
| `.env` | **Create** — Actual credentials (never committed) |
| `.env.example` | **Create** — Template with placeholder values (committed) |
| `app/core/Env.php` | **Create** — Environment variable loader |
| `index.php` | **Modify** — Load `.env` at bootstrap |
| `api/router.php` | **Modify** — Load `.env` at bootstrap |
| `api/db.php` | **Modify** — Replace hardcoded credentials with `Env::get()` |
| `.htaccess` | **Modify** — Block web access to `.env` |
| `.gitignore` | **Modify** — Exclude `.env` from version control |

## Manual Testing

### Test 1: Application Works with `.env`
1. Create the `.env` file with correct credentials
2. Update `api/db.php` to use `Env::get()`
3. Navigate to `/dashboard` — confirm data loads correctly
4. Navigate to `/reports/static` — confirm table renders with data
5. Test API: `curl https://reporting.angelo-j.xyz/api/static` — confirm JSON response

### Test 2: Missing `.env` Fails Gracefully
1. Rename `.env` to `.env.bak` temporarily
2. Navigate to `/dashboard`
3. Confirm the app either shows the 500 error page or uses fallback defaults (depending on whether defaults are set)
4. Restore `.env`

### Test 3: `.env` Not Accessible via Web
1. Try to access `https://reporting.angelo-j.xyz/.env` in a browser
2. Confirm you get a 403 Forbidden response (not the file contents)

### Test 4: Incorrect Credentials Handled
1. Change `DB_PASS` in `.env` to a wrong value
2. Navigate to `/dashboard`
3. Confirm the app shows a 500 error page (not a raw PHP error with credentials)
4. Restore the correct password

### Test 5: `.env.example` Has No Real Credentials
1. Open `.env.example` and verify it contains only placeholder values
2. Confirm it can be safely committed without exposing secrets
