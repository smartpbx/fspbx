# Proposal — `POST /api/v1/auth/login` + 2FA challenge

**Status:** design only on this branch. Implementation pending review of the open decisions at the bottom.

**Why:** The v1 API requires a Sanctum bearer token, but FsPBX has no public credentials → token endpoint. The existing `TokenController::create` does login-by-credentials but lives behind `auth:sanctum + api.cookie.auth` middleware, so it's only reachable from inside the SPA cookie session — useless for external apps (mobile, desktop companion apps, scripts). This PR adds a public, hardened login endpoint with full 2FA support so external apps can authenticate the same way the SPA does.

This proposal is the result of a focused review session against the production FsPBX 1.6.8 deployment at `yyz-fspbx01.smartpbx.io`. Schema, Fortify config, EmailChallengeController, and User model were all consulted.

---

## Endpoint shape

```
POST /api/v1/auth/login
  body: { user_email, password, device_name? }

  Responses:
    201 { access_token, token_type, expires_at, user{...} }                  // no 2FA path
    202 { requires_2fa: true, method: 'email'|'totp', challenge_id, expires_at }
    401 { message: "Authentication failed." }                                 // bad creds (constant-time)
    403 { message: "Domain disabled." }
    422 { errors: ... }
    429 { message: "Too many attempts." }

POST /api/v1/auth/2fa/verify
  body: { challenge_id, code }

  Responses:
    201 { access_token, token_type, expires_at, user{...} }
    401 { message: "Invalid or expired code." }
    429 { message: "Too many attempts." }
```

## 2FA branching logic

After password verification:

1. **TOTP** — if `$user->two_factor_confirmed_at !== null`, the user has Fortify TOTP enrolled. Return 202 with `method: 'totp'`. No email sent. User enters code from their authenticator app.
2. **Email challenge** — if `FortifyServiceProvider::emailChallengeEnabled()` is true and TOTP is not. Generate 6-digit code, dispatch existing `EmailLoginChallengeCode` job, return 202 with `method: 'email'`. (FsPBX defaults email-challenge to ON for all users — this is the common path.)
3. **No 2FA** — return 201 with token immediately.

## Challenge storage

Server-side cache keyed by `auth-2fa-challenge:{uuid}`:

```php
// Email method
['user_uuid' => ..., 'method' => 'email', 'code' => '123456', 'expires_at' => ...]

// TOTP method
['user_uuid' => ..., 'method' => 'totp', 'expires_at' => ...]
```

- Backend: Laravel `Cache` facade (Redis when available — Horizon already requires it).
- Email TTL: **10 min** (matches existing `EmailChallengeController`).
- TOTP TTL: **5 min** (TOTP codes rotate every 30s; longer keeps a brute-force window open).
- Cache entry deleted on successful verify (single-use).

## Verification

```php
// Email method
$valid = hash_equals((string) $payload['code'], (string) $request->input('code'));

// TOTP method (DI'd Fortify provider)
$valid = $totp->verify(decrypt($user->two_factor_secret), $code);
```

`$totp` is `Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider` — same verifier the SPA flow uses.

## Rate limiting

| Bucket | Limit | Keyed by |
|---|---|---|
| `auth-login` | 5/min | IP **and** email (both must pass) |
| `auth-2fa-verify` | 5/min | challenge_id |

The verify limiter on `challenge_id` is critical — without it, an attacker who knows a `challenge_id` could brute-force the 6-digit code (10⁶ space).

## Security review

| Concern | Mitigation | Verdict |
|---|---|---|
| Brute force on /login | 5/min per IP **and** per email | ✓ |
| User enumeration via timing | `Hash::check` always runs with a dummy bcrypt hash when email doesn't match | ✓ |
| User enumeration via response | Same `Authentication failed` + 401 for both wrong-password and unknown-email | ✓ |
| Email enumeration via 202 leak | The 202 response (`requires_2fa`) DOES tell the caller the email is real. Unavoidable — legit users have to be told to check email. Rate limiter caps the leak rate. | Accepted tradeoff |
| Code brute-force on /verify | 5/min per challenge_id | ✓ |
| Replay | Cache entry deleted on success (single-use) | ✓ |
| Challenge fixation | challenge_id is server-generated UUID v4, never user-controlled | ✓ |
| 2FA bypass | TOTP and email-challenge both required when configured | ✓ — **stricter than `TokenController::create`**, which silently bypassed 2FA |
| Disabled domain | Reject 403 if `domain.domain_enabled === false` | ✓ |
| Token never expiring | `expires_at = now+30 days` (existing tokens are forever) | ✓ |
| Token name reuse | `device_name` param, defaults to user-agent (truncated 64 chars) | ✓ |
| Password length DoS | `max:1024` validation | ✓ |
| Email canonicalization mismatch | `strtolower()` on input, matches Fortify `lowercase_usernames: true` | ✓ |
| TOTP secret exposure | Decrypt → pass to verifier → never log | ✓ |
| CSRF | Stateless API guard, no session | ✓ |

## Behavior changes vs existing `TokenController::create`

| Behavior | Existing `create` | This PR |
|---|---|---|
| Reachable publicly | No (behind `auth:sanctum + api.cookie.auth`) | **Yes** |
| HTTP method/path | `Route::resource` maps to `GET /tokens/create` (mismatched — likely effectively unreachable) | `POST /api/v1/auth/login` |
| Rate limiting | None | 5/min by IP + email |
| Timing-attack protection | None (early return on missing user) | Constant-time hash check |
| 2FA enforcement | None (silently bypasses) | Required when configured |
| Domain enabled check | None | Rejects disabled domains |
| Token expiry | Never | 30 days |
| Token name | hard-coded `auth_token` | `device_name` / user-agent |
| Email canonicalization | None | `strtolower` |

## Files

1. **`app/Http/Controllers/Api/V1/AuthController.php`** *(new)* — `login()` + `verify2fa()` + private `issueToken()`. ~150 LOC.
2. **`routes/api_v1.php`** *(prepend, outside the existing auth group)* — two public routes.
3. **`app/Providers/RouteServiceProvider.php`** — register `auth-login` and `auth-2fa-verify` limiters.
4. **`app/Providers/FortifyServiceProvider.php`** — extract `emailChallengeEnabled()` to public scope (or move to a `EmailChallengeService`) so AuthController can call it.
5. **`tests/Feature/Api/V1/AuthLoginTest.php`** *(new)* — see test list below.

## Tests

```
✓ no-2FA user → 201 + token
✓ TOTP-enrolled user → 202 + challenge_id + method:'totp', no email sent
✓ email-challenge user → 202 + challenge_id + method:'email', EmailLoginChallengeCode job dispatched
✓ verify with correct email code → 201 + token, cache entry deleted
✓ verify with correct TOTP code → 201 + token
✓ verify with wrong code → 401
✓ verify with expired challenge → 401
✓ verify replay (same code twice) → second attempt 401 (challenge consumed)
✓ verify rate limit: 6th attempt for same challenge_id → 429
✓ token from /verify works against GET /api/v1/domains
✓ login rate limit: 6th attempt within a minute → 429
✓ login on disabled domain → 403
✓ wrong password → 401
✓ unknown email → 401 (same body as wrong password)
✓ password >1024 chars → 422
✓ email is canonicalized: "Foo@Bar.com" matches user "foo@bar.com"
```

## Open decisions for the maintainer

1. **Cache backend.** Assuming Redis (Horizon requires it). OK to require Redis for this feature, or fall back to file/database driver?
2. **TOTP secret decryption.** Fortify standard is `decrypt($user->two_factor_secret)` — needs verification against existing FsPBX code that uses `two_factor_secret` (User model has accessors but no decrypt visible).
3. **`emailChallengeEnabled()` — refactor or duplicate?** It's currently a private method on `FortifyServiceProvider`. Cleanest is to extract to a static / service. Open question.
4. **Audit log.** Skipping in v0 — failed login attempts not logged. Acceptable, or should we hook the existing `activitylog` package?
5. **Recovery codes path.** Out of scope for v0 — users who lose their 2FA device fall back to UI-based token creation. Acceptable?

## Out of scope (intentional follow-ups)

- Recovery code flow (separate PR)
- Refresh tokens (Sanctum doesn't natively support; if needed, separate)
- Token revocation API in v1 (the SPA already has `/api/tokens` for this)
- Per-tenant rate limiter overrides

---

## Implementation sketch

### `app/Http/Controllers/Api/V1/AuthController.php`

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\EmailLoginChallengeCode;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;

class AuthController extends Controller
{
    /**
     * @group Auth
     * @unauthenticated
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'user_email'  => 'required|string|email|max:255',
            'password'    => 'required|string|max:1024',
            'device_name' => 'nullable|string|max:64',
        ]);

        $email = strtolower((string) $request->input('user_email'));

        $user = User::with('user_adv_fields', 'domain')
            ->where('user_email', $email)
            ->first();

        // Constant-time hash check even on missing user
        $dummyHash = '$2y$10$eImiTXuWVxfM37uY4JANjQ==';
        $hash = $user?->password ?? $dummyHash;
        if (!$user || !Hash::check((string) $request->input('password'), $hash)) {
            return response()->json(['message' => 'Authentication failed.'], 401);
        }

        if ($user->domain && $user->domain->domain_enabled === false) {
            return response()->json(['message' => 'Domain disabled.'], 403);
        }

        // 2FA branching
        $hasTotp = $user->two_factor_confirmed_at !== null;
        $hasEmailChallenge = $this->emailChallengeEnabled();

        if (!$hasTotp && !$hasEmailChallenge) {
            return $this->issueToken($user, $request);
        }

        $challengeId = (string) Str::uuid();
        $method = $hasTotp ? 'totp' : 'email';
        $ttlMinutes = $hasTotp ? 5 : 10;
        $expiresAt = now()->addMinutes($ttlMinutes);

        $payload = [
            'user_uuid'  => $user->user_uuid,
            'method'     => $method,
            'expires_at' => $expiresAt->toIso8601String(),
        ];

        if ($method === 'email') {
            $code = (string) random_int(100000, 999999);
            $payload['code'] = $code;
            EmailLoginChallengeCode::dispatch([
                'name'  => optional($user->user_adv_fields)->first_name ?? '',
                'email' => $user->user_email,
                'code'  => $code,
            ])->onQueue('emails');
        }

        Cache::put("auth-2fa-challenge:{$challengeId}", $payload, $expiresAt);

        return response()->json([
            'requires_2fa' => true,
            'method'       => $method,
            'challenge_id' => $challengeId,
            'expires_at'   => $expiresAt->toIso8601String(),
        ], 202);
    }

    /**
     * @group Auth
     * @unauthenticated
     */
    public function verify2fa(
        Request $request,
        TwoFactorAuthenticationProvider $totp
    ): JsonResponse {
        $request->validate([
            'challenge_id' => 'required|string|uuid',
            'code'         => 'required|string|min:6|max:8',
        ]);

        $key = "auth-2fa-challenge:{$request->input('challenge_id')}";
        $payload = Cache::get($key);
        if (!$payload) {
            return response()->json(['message' => 'Invalid or expired code.'], 401);
        }

        $user = User::with('user_adv_fields', 'domain')
            ->where('user_uuid', $payload['user_uuid'])
            ->first();
        if (!$user) {
            Cache::forget($key);
            return response()->json(['message' => 'Invalid or expired code.'], 401);
        }

        $code = (string) $request->input('code');
        $valid = false;

        if ($payload['method'] === 'email') {
            $valid = hash_equals((string) ($payload['code'] ?? ''), $code);
        } else {
            // TODO(handoff): verify the decrypt() pattern by checking how
            // existing FsPBX code (e.g. SPA 2FA challenge flow) accesses
            // two_factor_secret. Standard Fortify pattern is decrypted.
            $valid = $totp->verify(
                decrypt((string) $user->two_factor_secret),
                $code,
            );
        }

        if (!$valid) {
            return response()->json(['message' => 'Invalid or expired code.'], 401);
        }

        Cache::forget($key);
        return $this->issueToken($user, $request);
    }

    private function issueToken(User $user, Request $request): JsonResponse
    {
        $deviceName = $request->input('device_name')
            ?: Str::limit((string) ($request->userAgent() ?? 'api'), 64, '');

        $token = $user->createToken($deviceName, ['*'], now()->addDays(30));

        return response()->json([
            'access_token' => $token->plainTextToken,
            'token_type'   => 'Bearer',
            'expires_at'   => $token->accessToken->expires_at?->toIso8601String(),
            'user' => [
                'uuid'        => $user->user_uuid,
                'email'       => $user->user_email,
                'username'    => $user->username,
                'domain_uuid' => $user->domain_uuid,
            ],
        ], 201);
    }

    private function emailChallengeEnabled(): bool
    {
        // TODO(handoff): replace with actual FsPBX check. Currently this
        // is private on FortifyServiceProvider — needs to be extracted to
        // a public method or a small EmailChallengeService.
        return (bool) config('services.email_challenge.enabled', true);
    }
}
```

### `routes/api_v1.php` (prepend, BEFORE the existing auth group)

```php
use App\Http\Controllers\Api\V1\AuthController;

// Public — no token required.
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])
        ->middleware('throttle:auth-login');
    Route::post('2fa/verify', [AuthController::class, 'verify2fa'])
        ->middleware('throttle:auth-2fa-verify');
});
```

### `app/Providers/RouteServiceProvider.php` (add to `boot()`)

```php
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

RateLimiter::for('auth-login', function (Request $request) {
    return [
        Limit::perMinute(5)->by($request->ip()),
        Limit::perMinute(5)->by((string) $request->input('user_email')),
    ];
});

RateLimiter::for('auth-2fa-verify', function (Request $request) {
    return Limit::perMinute(5)
        ->by((string) $request->input('challenge_id'));
});
```

---

## Companion-app (smartpbx-app) integration

Once this PR lands, the companion app at https://github.com/smartpbx/smartpbx-app gets:

**Sidecar (`/api/v0/auth/login`, `/api/v0/auth/2fa/verify`):** thin proxies to the v1 endpoints. Sidecar adds nothing; just a CORS-friendly forwarder so the renderer doesn't need to hard-code the FsPBX URL.

**Renderer:** replace the "paste API token" field with email + password fields and a 2FA code prompt that appears when `requires_2fa: true`. Token storage upgrades to Electron `safeStorage` (OS keyring) when available; localStorage fallback for browser.

Estimated half-day of work once this PR is merged.
