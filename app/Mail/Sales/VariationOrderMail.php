<?php

namespace App\Mail\Sales;

use App\Models\Accounting\VariationOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Services\VariationOrderPdfService;

class VariationOrderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public VariationOrder $variationOrder,
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
            markdown: 'mail.sales.variation_order',
            with: [
                'content' => $this->content,
            ],
        );
    }

    public function attachments(): array
    {
        $pdfService = new VariationOrderPdfService();
        $pdf = $pdfService->generate($this->variationOrder);

        return [
            Attachment::fromData(fn () => $pdf, 'Variation-Order-' . $this->variationOrder->vo_number . '.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
