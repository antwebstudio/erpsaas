@php
    $record = $getRecord();
    $locale = $locale ?? config('filament-mail.template_editor.default_locale', 'en');

    $html = null;
    $subject = null;

    try {
        $htmlBody = $record->getHtmlBodyForLocale($locale);
        $subjectTemplate = $record->getSubjectForLocale($locale);

        // Build variable examples from the record's variables array
        $variables = [];
        if ($record->variables) {
            foreach ($record->variables as $variable) {
                $name = $variable['name'] ?? null;
                $example = $variable['example'] ?? "[$name]";
                if ($name) {
                    $variables[$name] = $example;
                }
            }
        }

        // Replace {{ var }} and {{ var.path }} placeholders with example values
        $replacer = function (?string $template) use ($variables): ?string {
            if ($template === null) {
                return null;
            }
            return preg_replace_callback('/\{\{\s*([\w.]+)\s*\}\}/', function ($matches) use ($variables) {
                $key = $matches[1];
                return $variables[$key] ?? "[$key]";
            }, $template);
        };

        $html = $replacer($htmlBody);
        $subject = $replacer($subjectTemplate);

        if ($html !== null && config('laravel-mail.templates.inline_css', true)) {
            $html = (new \TijsVerkoyen\CssToInlineStyles\CssToInlineStyles)->convert($html);
        }
    } catch (\Throwable $e) {
        $html = null;
        $subject = null;
    }
@endphp

<div class="fi-mail-space-y-3">
    @if($subject)
        <div class="fi-mail-subject">
            <strong>Subject:</strong> {{ $subject }}
        </div>
    @endif

    @if($html)
        <iframe
            srcdoc="{{ e($html) }}"
            class="fi-mail-preview-iframe"
            style="min-height: 400px; max-width: {{ config('filament-mail.preview.max_width', '800px') }};"
            @if(config('filament-mail.preview.sandbox', true))
                sandbox="allow-same-origin"
            @endif
        ></iframe>
    @else
        <p class="fi-mail-empty">
            No content available for this locale. Make sure the template has content and the locale is configured.
        </p>
    @endif
</div>
