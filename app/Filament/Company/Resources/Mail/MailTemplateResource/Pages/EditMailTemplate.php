<?php

namespace App\Filament\Company\Resources\Mail\MailTemplateResource\Pages;

use App\Filament\Company\Resources\Mail\MailTemplateResource;
use App\Mail\TemplateEmailMailable;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Mail;
use JeffersonGoncalves\FilamentMail\Resources\MailTemplateResource\Pages\EditMailTemplate as BaseEditMailTemplate;
use TijsVerkoyen\CssToInlineStyles\CssToInlineStyles;

class EditMailTemplate extends BaseEditMailTemplate
{
    protected static string $resource = MailTemplateResource::class;

    protected function getHeaderActions(): array
    {
        $locales = config('filament-mail.template_editor.locales', ['en']);

        return [
            Actions\LocaleSwitcher::make(),

            Actions\Action::make('preview')
                ->label('Preview')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->modalContent(function () {
                    $locale = app()->getLocale();
                    [$html, $subject] = $this->renderTemplateForPreview($locale);

                    return view('filament-mail::components.mail-preview-modal', [
                        'html' => $html,
                        'subject' => $subject,
                    ]);
                })
                ->modalHeading('Template Preview')
                ->modalWidth('7xl')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close'),

            Actions\Action::make('sendTest')
                ->label('Send Test')
                ->icon('heroicon-o-paper-airplane')
                ->color('info')
                ->form([
                    TextInput::make('email')
                        ->label('Recipient Email')
                        ->email()
                        ->required(),
                    Select::make('locale')
                        ->label('Locale')
                        ->options(collect($locales)->mapWithKeys(fn ($l) => [$l => strtoupper($l)])->all())
                        ->default(config('filament-mail.template_editor.default_locale', 'en')),
                ])
                ->action(function (array $data) {
                    $exampleData = collect($this->record->variables ?? [])
                        ->mapWithKeys(fn ($var) => [$var['name'] => $var['example'] ?? ''])
                        ->all();

                    if (! empty($data['locale'])) {
                        app()->setLocale($data['locale']);
                    }

                    Mail::to($data['email'])
                        ->send((new TemplateEmailMailable($this->record->key, $exampleData))
                            ->useTemplate($this->record));

                    Notification::make()
                        ->title('Test email sent')
                        ->body("Sent to {$data['email']}")
                        ->success()
                        ->send();
                }),

            Actions\Action::make('duplicate')
                ->label('Duplicate')
                ->icon('heroicon-o-document-duplicate')
                ->color('gray')
                ->requiresConfirmation()
                ->action(function () {
                    $clone = $this->record->replicate(['id']);
                    $clone->key = $this->record->key . '-copy';
                    $clone->name = $this->record->name . ' (Copy)';
                    $clone->save();

                    Notification::make()
                        ->title('Template duplicated')
                        ->success()
                        ->send();

                    return redirect(MailTemplateResource::getUrl('edit', ['record' => $clone]));
                }),

            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    private function renderTemplateForPreview(string $locale): array
    {
        try {
            $htmlBody = $this->record->getHtmlBodyForLocale($locale);
            $subjectBody = $this->record->getSubjectForLocale($locale);

            $variables = collect($this->record->variables ?? [])
                ->mapWithKeys(fn ($var) => [($var['name'] ?? '') => ($var['example'] ?? '')])
                ->filter(fn ($v, $k) => $k !== '')
                ->all();

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
            $subject = $replacer($subjectBody);

            if ($html !== null && config('laravel-mail.templates.inline_css', true)) {
                $html = (new CssToInlineStyles)->convert($html);
            }

            return [$html, $subject];
        } catch (\Throwable) {
            return [null, null];
        }
    }
}
