@php
    $record = $getRecord();
    $locale = $locale ?? config('filament-mail.template_editor.default_locale', 'en');
    $html = $record->getHtmlBodyForLocale($locale);
@endphp
<div class="overflow-auto max-h-[600px]">
    @if($html)
        <pre class="text-xs p-4 bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded font-mono whitespace-pre-wrap break-all leading-relaxed">{{ $html }}</pre>
    @else
        <p class="text-sm text-gray-500 dark:text-gray-400 italic">No HTML content available for this locale.</p>
    @endif
</div>
