<?php

namespace App\Filament\Company\Resources\Sales\LeadResource\Pages;

use App\Filament\Company\Resources\Sales\ClientResource;
use App\Filament\Company\Resources\Sales\ClientResource\RelationManagers;
use App\Filament\Company\Resources\Sales\LeadResource;
use App\Filament\User\Pages\CreateQuotation;
use App\Filament\User\Pages\CreateVariationOrder;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\IconPosition;
use Illuminate\Contracts\Support\Htmlable;

class ViewLead extends ViewRecord
{
    protected static string $resource = LeadResource::class;

    public function getRelationManagers(): array
    {
        return [
            // RelationManagers\InvoicesRelationManager::class,
            // RelationManagers\RecurringInvoicesRelationManager::class,
            RelationManagers\EstimatesRelationManager::class,
            RelationManagers\VariationOrdersRelationManager::class,
        ];
    }

    public function getTitle(): string | Htmlable
    {
        return $this->record->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create_quotation')
                ->label('Create Quotation')
                ->icon('heroicon-o-document-text')
                ->url(fn () => route('filament.user.pages.create-quotation', [
                    'tenant' => \Filament\Facades\Filament::getTenant(),
                    'client' => $this->record->id,
                ])),
            EditAction::make()
                ->label('Edit lead')
                ->outlined(),
            ActionGroup::make([
                ActionGroup::make([
                    Action::make('createQuotation')
                        ->label('Create quotation')
                        ->icon('heroicon-m-document-duplicate')
                        ->url(CreateQuotation::getUrl(['client' => $this->record->getKey()], panel: 'user')),
                    Action::make('createVariationOrder')
                        ->label('Create variation order')
                        ->icon('heroicon-m-document-text')
                        ->url(CreateVariationOrder::getUrl(['client' => $this->record->getKey()], panel: 'user')),
                ])->dropdown(false),
                DeleteAction::make(),
            ])
                ->label('Actions')
                ->button()
                ->outlined()
                ->dropdownPlacement('bottom-end')
                ->icon('heroicon-m-chevron-down')
                ->iconPosition(IconPosition::After),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            // ClientResource\Widgets\InvoiceOverview::class,
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('General')
                    ->columns()
                    ->schema([
                        TextEntry::make('primaryContact.full_name')
                            ->label('Primary contact'),
                        TextEntry::make('primaryContact.email')
                            ->label('Primary email'),
                        TextEntry::make('primaryContact.first_available_phone')
                            ->label('Primary phone'),
                        // TextEntry::make('website')
                        //     ->label('Website')
                        //     ->url(static fn ($state) => $state, true)
                        //     ->link(),
                    ]),
                // Section::make('Additional Details')
                //     ->columns()
                //     ->schema([
                //         TextEntry::make('billingAddress.address_string')
                //             ->label('Billing address')
                //             ->listWithLineBreaks(),
                //         TextEntry::make('shippingAddress.address_string')
                //             ->label('Shipping address')
                //             ->listWithLineBreaks(),
                //         TextEntry::make('notes')
                //             ->label('Delivery instructions'),
                //     ]),
            ]);
    }
}
