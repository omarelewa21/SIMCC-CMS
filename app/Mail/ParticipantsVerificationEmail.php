<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Models\Competition;

class ParticipantsVerificationEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $competition;

    /**
     * Create a new message instance.
     *
     * @param Competition $competition
     */
    public function __construct(Competition $competition)
    {
        $this->competition = $competition;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Participants Verification Email')
            ->view('emails.participants_verification');
    }
}
