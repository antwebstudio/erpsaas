<?php

namespace App\Filament\Company\Resources\Mail;

use App\Filament\Company\Resources\Mail\MailLogResource\Pages as LocalPages;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\Tabs;
use Filament\Infolists\Components\Tabs\Tab;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Infolists\Infolist;
use JeffersonGoncalves\FilamentMail\Resources\MailLogResource as BaseMailLogResource;
use JeffersonGoncalves\FilamentMail\Resources\MailLogResource\Pages;
use JeffersonGoncalves\FilamentMail\Resources\MailTemplateResource;
use JeffersonGoncalves\LaravelMail\Enums\MailStatus;

class MailLogResource extends BaseMailLogResource
{
    protected static ?string $tenantOwnershipRelationshipName = 'company';

    protected static ?string $slug = 'mail-logs';

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Details')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (MailStatus $state): string => match ($state) {
                                MailStatus::Pending => 'gray',
                                MailStatus::Sent => 'info',
                                MailStatus::Delivered => 'success',
                                MailStatus::Bounced => 'danger',
                                MailStatus::Complained => 'warning',
                                MailStatus::Failed => 'danger',
                            }),
                        TextEntry::make('subject'),
                        TextEntry::make('mailer')
                            ->placeholder('—'),
                        TextEntry::make('provider_message_id')
                            ->label('Provider Message ID')
                            ->placeholder('—')
                            ->columnSpanFull()
                            ->copyable(),
                        TextEntry::make('created_at')
                            ->dateTime(),
                        TextEntry::make('updated_at')
                            ->dateTime(),
                    ]),

                Section::make('Sender & Recipients')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('from')
                            ->getStateUsing(fn ($record) => static::formatAddresses($record->from)),
                        TextEntry::make('to')
                            ->getStateUsing(fn ($record) => static::formatAddresses($record->to)),
                        TextEntry::make('cc')
                            ->getStateUsing(fn ($record) => static::formatAddresses($record->cc))
                            ->placeholder('—'),
                        TextEntry::make('bcc')
                            ->getStateUsing(fn ($record) => static::formatAddresses($record->bcc))
                            ->placeholder('—'),
                        TextEntry::make('reply_to')
                            ->label('Reply To')
                            ->getStateUsing(fn ($record) => static::formatAddresses($record->reply_to))
                            ->placeholder('—'),
                    ]),

                Section::make('Content')
                    ->schema([
                        Tabs::make('content_tabs')
                            ->tabs([
                                Tab::make('Preview')
                                    ->schema([
                                        ViewEntry::make('html_body_preview')
                                            ->view('filament.infolists.html-preview-entry')
                                            ->columnSpanFull(),
                                    ]),
                                Tab::make('HTML')
                                    ->schema([
                                        ViewEntry::make('html_body')
                                            ->view('filament.infolists.html-source-entry')
                                            ->columnSpanFull(),
                                    ]),
                                Tab::make('Plain Text')
                                    ->schema([
                                        TextEntry::make('text_body')
                                            ->prose()
                                            ->placeholder('No plain text version'),
                                    ]),
                            ]),
                    ]),

                Section::make('Headers')
                    ->schema([
                        TextEntry::make('headers')
                            ->getStateUsing(function ($record): string {
                                $state = $record->headers;
                                if (! is_array($state) || empty($state)) {
                                    return '—';
                                }

                                return collect($state)
                                    ->map(fn ($value, $key) => is_int($key) ? $value : "{$key}: {$value}")
                                    ->implode("\n");
                            })
                            ->prose()
                            ->placeholder('—'),
                    ])
                    ->collapsible()
                    ->collapsed(),

                Section::make('Attachments')
                    ->schema([
                        ViewEntry::make('attachments')
                            ->view('filament-mail::components.attachments-entry')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn ($record) => is_array($record->attachments) && count($record->attachments) > 0),

                Section::make('Metadata')
                    ->schema([
                        TextEntry::make('metadata')
                            ->getStateUsing(function ($record): string {
                                $state = $record->metadata;
                                if (! is_array($state) || empty($state)) {
                                    return '—';
                                }

                                return json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                            })
                            ->prose()
                            ->placeholder('—'),
                    ])
                    ->collapsible()
                    ->collapsed(),

                Section::make('Template')
                    ->schema([
                        TextEntry::make('template.name')
                            ->label('Template')
                            ->url(fn ($record) => $record->mail_template_id
                                ? MailTemplateResource::getUrl('edit', ['record' => $record->mail_template_id])
                                : null),
                        TextEntry::make('template.key')
                            ->label('Template Key'),
                    ])
                    ->columns(2)
                    ->visible(fn ($record) => $record->mail_template_id !== null),

                Section::make('Tracking Events')
                    ->schema([
                        ViewEntry::make('tracking_events')
                            ->view('filament-mail::components.tracking-events-entry')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn ($record) => $record->trackingEvents()->exists()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMailLogs::route('/'),
            'view' => LocalPages\ViewMailLog::route('/{record}'),
        ];
    }
}
