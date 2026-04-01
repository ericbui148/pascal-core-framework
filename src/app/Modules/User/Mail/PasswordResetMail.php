<?php

namespace App\Modules\User\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $token,
        public readonly string $email,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Reset Your Password — Pascal Platform');
    }

    public function content(): Content
    {
        $url = url("/reset-password?token={$this->token}&email=" . urlencode($this->email));

        return new Content(
            htmlString: "
                <h2>Reset Your Password</h2>
                <p>Click the link below to reset your password. This link expires in 60 minutes.</p>
                <p><a href='{$url}' style='background:#6366f1;color:white;padding:12px 24px;text-decoration:none;border-radius:6px;display:inline-block'>Reset Password</a></p>
                <p>Or copy this link: {$url}</p>
                <p>If you did not request a password reset, ignore this email.</p>
            "
        );
    }
}
