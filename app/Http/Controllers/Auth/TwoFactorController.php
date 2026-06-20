<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\Totp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TwoFactorController extends Controller
{
    /**
     * Two-factor settings page.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        $uri = null;
        if ($user->two_factor_secret && ! $user->hasTwoFactorEnabled()) {
            $uri = Totp::uri($user->two_factor_secret, $user->email, config('app.name'));
        }

        return view('auth.two-factor', [
            'user' => $user,
            'provisioningUri' => $uri,
            'recoveryCodes' => session('recovery_codes'),
        ]);
    }

    /**
     * Generate a secret and begin enrollment (not yet confirmed).
     */
    public function enable(Request $request): RedirectResponse
    {
        $user = $request->user();

        $user->forceFill([
            'two_factor_secret' => Totp::generateSecret(),
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return redirect()->route('two-factor.index')
            ->with('success', 'Scan the QR code with your authenticator app, then confirm a code to finish.');
    }

    /**
     * Confirm enrollment by verifying a code; issues recovery codes.
     */
    public function confirm(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string']]);
        $user = $request->user();

        if (! $user->two_factor_secret || ! Totp::verify($user->two_factor_secret, $request->input('code'))) {
            return back()->withErrors(['code' => 'The provided code was invalid.']);
        }

        $codes = collect(range(1, 8))
            ->map(fn () => Str::lower(Str::random(5).'-'.Str::random(5)))
            ->all();

        $user->forceFill([
            'two_factor_recovery_codes' => $codes,
            'two_factor_confirmed_at' => now(),
        ])->save();

        return redirect()->route('two-factor.index')
            ->with('success', 'Two-factor authentication is now enabled. Save your recovery codes.')
            ->with('recovery_codes', $codes);
    }

    /**
     * Disable two-factor authentication.
     */
    public function disable(Request $request): RedirectResponse
    {
        $request->user()->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return redirect()->route('two-factor.index')->with('success', 'Two-factor authentication disabled.');
    }
}
