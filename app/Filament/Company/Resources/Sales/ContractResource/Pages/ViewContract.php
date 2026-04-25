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
        $invoiceActions = ContractResource::buildPaymentInvoiceActions(Actions\Action::class);

        return [
            Actions\ActionGroup::make([
                Actions\ActionGroup::make([
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
