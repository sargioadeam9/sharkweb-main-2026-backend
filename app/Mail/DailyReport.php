<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The nightly KPI digest.
 *
 * NOT QUEUED, for the same reason as every other mail here: shared hosting runs no queue
 * worker, so a queued message would sit in the jobs table until someone noticed it was
 * never delivered.
 *
 * The whole payload is a plain array built by `report:daily`. The mailable does no
 * querying of its own — that keeps "what the report says" in one place, so the test
 * button and the 23:30 run cannot drift apart.
 */
class DailyReport extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $report
     */
    public function __construct(
        public readonly array $report,
    ) {}

    public function envelope(): Envelope
    {
        // Leads in the subject line: the inbox preview alone should answer "did anything
        // come in today", which is the only question this mail exists to answer.
        $leads = (int) ($this->report['leads_today'] ?? 0);

        return new Envelope(
            subject: "Daily report — {$this->report['date']} — {$leads} lead".($leads === 1 ? '' : 's'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.daily-report',
            with: ['report' => $this->report],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
