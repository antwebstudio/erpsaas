<?php

namespace App\Mail\Sales;

use App\Models\Accounting\Invoice;
use App\Services\InvoicePdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invoice $invoice,
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
            markdown: 'mail.sales.invoice',
            with: [
                'content' => $this->content,
            ],
        );
    }

    public function attachments(): array
    {
        $pdfService = new InvoicePdfService();
        $pdf = $pdfService->generate($this->invoice);

        return [
            Attachment::fromData(fn () => $pdf, 'Invoice-' . $this->invoice->invoice_number . '.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
