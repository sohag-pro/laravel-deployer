<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Totp;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    /**
     * Failed attempts allowed per email + IP before lockout.
     */
    protected const MAX_ATTEMPTS = 5;

    /**
     * Lockout window in seconds once the limit is hit.
     */
    protected const DECAY_SECONDS = 60;

    /**
     * Show the login form.
     */
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect('/');
        }

        return view('login');
    }

    /**
     * Handle a login attempt.
     */
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $this->ensureIsNotRateLimited($request);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            RateLimiter::hit($this->throttleKey($request), self::DECAY_SECONDS);

            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'These credentials do not match our records.']);
        }

        RateLimiter::clear($this->throttleKey($request));

        // When 2FA is enabled, defer login until the code is verified.
        if ($user->hasTwoFactorEnabled()) {
            $request->session()->put('auth.2fa.user_id', $user->id);
            $request->session()->put('auth.2fa.remember', $request->boolean('remember'));

            return redirect()->route('two-factor.challenge');
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->intended('/');
    }

    /**
     * Show the two-factor challenge after a correct password.
     */
    public function showChallenge(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('auth.2fa.user_id')) {
            return redirect()->route('login');
        }

        return view('auth.two-factor-challenge');
    }

    /**
     * Verify a TOTP code or recovery code and complete login.
     */
    public function challenge(Request $request): RedirectResponse
    {
        $userId = $request->session()->get('auth.2fa.user_id');
        if (! $userId) {
            return redirect()->route('login');
        }

        $this->ensureIsNotRateLimited($request);

        $user = User::find($userId);
        $code = trim((string) $request->input('code'));
        $recovery = trim((string) $request->input('recovery_code'));

        $passed = ($code !== '' && Totp::verify((string) $user->two_factor_secret, $code))
            || ($recovery !== '' && $user->useRecoveryCode($recovery));

        if (! $passed) {
            RateLimiter::hit($this->throttleKey($request), self::DECAY_SECONDS);

            return back()->withErrors(['code' => 'The provided two-factor code was invalid.']);
        }

        RateLimiter::clear($this->throttleKey($request));

        $remember = (bool) $request->session()->pull('auth.2fa.remember', false);
        $request->session()->forget('auth.2fa.user_id');

        Auth::login($user, $remember);
        $request->session()->regenerate();

        return redirect()->intended('/');
    }

    /**
     * Block further attempts once too many have failed for this email + IP.
     */
    protected function ensureIsNotRateLimited(Request $request): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey($request), self::MAX_ATTEMPTS)) {
            return;
        }

        Event::dispatch(new Lockout($request));

        $seconds = RateLimiter::availableIn($this->throttleKey($request));

        throw ValidationException::withMessages([
            'email' => "Too many login attempts. Please try again in {$seconds} seconds.",
        ]);
    }

    /**
     * Rate-limit key scoped to the submitted email and client IP.
     */
    protected function throttleKey(Request $request): string
    {
        return Str::transliterate(Str::lower((string) $request->input('email')).'|'.$request->ip());
    }

    /**
     * Log the user out.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
