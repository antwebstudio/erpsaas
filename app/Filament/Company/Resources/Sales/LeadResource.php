<?php

namespace App\Filament\Company\Resources\Sales;

use App\Filament\Company\Resources\Sales\LeadResource\Pages;
use App\Filament\Company\Resources\Sales\ClientResource\RelationManagers;
use App\Filament\Exports\Common\ClientExporter;
use App\Filament\Forms\Components\AddressFields;
use App\Filament\Forms\Components\CreateCurrencySelect;
use App\Filament\Forms\Components\CustomSection;
use App\Filament\Forms\Components\PhoneBuilder;
use App\Filament\Tables\Columns;
use App\Models\Common\Address;
use App\Models\Common\Client;
use App\Models\Common\Lead;
use App\Models\Common\LeadSource;
use App\Models\User;
use App\Utilities\Currency\CurrencyConverter;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class LeadResource extends Resource
{
    use \App\Filament\Traits\HasNavigationPermission;

    protected static ?string $model = Lead::class;

    protected static ?string $modelLabel = 'Lead';

    protected static ?string $slug = 'leads';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('General Information')
                    ->schema([
                        Forms\Components\Group::make()
                            ->columns()
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Lead name')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\Select::make('lead_source_id')
                                    ->label('Lead Source')
                                    ->relationship('leadSource', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->createOptionForm([
                                        Forms\Components\TextInput::make('name')
                                            ->required()
                                            ->maxLength(255),
                                        Forms\Components\Textarea::make('description')
                                            ->maxLength(65535),
                                    ]),
                                Forms\Components\Textarea::make('notes')
                                    ->columnSpanFull(),
                            ]),
                        CustomSection::make('Primary Contact')
                            ->relationship('primaryContact')
                            ->saveRelationshipsUsing(null)
                            ->saveRelationshipsBeforeChildrenUsing(null)
                            ->dehydrated(true)
                            ->contained(false)
                            ->schema([
                                Forms\Components\Hidden::make('is_primary')
                                    ->default(true),
                                Forms\Components\TextInput::make('first_name')
                                    ->label('First name')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('last_name')
                                    ->label('Last name')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('email')
                                    ->label('Email')
                                    ->email()
                                    ->required()
                                    ->columnSpanFull()
                                    ->maxLength(255),
                                PhoneBuilder::make('phones')
                                    ->hiddenLabel()
                                    ->blockLabels(false)
                                    ->default([
                                        ['type' => 'primary'],
                                    ])
                                    ->columnSpanFull()
                                    ->blocks([
                                        Forms\Components\Builder\Block::make('primary')
                                            ->schema([
                                                Forms\Components\TextInput::make('number')
                                                    ->label('Phone')
                                                    ->required()
                                                    ->maxLength(15),
                                            ])->maxItems(1),

                                        Forms\Components\Builder\Block::make('mobile')
                                            ->schema([
                                                Forms\Components\TextInput::make('number')
                                                    ->label('Mobile')
                                                    ->maxLength(15),
                                            ])->maxItems(1),
                                        Forms\Components\Builder\Block::make('toll_free')
                                            ->schema([
                                                Forms\Components\TextInput::make('number')
                                                    ->label('Toll free')
                                                    ->maxLength(15),
                                            ])->maxItems(1),
                                        Forms\Components\Builder\Block::make('fax')
                                            ->schema([
                                                Forms\Components\TextInput::make('number')
                                                    ->label('Fax')
                                                    ->live()
                                                    ->maxLength(15),
                                            ])->maxItems(1),
                                    ])
                                    ->deletable(fn (PhoneBuilder $builder) => $builder->getItemsCount() > 1)
                                    ->reorderable(false)
                                    ->blockNumbers(false)
                                    ->addActionLabel('Add Phone'),
                            ])->columns(),
                        Forms\Components\Repeater::make('secondaryContacts')
                            ->relationship()
                            ->saveRelationshipsUsing(null)
                            ->saveRelationshipsBeforeChildrenUsing(null)
                            ->dehydrated(true)
                            ->hiddenLabel()
                            ->extraAttributes([
                                'class' => 'uncontained',
                            ])
                            ->columns()
                            ->defaultItems(0)
                            ->maxItems(3)
                            ->itemLabel(function (Forms\Components\Repeater $component, array $state): ?string {
                                if ($component->getItemsCount() === 1) {
                                    return 'Secondary Contact';
                                }

                                $firstName = $state['first_name'] ?? null;
                                $lastName = $state['last_name'] ?? null;

                                if ($firstName && $lastName) {
                                    return "{$firstName} {$lastName}";
                                }

                                if ($firstName) {
                                    return $firstName;
                                }

                                return 'Secondary Contact';
                            })
                            ->addActionLabel('Add Contact')
                            ->schema([
                                Forms\Components\TextInput::make('first_name')
                                    ->label('First name')
                                    ->live(onBlur: true)
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('last_name')
                                    ->label('Last name')
                                    ->live(onBlur: true)
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('email')
                                    ->label('Email')
                                    ->email()
                                    ->maxLength(255),
                                PhoneBuilder::make('phones')
                                    ->hiddenLabel()
                                    ->blockLabels(false)
                                    ->default([
                                        ['type' => 'primary'],
                                    ])
                                    ->blocks([
                                        Forms\Components\Builder\Block::make('primary')
                                            ->schema([
                                                Forms\Components\TextInput::make('number')
                                                    ->label('Phone')
                                                    ->maxLength(255),
                                            ])->maxItems(1),
                                    ])
                                    ->addable(false)
                                    ->deletable(false)
                                    ->reorderable(false)
                                    ->blockNumbers(false),
                            ]),
                    ])->columns(1),
                Forms\Components\Section::make('Billing')
                    ->schema([
                        CreateCurrencySelect::make('currency_code')
                            ->softRequired(),
                        CustomSection::make('Billing Address')
                            ->relationship('billingAddress')
                            ->saveRelationshipsUsing(null)
                            ->saveRelationshipsBeforeChildrenUsing(null)
                            ->dehydrated(true)
                            ->contained(false)
                            ->schema([
                                Forms\Components\Hidden::make('type')
                                    ->default('billing'),
                                AddressFields::make()
                                    ->requiredIfAnyFilled(),
                            ])->columns(),
                    ])
                    ->columns(1),
                // Forms\Components\Section::make('Shipping')
                //     ->relationship('shippingAddress')
                //     ->saveRelationshipsUsing(null)
                //     ->saveRelationshipsBeforeChildrenUsing(null)
                //     ->dehydrated(true)
                //     ->schema([
                //         Forms\Components\Hidden::make('type')
                //             ->default('shipping'),
                //         Forms\Components\TextInput::make('recipient')
                //             ->label('Recipient')
                //             ->maxLength(255),
                //         Forms\Components\TextInput::make('phone')
                //             ->label('Phone')
                //             ->maxLength(255),
                //         CustomSection::make('Shipping Address')
                //             ->contained(false)
                //             ->schema([
                //                 Forms\Components\Checkbox::make('same_as_billing')
                //                     ->label('Same as billing address')
                //                     ->live()
                //                     ->afterStateHydrated(function (?Address $record, Forms\Components\Checkbox $component) {
                //                         if (! $record || $record->parent_address_id) {
                //                             return $component->state(true);
                //                         }

                //                         return $component->state(false);
                //                     })
                //                     ->afterStateUpdated(static function (Get $get, Set $set, $state) {
                //                         if ($state) {
                //                             return;
                //                         }

                //                         $billingAddress = $get('../billingAddress');

                //                         $fieldsToSync = [
                //                             'address_line_1',
                //                             'address_line_2',
                //                             'country_code',
                //                             'state_id',
                //                             'city',
                //                             'postal_code',
                //                         ];

                //                         foreach ($fieldsToSync as $field) {
                //                             $set($field, $billingAddress[$field]);
                //                         }
                //                     })
                //                     ->columnSpanFull(),
                //                 AddressFields::make()
                //                     ->visible(static fn (Get $get) => ! $get('same_as_billing')),
                //                 Forms\Components\Textarea::make('notes')
                //                     ->label('Delivery instructions')
                //                     ->maxLength(255)
                //                     ->columnSpanFull(),
                //             ])->columns(),
                //     ])->columns(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Columns::id(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->description(static fn (Client $client) => $client->primaryContact?->full_name),
                Tables\Columns\TextColumn::make('leadSource.name')
                    ->label('Lead Source')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('primaryContact.email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('primaryContact.phones')
                    ->label('Phone')
                    ->toggleable()
                    ->state(static fn (Client $client) => $client->primaryContact?->first_available_phone),
                Tables\Columns\TextColumn::make('billingAddress.address_string')
                    ->label('Billing address')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->listWithLineBreaks(),
                Tables\Columns\TextColumn::make('balance')
                    ->label('Balance')
                    ->getStateUsing(function (Client $client) {
                        return $client->invoices()
                            ->unpaid()
                            ->get()
                            ->sumMoneyInDefaultCurrency('amount_due');
                    })
                    ->coloredDescription(function (Client $client) {
                        $overdue = $client->invoices()
                            ->overdue()
                            ->get()
                            ->sumMoneyInDefaultCurrency('amount_due');

                        if ($overdue <= 0) {
                            return null;
                        }

                        $formattedOverdue = CurrencyConverter::formatCentsToMoney($overdue);

                        return "Overdue: {$formattedOverdue}";
                    })
                    ->sortable(query: function (Builder $query, string $direction) {
                        return $query
                            ->withSum(['invoices' => fn (Builder $query) => $query->unpaid()], 'amount_due')
                            ->orderBy('invoices_sum_amount_due', $direction);
                    })
                    ->currency(convert: false)
                    ->alignEnd(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\ExportAction::make()
                    ->exporter(ClientExporter::class),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ActionGroup::make([
                        Tables\Actions\EditAction::make(),
                        Tables\Actions\ViewAction::make(),
                    ])->dropdown(false),
                    Tables\Actions\Action::make('assign_lead')
                        ->label('Assign Lead')
                        ->icon('heroicon-o-user-circle')
                        ->visible(fn () => Auth::user()->can('assign_lead_sales::lead'))
                        ->form(fn (Lead $record) => [
                            Forms\Components\Placeholder::make('current_owner')
                                ->label('Current Owner')
                                ->content($record->createdBy?->name ?? 'Unknown'),
                            Forms\Components\Select::make('new_owner_id')
                                ->label('Assign To')
                                ->required()
                                ->options(function () {
                                    $company = auth()->user()->currentCompany;
                                    return $company->allUsers()->pluck('name', 'id');
                                })
                                ->searchable(),
                        ])
                        ->modalHeading('Assign Lead')
                        ->modalDescription(fn (Lead $record) => 'Reassigning this lead will change its owner. Current owner: ' . ($record->createdBy?->name ?? 'Unknown'))
                        ->modalSubmitActionLabel('Assign')
                        ->action(function (Lead $record, array $data): void {
                            $record->update(['created_by' => $data['new_owner_id']]);
                        }),
                    Tables\Actions\Action::make('create_quotation')
                        ->label('Create Quotation')
                        ->icon('heroicon-o-document-text')
                        ->url(fn (Lead $record) => route('quotation-builder.switch-and-open', ['client' => $record->id]))
                        ->openUrlInNewTab(false),
                    Tables\Actions\Action::make('create_variation_order')
                        ->label('Create Variation Order')
                        ->icon('heroicon-o-document-plus')
                        ->url(fn (Lead $record) => route('variation-order-builder.switch-and-open', ['client' => $record->id]))
                        ->openUrlInNewTab(false),
                    Tables\Actions\DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                //
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\EstimatesRelationManager::class,
            RelationManagers\VariationOrdersRelationManager::class,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (Auth::user()->can('view_mine_sales::lead') && !Auth::user()->can('view_any_sales::lead')) {
            $query->where('created_by', Auth::id());
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLeads::route('/'),
            'create' => Pages\CreateLead::route('/create'),
            'view' => Pages\ViewLead::route('/{record}'),
            'edit' => Pages\EditLead::route('/{record}/edit'),
        ];
    }
}
