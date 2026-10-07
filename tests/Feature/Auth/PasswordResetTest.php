<?php

namespace Tests\Feature\Auth;

use App\Livewire\Pages\Auth\ForgotPassword;
use App\Livewire\Pages\Auth\ResetPassword as ResetPasswordPage;
use App\Models\User;
use App\Notifications\CustomResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response
            ->assertSeeLivewire(ForgotPassword::class)
            ->assertStatus(200);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        Livewire::test(ForgotPassword::class)
            ->set('email', $user->email)
            ->call('sendResetLink');

        Notification::assertSentOnDemand(CustomResetPassword::class);
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        Livewire::test(ForgotPassword::class)
            ->set('email', $user->email)
            ->call('sendResetLink');

        Notification::assertSentOnDemand(CustomResetPassword::class, function ($notification) {
            $response = $this->get('/reset-password/'.$notification->token);

            $response
                ->assertSeeLivewire(ResetPasswordPage::class)
                ->assertStatus(200);

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        Livewire::test(ForgotPassword::class)
            ->set('email', $user->email)
            ->call('sendResetLink');

        Notification::assertSentOnDemand(CustomResetPassword::class, function ($notification) use ($user) {
            $component = Livewire::withQueryParams(['email' => $user->email])
                ->test(ResetPasswordPage::class, ['token' => $notification->token])
                ->set('password', 'password')
                ->set('password_confirmation', 'password');

            $component->call('resetPassword');

            $component
                ->assertRedirect('/login')
                ->assertHasNoErrors();

            return true;
        });
    }
}
