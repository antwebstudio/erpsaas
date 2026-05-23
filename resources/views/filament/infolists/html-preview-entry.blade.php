@php
    $record = $getRecord();
    if (method_exists($record, 'getHtmlBodyForLocale')) {
        $locale = $locale ?? config('filament-mail.template_editor.default_locale', 'en');
        $html = $record->getHtmlBodyForLocale($locale);
    } else {
        $html = $record->html_body;
    }
@endphp

<div>
    @if($html)
        <iframe
            srcdoc="{!! htmlspecialchars($html, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') !!}"
            style="width: 100%; min-height: 500px; border: 1px solid #e5e7eb; border-radius: 0.5rem; background: #fff;"
        ></iframe>
    @else
        <p class="text-sm text-gray-500 dark:text-gray-400 italic">No HTML content available.</p>
    @endif
</div>
