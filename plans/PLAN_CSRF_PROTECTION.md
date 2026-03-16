# Plan: CSRF Protection

## Overview
Add CSRF (Cross-Site Request Forgery) token generation and validation to all state-changing forms and POST endpoints. This prevents attackers from tricking authenticated users into submitting malicious requests.

## Current State
- No CSRF tokens on any forms (login, user CRUD, report save)
- All POST routes accept requests without origin verification
- Session-based auth means the browser auto-sends cookies, making CSRF attacks trivial

## Implementation

### Step 1: Create CSRF Token Helper
**File:** `app/core/Csrf.php`

Create a utility class with two static methods:
- `Csrf::generateToken()` — Generate a random token using `bin2hex(random_bytes(32))`, store it in `$_SESSION['csrf_token']`, and return it
- `Csrf::validateToken($token)` — Compare the submitted token against `$_SESSION['csrf_token']` using `hash_equals()` (timing-safe comparison). Return `true`/`false`

### Step 2: Add Hidden CSRF Field to All Forms
**Files to modify:**
- `app/views/login.php` — Login form
- `app/views/users/create.php` — User creation form
- `app/views/users/edit.php` — User edit form
- `app/views/reports/create.php` — Report save form

In each form, add a hidden input:
```html
<input type="hidden" name="csrf_token" value="<?= Csrf::generateToken() ?>">
```

### Step 3: Validate CSRF Token in Controllers
**Files to modify:**
- `app/controllers/AuthController.php` — In the login POST handler, call `Csrf::validateToken($_POST['csrf_token'])` before processing credentials. If invalid, redirect back to login with an error message.
- `app/controllers/UserController.php` — In `store()`, `update()`, and `delete()` methods, validate the CSRF token before performing the operation. If invalid, return a 403 error.
- `app/controllers/ReportController.php` — In `store()`, validate CSRF token before saving the report.

### Step 4: Handle Validation Failures
When a CSRF token is missing or invalid:
- Set a flash message: `$_SESSION['error'] = 'Invalid request. Please try again.'`
- Redirect back to the referring page (use `$_SERVER['HTTP_REFERER']` or the form's origin route)
- Do NOT process the form submission

### Step 5: Regenerate Token Per Request
After each successful form submission, regenerate the token to prevent replay attacks. Call `Csrf::generateToken()` again so the next form load gets a fresh token.

## Files Created/Modified
| File | Action |
|------|--------|
| `app/core/Csrf.php` | **Create** — Token generation and validation |
| `app/views/login.php` | **Modify** — Add hidden CSRF field |
| `app/views/users/create.php` | **Modify** — Add hidden CSRF field |
| `app/views/users/edit.php` | **Modify** — Add hidden CSRF field |
| `app/views/reports/create.php` | **Modify** — Add hidden CSRF field |
| `app/controllers/AuthController.php` | **Modify** — Validate token on login POST |
| `app/controllers/UserController.php` | **Modify** — Validate token on create/update/delete |
| `app/controllers/ReportController.php` | **Modify** — Validate token on store |

## Manual Testing

### Test 1: Token Present in Forms
1. Log in as any user
2. Navigate to each form page (login, user create, user edit, report create)
3. Inspect the HTML source — confirm each `<form>` contains a hidden `csrf_token` input with a 64-character hex value

### Test 2: Valid Submission Works
1. Log in as `admin`
2. Go to `/users/create`, fill in the form, submit
3. Confirm the user is created successfully (normal flow unchanged)

### Test 3: Missing Token Rejected
1. Log in as `admin`
2. Go to `/users/create`, use browser DevTools to delete the `csrf_token` hidden input
3. Submit the form
4. Confirm the form is rejected with an error message and the user is NOT created

### Test 4: Tampered Token Rejected
1. Log in as `admin`
2. Go to `/users/create`, use browser DevTools to change the `csrf_token` value to `aaaa`
3. Submit the form
4. Confirm the form is rejected with an error message

### Test 5: Cross-Origin POST Rejected
1. Open a separate HTML file locally with a form that POSTs to `https://reporting.angelo-j.xyz/users/store` with a fake `csrf_token`
2. Submit from the local file while logged in to the app in another tab
3. Confirm the request is rejected (no user created)

### Test 6: Token Regeneration
1. Submit a valid form (e.g., create a user)
2. Inspect the next page's form — the `csrf_token` value should be different from the previous one
