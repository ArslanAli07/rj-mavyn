<?php

namespace App\Mail;

use App\Models\User;
use App\Services\StoreIdentity;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class ForgotPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $resetUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(public User $user, public string $token, public ?array $brand = null, public ?string $messageId = null)
    {
        $this->resetUrl = url(route('password.reset', [
            'token' => $this->token,
            'email' => $this->user->getEmailForPasswordReset(),
        ], false));
    }

    public function headers(): Headers
    {
        return new Headers(messageId: $this->messageId);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address((string) config('mail.from.address'), ($this->brand ?? StoreIdentity::snapshot())['name']),
            subject: 'Reset Password - '.($this->brand ?? StoreIdentity::snapshot())['name'],
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.forgot-password',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
