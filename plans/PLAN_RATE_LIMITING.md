# Plan: Rate Limiting

## Overview
Add IP-based rate limiting to the login endpoint and all API endpoints to prevent brute-force password attacks and API abuse/DoS.

## Current State
- No rate limiting anywhere — login can be attempted unlimited times
- API endpoints (`/api/static`, `/api/performance`, `/api/activity`) have no throttling
- A single client could exhaust server resources with rapid requests

## Implementation

### Step 1: Create Rate Limiter Using Database
**File:** `app/core/RateLimiter.php`

Use a MySQL table to track request counts per IP per endpoint. This avoids needing Redis/Memcached (not available on shared hosting).

Create class `RateLimiter` with:
- `__construct(PDO $pdo)` — Accept the existing PDO connection
- `isRateLimited(string $ip, string $endpoint, int $maxAttempts, int $windowSeconds): bool` — Check if the IP has exceeded `$maxAttempts` within the last `$windowSeconds` for the given endpoint
- `recordAttempt(string $ip, string $endpoint): void` — Insert a new attempt record
- `clearAttempts(string $ip, string $endpoint): void` — Clear attempts (used after successful login)

### Step 2: Create Database Table
**File:** `database/rate_limits.sql` (reference SQL, run manually)

```sql
CREATE TABLE rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    endpoint VARCHAR(100) NOT NULL,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_endpoint_time (ip_address, endpoint, attempted_at)
);
```

The index on `(ip_address, endpoint, attempted_at)` ensures the lookup query is fast.

### Step 3: Apply to Login
**File:** `app/controllers/AuthController.php`

In the login POST handler, before checking credentials:
1. Instantiate `RateLimiter` with the existing PDO connection
2. Call `isRateLimited($_SERVER['REMOTE_ADDR'], 'login', 5, 900)` — 5 attempts per 15 minutes
3. If rate limited: set `$_SESSION['error'] = 'Too many login attempts. Please try again in 15 minutes.'` and redirect back to `/login`
4. If not rate limited: call `recordAttempt()` and proceed with normal login flow
5. On successful login: call `clearAttempts()` to reset the counter

### Step 4: Apply to API Endpoints
**File:** `api/router.php`

At the top of the API router, before dispatching to handlers:
1. Instantiate `RateLimiter`
2. Call `isRateLimited($_SERVER['REMOTE_ADDR'], 'api', 100, 60)` — 100 requests per minute
3. If rate limited: return `429 Too Many Requests` with JSON body `{"error": "Rate limit exceeded. Try again later."}`
4. If not rate limited: call `recordAttempt()` and continue routing


## Files Created/Modified
| File | Action |
|------|--------|
| `app/core/RateLimiter.php` | **Create** — Rate limiting logic |
| `database/rate_limits.sql` | **Create** — Table creation SQL |
| `app/controllers/AuthController.php` | **Modify** — Add login rate limiting |
| `api/router.php` | **Modify** — Add API rate limiting |

## Manual Testing

### Test 1: Login Rate Limiting Triggers
1. Go to `/login`
2. Submit 5 incorrect login attempts in a row (wrong password)
3. On the 6th attempt, confirm you see "Too many login attempts. Please try again in 15 minutes."
4. Confirm even correct credentials are rejected while rate limited

### Test 2: Successful Login Resets Counter
1. Submit 3 incorrect login attempts
2. Submit a correct login
3. Log out
4. Submit 3 more incorrect login attempts
5. Confirm you are NOT rate limited (counter was reset on successful login)

### Test 3: API Rate Limiting Triggers
1. Use curl or a script to send 101 rapid GET requests to `/api/static`
2. Confirm that after 100 requests, subsequent requests return HTTP 429 with the error JSON

### Test 4: Rate Limit Window Expires
1. Trigger login rate limiting (5 failed attempts)
2. Wait 15 minutes (or manually delete records from `rate_limits` table for faster testing)
3. Confirm login attempts work again

### Test 5: Different IPs Are Independent
1. From one IP, trigger rate limiting on login
2. From a different IP (or using a proxy), confirm login still works
3. Verify the `rate_limits` table shows separate entries per IP

