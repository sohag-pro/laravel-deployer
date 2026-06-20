<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_enable_and_confirm_two_factor(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('two-factor.enable'))->assertRedirect();
        $user->refresh();
        $this->assertNotNull($user->two_factor_secret);
        $this->assertFalse($user->hasTwoFactorEnabled());

        $this->actingAs($user)
            ->post(route('two-factor.confirm'), ['code' => Totp::now($user->two_factor_secret)])
            ->assertSessionHas('recovery_codes');

        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());
    }

    public function test_confirm_rejects_an_invalid_code(): void
    {
        $user = User::factory()->create(['two_factor_secret' => Totp::generateSecret()]);

        $this->actingAs($user)
            ->post(route('two-factor.confirm'), ['code' => '000000'])
            ->assertSessionHasErrors('code');

        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
    }

    public function test_login_with_two_factor_redirects_to_challenge(): void
    {
        $user = $this->userWithTwoFactor();

        $this->post(route('login.attempt'), ['email' => $user->email, 'password' => 'secret-password'])
            ->assertRedirect(route('two-factor.challenge'));

        $this->assertGuest();
    }

    public function test_challenge_completes_login_with_a_valid_code(): void
    {
        $user = $this->userWithTwoFactor();

        $this->post(route('login.attempt'), ['email' => $user->email, 'password' => 'secret-password']);

        $this->post(route('two-factor.verify'), ['code' => Totp::now($user->two_factor_secret)])
            ->assertRedirect('/');

        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_challenge_accepts_a_recovery_code_once(): void
    {
        $user = $this->userWithTwoFactor(['recovery-code-1', 'recovery-code-2']);

        $this->post(route('login.attempt'), ['email' => $user->email, 'password' => 'secret-password']);
        $this->post(route('two-factor.verify'), ['recovery_code' => 'recovery-code-1'])->assertRedirect('/');

        $this->assertAuthenticated();
        $this->assertNotContains('recovery-code-1', $user->fresh()->two_factor_recovery_codes);
    }

    public function test_challenge_rejects_an_invalid_code(): void
    {
        $user = $this->userWithTwoFactor();

        $this->post(route('login.attempt'), ['email' => $user->email, 'password' => 'secret-password']);
        $this->post(route('two-factor.verify'), ['code' => '000000'])->assertSessionHasErrors('code');

        $this->assertGuest();
    }

    public function test_user_can_disable_two_factor(): void
    {
        $user = $this->userWithTwoFactor();

        $this->actingAs($user)->post(route('two-factor.disable'))->assertRedirect();

        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
    }

    protected function userWithTwoFactor(array $recoveryCodes = ['code-a', 'code-b']): User
    {
        return User::factory()->create([
            'password' => Hash::make('secret-password'),
            'two_factor_secret' => Totp::generateSecret(),
            'two_factor_recovery_codes' => $recoveryCodes,
            'two_factor_confirmed_at' => now(),
        ]);
    }
}
