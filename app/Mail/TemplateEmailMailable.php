<?php

namespace App\Mail;

use JeffersonGoncalves\LaravelMail\Mail\TemplateNotificationMailable;

class TemplateEmailMailable extends TemplateNotificationMailable
{
    /**
     * Replace {{ var }} and {{ var.path }} placeholders using the data array,
     * then strip any remaining unresolved placeholders.
     * This avoids Blade::render() which fails on custom dot-notation variables.
     */
    protected function renderBlade(string $template, array $data): string
    {
        return preg_replace_callback('/\{\{\s*([\w.]+)\s*\}\}/', function ($matches) use ($data) {
            $key = $matches[1];

            return array_key_exists($key, $data) ? (string) $data[$key] : '';
        }, $template) ?? $template;
    }
}
