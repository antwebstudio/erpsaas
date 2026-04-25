<?php

namespace App\Filament\Company\Resources\Sales\VariationOrderResource\Pages;

use App\Enums\Accounting\DocumentType;
use App\Filament\Company\Resources\Sales\AllClientResource;
use App\Filament\Company\Resources\Sales\ClientResource;
use App\Filament\Company\Resources\Sales\EstimateResource;
use App\Filament\Company\Resources\Sales\VariationOrderResource;
use App\Filament\Infolists\Components\BannerEntry;
use App\Filament\Infolists\Components\DocumentPreview;
use App\Models\Accounting\VariationOrder;
use Filament\Actions;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\HtmlString;

class ViewVariationOrder extends ViewRecord
{
    protected static string $resource = VariationOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->label('Edit variation order')
                ->outlined(),
            Actions\ActionGroup::make([
                Actions\ActionGroup::make([
                    VariationOrder::getApproveDraftAction(),
                    VariationOrder::getMarkAsSentAction(),
                    VariationOrder::getMarkAsRejectedAction(),
                    VariationOrder::getPreviewAction(),
                    VariationOrder::getPrintDocumentAction(),
                    VariationOrder::getDownloadMergedPdfAction(),
                    VariationOrder::getReplicateAction(),
                ])->dropdown(false),
                Actions\DeleteAction::make(),
            ])
                ->label('Actions')
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
                BannerEntry::make('inactiveAdjustments')
                    ->label('Inactive adjustments')
                    ->warning()
                    ->icon('heroicon-o-exclamation-triangle')
                    ->visible(fn (VariationOrder $record) => $record->hasInactiveAdjustments() && $record->canBeApproved())
                    ->columnSpanFull()
                    ->description(function (VariationOrder $record) {
                        $inactiveAdjustments = collect();

                        foreach ($record->lineItems as $lineItem) {
                            foreach ($lineItem->adjustments as $adjustment) {
                                if ($adjustment->isInactive() && $inactiveAdjustments->doesntContain($adjustment->name)) {
                                    $inactiveAdjustments->push($adjustment->name);
                                }
                            }
                        }

                        $adjustmentsList = $inactiveAdjustments->map(static function ($name) {
                            return "<span class='font-medium'>{$name}</span>";
                        })->join(', ');

                        $output = "<p class='text-sm'>This variation order contains inactive adjustments that need to be addressed before approval: {$adjustmentsList}</p>";

                        return new HtmlString($output);
                    }),
                Section::make('Variation Order Details')
                    ->columns(4)
                    ->schema([
                        Grid::make(1)
                            ->schema([
                                TextEntry::make('vo_number')
                                    ->label('VO #'),
                                TextEntry::make('status')
                                    ->badge(),
                                TextEntry::make('company.name')
                                    ->label('Issuing Company')
                                    ->getStateUsing(fn (VariationOrder $record) => $record->templateCompany?->name ?? $record->company->name),
                                TextEntry::make('client.name')
                                    ->label('Client')
                                    ->url(static fn (VariationOrder $record) => $record->client_id ? AllClientResource::getUrl('view', ['record' => $record->client_id]) : null)
                                    ->link(),
                                TextEntry::make('estimate.estimate_number')
                                    ->label('Linked Quotation')
                                    ->placeholder('—')
                                    ->url(fn (VariationOrder $record) => $record->estimate_id
                                        ? EstimateResource::getUrl('view', ['record' => $record->estimate_id])
                                        : null
                                    )
                                    ->color('primary')
                                    ->visible(fn (VariationOrder $record) => filled($record->estimate_id)),
                                TextEntry::make('expiry_date')
                                    ->label('Expiration date')
                                    ->hidden(fn () => ! config('erp.show_expiry_date', true))
                                    ->asRelativeDay(),
                                TextEntry::make('approved_at')
                                    ->label('Approved at')
                                    ->date(),
                                TextEntry::make('last_sent_at')
                                    ->label('Last sent')
                                    ->date(),
                                TextEntry::make('accepted_at')
                                    ->label('Accepted at')
                                    ->date(),
                                TextEntry::make('rejected_at')
                                    ->label('Rejected at')
                                    ->date()
                                    ->visible(fn (VariationOrder $record) => filled($record->rejected_at)),
                            ])->columnSpan(1),
                        DocumentPreview::make()
                            ->type(DocumentType::VariationOrder),
                    ]),
            ]);
    }

}
