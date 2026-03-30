<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail as VerifyEmailBase;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Carbon;

class CustomVerifyEmail extends VerifyEmailBase
{
    protected $user;

    /**
     * Create a new notification instance.
     */
    public function __construct($user = null)
    {
        $this->user = $user;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        // Use the user passed in constructor, or the notifiable
        $user = $this->user ?? $notifiable;
        $verificationUrl = $this->verificationUrl($user);

        return (new MailMessage)
            ->subject('Verifikasi Alamat Email - ' . config('app.name', 'Boilerplate'))
            ->view('emails.verify-email', [
                'verificationUrl' => $verificationUrl,
                'userName' => $user->name,
            ]);
    }
    
    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        return ['mail'];
    }
    
    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable): array
    {
        return [];
    }
    
    /**
     * Get the mail representation of the notification.
     * Override to send to pending_email if exists.
     */
    public function routeNotificationForMail($notifiable)
    {
        // Send to pending_email if exists, otherwise to current email
        return $notifiable->pending_email ?? $notifiable->email;
    }

    /**
     * Get the verification URL for the given notifiable.
     */
    protected function verificationUrl($notifiable): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(60),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );
    }
}
