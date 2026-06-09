<?php

namespace App\Sidecar;

use Spatie\LaravelPdf\Drivers\PdfDriver;
use Spatie\LaravelPdf\PdfOptions;

class WeasyPrintDriver implements PdfDriver
{
    /**
     * Compile HTML to PDF on AWS Lambda via Sidecar WeasyPrint.
     */
    public function generatePdf(string $html, ?string $headerHtml, ?string $footerHtml, PdfOptions $options): string
    {
        // 1. Merge running headers and footers into the HTML body so WeasyPrint can render them
        $html = $this->mergeHeaderFooter($html, $headerHtml, $footerHtml);

        // 2. Call Sidecar WeasyPrint Lambda function
        $response = WeasyPrint::execute([
            'html' => $html,
        ]);

        $responseBody = $response->body();
        
        if (!isset($responseBody['statusCode']) || $responseBody['statusCode'] !== 200) {
            $errorMsg = $responseBody['error'] 
                ?? $responseBody['errorMessage'] 
                ?? (is_array($responseBody) ? json_encode($responseBody) : (string)$responseBody)
                ?? 'Unknown error';
            throw new \Exception("WeasyPrint Lambda failed: " . $errorMsg);
        }

        return base64_decode($responseBody['body']);
    }

    /**
     * Save the compiled PDF to the local path.
     */
    public function savePdf(string $html, ?string $headerHtml, ?string $footerHtml, PdfOptions $options, string $path): void
    {
        $pdfContent = $this->generatePdf($html, $headerHtml, $footerHtml, $options);
        file_put_contents($path, $pdfContent);
    }

    /**
     * Merge the header and footer blocks into the html body, mimicking the Spatie WeasyPrintDriver.
     */
    protected function mergeHeaderFooter(string $html, ?string $headerHtml, ?string $footerHtml): string
    {
        if (! $headerHtml && ! $footerHtml) {
            return $html;
        }

        $headerBlock = $headerHtml
            ? '<div class="pdf-header">'.$headerHtml.'</div>'
            : '';

        $footerBlock = $footerHtml
            ? '<div class="pdf-footer">'.$footerHtml.'</div>'
            : '';

        if (preg_match('/<body([^>]*)>/i', $html, $matches)) {
            return preg_replace(
                '/<body([^>]*)>/i',
                '<body$1>'.$headerBlock.$footerBlock,
                $html,
            );
        }

        return $headerBlock.$footerBlock.$html;
    }
}
