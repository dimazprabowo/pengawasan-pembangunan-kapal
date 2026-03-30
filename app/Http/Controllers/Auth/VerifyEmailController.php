<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

class VerifyEmailController extends Controller
{
    /**
     * Mark the authenticated user's email address as verified.
     */
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        $user = $request->user();

        // If user has pending email, activate it
        if ($user->pending_email) {
            $user->email = $user->pending_email;
            $user->pending_email = null;
            $user->email_verified_at = now();
            $user->save();

            event(new Verified($user));

            return redirect()->intended(route('dashboard', absolute: false).'?verified=1')
                ->with('status', 'Email berhasil diverifikasi dan diaktifkan!');
        }

        // Normal verification for new users
        if ($user->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard', absolute: false).'?verified=1');
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return redirect()->intended(route('dashboard', absolute: false).'?verified=1');
    }
}
