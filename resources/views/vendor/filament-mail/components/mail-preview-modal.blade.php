<div class="fi-mail-space-y-4">
    @if($subject ?? null)
        <div class="fi-mail-subject">
            <strong>Subject:</strong> {{ $subject }}
        </div>
    @endif

    @if($html ?? null)
        <iframe
            srcdoc="{!! htmlspecialchars($html, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') !!}"
            class="fi-mail-preview-iframe"
            style="min-height: 600px; max-width: {{ config('filament-mail.preview.max_width', '800px') }}; width: 100%;"
        ></iframe>
    @else
        <p class="fi-mail-empty">No HTML content available.</p>
    @endif
</div>
