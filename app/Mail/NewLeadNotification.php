<?php

namespace App\Mail;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewLeadNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Lead $lead)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Nuevo lead: {$this->lead->organization} ({$this->lead->sectorLabel()})",
            replyTo: [
                new Address($this->lead->email, $this->lead->name),
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.leads.new',
            with: [
                'lead' => $this->lead,
                'adminUrl' => config('app.url') . '/admin/leads/' . $this->lead->id,
            ],
        );
    }
}
