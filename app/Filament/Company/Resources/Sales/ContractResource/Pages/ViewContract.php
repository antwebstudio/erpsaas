<?php

namespace App\Filament\Company\Resources\Sales\ContractResource\Pages;

use App\Enums\Accounting\DocumentType;
use App\Filament\Company\Resources\Sales\AllClientResource;
use App\Filament\Company\Resources\Sales\ClientResource;
use App\Filament\Company\Resources\Sales\ContractResource;
use App\Filament\Company\Resources\Sales\LeadResource;
use App\Filament\Infolists\Components\BannerEntry;
use App\Filament\Infolists\Components\DocumentPreview;
use App\Models\Accounting\Estimate;
use Filament\Actions;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\IconPosition;
use Illuminate\Support\HtmlString;

class ViewContract extends ViewRecord
{
    protected static string $resource = ContractResource::class;

    protected static bool $isScopedToTenant = false;

    protected $listeners = [
        'refresh' => '$refresh',
    ];

    protected function getHeaderActions(): array
    {
        $invoiceActions = ContractResource::buildPaymentInvoiceActions(Actions\Action::class, $this->record->company_id);

        return [
            Actions\ActionGroup::make([
                Actions\ActionGroup::make([
                    ContractResource::getViewPaymentsAction(Actions\Action::class),

                    Actions\Action::make('complete')
                        ->label('Mark as Completed')
                        ->icon('heroicon-o-check-circle')
                        ->color('info')
                        ->visible(fn () => ! $this->record->isCompleted() && auth()->user()->canForCompany($this->record->company_id, 'complete_sales::contract'))
                        ->requiresConfirmation()
                        ->action(function () {
                            $this->record->complete();
                            \Filament\Notifications\Notification::make()
                                ->title('Contract marked as completed')
                                ->success()
                                ->send();
                        }),

                    Actions\Action::make('uncomplete')
                        ->label('Mark as Active')
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->color('warning')
                        ->visible(fn () => $this->record->isCompleted() && auth()->user()->canForCompany($this->record->company_id, 'complete_sales::contract'))
                        ->requiresConfirmation()
                        ->action(function () {
                            $this->record->uncomplete();
                            \Filament\Notifications\Notification::make()
                                ->title('Contract marked as active')
                                ->success()
                                ->send();
                        }),

                    Actions\Action::make('archive')
                        ->label('Archive')
                        ->icon('heroicon-o-archive-box')
                        ->color('warning')
                        ->visible(fn () => ! $this->record->isArchived() && ! $this->record->isCompleted() && auth()->user()->can('update', $this->record))
                        ->requiresConfirmation()
                        ->action(function () {
                            $this->record->archive();
                            \Filament\Notifications\Notification::make()
                                ->title('Contract archived')
                                ->success()
                                ->send();
                        }),

                    Actions\Action::make('unarchive')
                        ->label('Unarchive')
                        ->icon('heroicon-o-archive-box-arrow-down')
                        ->color('success')
                        ->visible(fn () => $this->record->isArchived() && auth()->user()->can('update', $this->record))
                        ->requiresConfirmation()
                        ->action(function () {
                            $this->record->unarchive();
                            \Filament\Notifications\Notification::make()
                                ->title('Contract unarchived')
                                ->success()
                                ->send();
                        }),

                    Estimate::getPreviewAction(Actions\Action::class, 'preview_contract'),
                    Estimate::getDownloadMergedPdfAction(Actions\Action::class, 'download_contract'),
                ])->dropdown(false),
            ])
                ->label('Actions')
                ->button()
                ->outlined()
                ->dropdownPlacement('bottom-end')
                ->icon('heroicon-m-chevron-down')
                ->iconPosition(IconPosition::After),

            Actions\ActionGroup::make($invoiceActions)
                ->label('Generate Invoice')
                ->button()
                ->outlined()
                ->visible(fn () => ! $this->record->isCompleted() && auth()->user()->canForCompany($this->record->company_id, 'create_sales::invoice'))
                ->dropdownPlacement('bottom-end')
                ->icon('heroicon-m-chevron-down')
                ->iconPosition(IconPosition::After),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Contract Details')
                    ->columns(4)
                    ->schema([
                        Grid::make(1)
                            ->schema([
                                TextEntry::make('company.name')
                                    ->label('Issuing Company'),
                                TextEntry::make('reference_number')
                                    ->label('Reference Number'),
                                TextEntry::make('estimate_number')
                                    ->label('Estimate Number'),
                                TextEntry::make('status')
                                    ->badge(),
                                TextEntry::make('clientAndLead.name')
                                    ->label('Client')
                                    ->url(static function (Estimate $record) {
                                        if (! $record->client_id) {
                                            return null;
                                        }

                                        return AllClientResource::getUrl('view', ['record' => $record->client_id]);
                                    })
                                    ->link(),
                                TextEntry::make('date')
                                    ->date(),
                                TextEntry::make('accepted_at')
                                    ->label('Accepted at')
                                    ->date(),
                            ])->columnSpan(1),
                        DocumentPreview::make()
                            ->type(DocumentType::Estimate),
                    ]),
            ]);
    }
}
