# Plan: User Signup

## Overview
Add a public registration page where new users can create an account with a username and password. New accounts default to the `viewer` role (saved reports access only). No admin approval required.

## Current State
- Only super admins can create users via `/users/create`
- No public-facing registration page
- Login page has no "Sign up" link

## Implementation

### Step 1: Add Signup Route
**File:** `app/core/Router.php`

Register two new routes:
- `GET /signup` → `AuthController::showSignup()`
- `POST /signup` → `AuthController::handleSignup()`

Both routes should be accessible without authentication (like `/login`).

### Step 2: Create Signup View
**File:** `app/views/signup.php`

Simple form matching the login page styling:
- Username input (text, required)
- Password input (password, required)
- Confirm Password input (password, required)
- Submit button ("Create Account")
- Link back to `/login` ("Already have an account? Log in")
- Display error messages from `$_SESSION['signup_error']` (same pattern as login)

### Step 3: Add Signup Logic to AuthController
**File:** `app/controllers/AuthController.php`

Add two methods:

**`showSignup()`** — Render the signup view, read and clear any `$_SESSION['signup_error']`.

**`handleSignup()`** — Process the registration:
1. Trim and validate `$_POST['username']` — reject if empty or too short (min 3 chars)
2. Validate `$_POST['password']` — reject if empty or too short (min 8 chars)
3. Compare `password` and `confirm_password` — reject if they don't match
4. Check if username already exists via `UserModel::findByUsername()` — reject with "Username already taken"
5. Hash the password with `password_hash($password, PASSWORD_BCRYPT)`
6. Insert new user with role `viewer` and no section assignments (viewers don't need sections)
7. On success: set `$_SESSION['signup_success'] = 'Account created. Please log in.'`, redirect to `/login`
8. On failure: set `$_SESSION['signup_error']` with the specific message, redirect back to `/signup`

### Step 4: Add "Sign Up" Link to Login Page
**File:** `app/views/login.php`

Add a link below the login form:
```html
<p>Don't have an account? <a href="/signup">Sign up</a></p>
```

### Step 5: Show Success Message on Login Page
**File:** `app/controllers/AuthController.php` → `showLogin()`

Read and clear `$_SESSION['signup_success']` and pass it to the login view so the "Account created" message displays after redirect.

**File:** `app/views/login.php`

Display the success message if set (styled as a green/success banner, distinct from error messages).

## Files Created/Modified
| File | Action |
|------|--------|
| `app/views/signup.php` | **Create** — Registration form |
| `app/core/Router.php` | **Modify** — Add GET/POST `/signup` routes |
| `app/controllers/AuthController.php` | **Modify** — Add `showSignup()` and `handleSignup()` |
| `app/views/login.php` | **Modify** — Add signup link + success message |

## Manual Testing

### Test 1: Signup Page Loads
1. Visit `/signup` without being logged in
2. Confirm the form renders with username, password, confirm password fields and a submit button

### Test 2: Successful Registration
1. Fill in a unique username, matching passwords (8+ chars), submit
2. Confirm redirect to `/login` with "Account created. Please log in." message
3. Log in with the new credentials — confirm it works and the role is `viewer`
4. Confirm you can only access `/reports/saved`

### Test 3: Duplicate Username Rejected
1. Try to sign up with an existing username (e.g., `admin`)
2. Confirm error: "Username already taken"

### Test 4: Password Mismatch Rejected
1. Enter different values for password and confirm password
2. Confirm error: "Passwords do not match"

### Test 5: Validation Enforced
1. Submit with empty username → error
2. Submit with username under 3 chars → error
3. Submit with password under 8 chars → error

### Test 6: Forceful Browsing After Signup
1. Sign up as a new viewer
2. Log in, then try to visit `/dashboard` directly → 403
3. Try `/users` → 403
4. Try `/reports/static` → 403
