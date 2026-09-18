<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeEmployeeMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $employee;
    public string $plainPassword;

    /**
     * Create a new message instance.
     */
    public function __construct(User $employee, string $plainPassword = '')
    {
        $this->employee = $employee;
        $this->plainPassword = $plainPassword;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome aboard, ' . $this->employee->name . ' — your employee account is ready',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.welcome_employee',
            with: [
                'employee' => $this->employee,
                'password' => $this->plainPassword,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
