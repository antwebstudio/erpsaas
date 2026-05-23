<?php

namespace App\Filament\Company\Resources\Mail;

use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use JeffersonGoncalves\FilamentMail\Contracts\TemplateEditorContract;
use JeffersonGoncalves\FilamentMail\Resources\MailTemplateResource as BaseMailTemplateResource;
use App\Filament\Company\Resources\Mail\MailTemplateResource\Pages;

class MailTemplateResource extends BaseMailTemplateResource
{
    protected static ?string $tenantOwnershipRelationshipName = 'company';

    protected static ?string $slug = 'mail-templates';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('General')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('key')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->disabled(fn (?string $operation) => $operation === 'edit')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ]),

                Section::make('Content')
                    ->schema([
                        Forms\Components\TextInput::make('subject')
                            ->label('Subject')
                            ->required(),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('insert_lead_profile')
                                    ->label('Insert Lead Profile')
                                    ->options([
                                        '{{ lead.name }}' => 'Lead Name',
                                        '{{ lead.status }}' => 'Lead Status',
                                        '{{ lead.nric }}' => 'Lead NRIC/ID',
                                        '{{ lead.website }}' => 'Lead Website',
                                        '{{ lead.account_number }}' => 'Lead Account Number',
                                        '{{ lead.notes }}' => 'Lead Notes',
                                    ])
                                    ->placeholder('Choose a lead profile variable...')
                                    ->dehydrated(false)
                                    ->native(true)
                                    ->extraAttributes([
                                        'x-on:change' => "
                                            const val = \$event.target.value;
                                            if (val) {
                                                const activeEl = document.activeElement;
                                                if (activeEl && (activeEl.tagName === 'INPUT' || activeEl.tagName === 'TEXTAREA')) {
                                                    const start = activeEl.selectionStart;
                                                    const end = activeEl.selectionEnd;
                                                    const text = activeEl.value;
                                                    activeEl.value = text.substring(0, start) + val + text.substring(end);
                                                    activeEl.focus();
                                                    activeEl.selectionStart = activeEl.selectionEnd = start + val.length;
                                                    activeEl.dispatchEvent(new Event('input', { bubbles: true }));
                                                } else {
                                                    const trix = document.querySelector('trix-editor');
                                                    if (trix) {
                                                        trix.editor.insertString(val);
                                                        trix.focus();
                                                    }
                                                }
                                                \$event.target.value = '';
                                            }
                                        "
                                    ]),

                                Forms\Components\Select::make('insert_lead_contact_address')
                                    ->label('Insert Lead Contact & Address')
                                    ->options([
                                        '{{ lead.contact.first_name }}' => 'Contact First Name',
                                        '{{ lead.contact.last_name }}' => 'Contact Last Name',
                                        '{{ lead.contact.full_name }}' => 'Contact Full Name',
                                        '{{ lead.contact.email }}' => 'Contact Email',
                                        '{{ lead.contact.phone }}' => 'Contact Phone',
                                        '{{ lead.billing_address.address_line_1 }}' => 'Billing Address Line 1',
                                        '{{ lead.billing_address.address_line_2 }}' => 'Billing Address Line 2',
                                        '{{ lead.billing_address.city }}' => 'Billing City',
                                        '{{ lead.billing_address.state }}' => 'Billing State',
                                        '{{ lead.billing_address.postal_code }}' => 'Billing Postal Code',
                                        '{{ lead.billing_address.country }}' => 'Billing Country',
                                        '{{ lead.shipping_address.address_line_1 }}' => 'Shipping Address Line 1',
                                        '{{ lead.shipping_address.address_line_2 }}' => 'Shipping Address Line 2',
                                        '{{ lead.shipping_address.city }}' => 'Shipping City',
                                        '{{ lead.shipping_address.state }}' => 'Shipping State',
                                        '{{ lead.shipping_address.postal_code }}' => 'Shipping Postal Code',
                                        '{{ lead.shipping_address.country }}' => 'Shipping Country',
                                    ])
                                    ->placeholder('Choose a contact/address variable...')
                                    ->dehydrated(false)
                                    ->native(true)
                                    ->extraAttributes([
                                        'x-on:change' => "
                                            const val = \$event.target.value;
                                            if (val) {
                                                const activeEl = document.activeElement;
                                                if (activeEl && (activeEl.tagName === 'INPUT' || activeEl.tagName === 'TEXTAREA')) {
                                                    const start = activeEl.selectionStart;
                                                    const end = activeEl.selectionEnd;
                                                    const text = activeEl.value;
                                                    activeEl.value = text.substring(0, start) + val + text.substring(end);
                                                    activeEl.focus();
                                                    activeEl.selectionStart = activeEl.selectionEnd = start + val.length;
                                                    activeEl.dispatchEvent(new Event('input', { bubbles: true }));
                                                } else {
                                                    const trix = document.querySelector('trix-editor');
                                                    if (trix) {
                                                        trix.editor.insertString(val);
                                                        trix.focus();
                                                    }
                                                }
                                                \$event.target.value = '';
                                            }
                                        "
                                    ]),
                            ]),

                        app(TemplateEditorContract::class)
                            ->getFormField('html_body'),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMailTemplates::route('/'),
            'create' => Pages\CreateMailTemplate::route('/create'),
            'edit' => Pages\EditMailTemplate::route('/{record}/edit'),
            'view' => Pages\ViewMailTemplate::route('/{record}'),
        ];
    }
}
