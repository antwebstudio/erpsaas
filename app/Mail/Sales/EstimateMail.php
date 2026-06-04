<?php

namespace App\Mail\Sales;

use App\Models\Accounting\Estimate;
use App\Services\EstimatePdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EstimateMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Estimate $estimate,
        public string $content,
        public string $subjectString
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectString,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.sales.estimate',
            with: [
                'content' => $this->content,
            ],
        );
    }

    public function attachments(): array
    {
        $pdfService = new EstimatePdfService;
        $pdf = $pdfService->generate($this->estimate);

        return [
            Attachment::fromData(fn () => $pdf, 'Estimate-' . $this->estimate->estimate_number . '.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
