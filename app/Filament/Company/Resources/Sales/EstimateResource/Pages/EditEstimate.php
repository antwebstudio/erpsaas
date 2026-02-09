<?php

namespace App\Filament\Company\Resources\Sales\EstimateResource\Pages;

use App\Concerns\HandlePageRedirect;
use App\Concerns\ManagesLineItems;
use App\Filament\Company\Resources\Sales\EstimateResource;
use App\Models\Accounting\Estimate;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Database\Eloquent\Model;

class EditEstimate extends EditRecord
{
    use HandlePageRedirect;
    use ManagesLineItems;

    protected static string $resource = EstimateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('backToBuilder')
                ->label('Back to Page Builder')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn () => \App\Filament\User\Pages\CreateQuotation::getUrl([
                    'estimate_id' => $this->getRecord()->id,
                    'client' => $this->getRecord()->client_id,
                ], panel: 'user')),
            Actions\Action::make('selectWork')
                ->label('Select Work')
                ->icon('heroicon-o-briefcase')
                ->form([
                    \Filament\Forms\Components\CheckboxList::make('categories')
                        ->label('Work Scopes')
                        ->searchable()
                        ->bulkToggleable()
                        ->columns(2)
                        ->options(\App\Models\Common\OfferingCategory::query()
                            ->whereNull('parent_id')
                            ->pluck('name', 'id'))
                        ->default(fn (Estimate $record) => $record->lineItemGroups()
                            ->whereNotNull('offering_category_id')
                            ->pluck('offering_category_id')
                            ->toArray())
                        ->required(),
                ])
                ->action(function (array $data, Estimate $record) {
                    $selectedIds = array_map('intval', $data['categories']);
                    $currentGroups = $record->lineItemGroups()->whereNotNull('offering_category_id')->get();
                    $currentCategoryIds = $currentGroups->pluck('offering_category_id')->toArray();

                    // Remove deselected
                    $toRemove = $currentGroups->whereNotIn('offering_category_id', $selectedIds);
                    foreach ($toRemove as $group) {
                        $group->items()->delete();
                        $group->delete();
                    }

                    // Add newly selected
                    $toAddIds = array_diff($selectedIds, $currentCategoryIds);
                    
                    if (!empty($toAddIds)) {
                        $maxOrder = $record->lineItemGroups()->max('order') ?? 0;
                        $categoriesToAdd = \App\Models\Common\OfferingCategory::whereIn('id', $toAddIds)->get();

                        foreach ($categoriesToAdd as $category) {
                            $record->lineItemGroups()->create([
                                'company_id' => $record->company_id,
                                'offering_category_id' => $category->id,
                                'name' => $category->name,
                                'order' => ++$maxOrder,
                            ]);
                        }
                    }

                    \Filament\Notifications\Notification::make()
                        ->title('Work scopes updated')
                        ->success()
                        ->send();
                    
                    $this->refreshFormData(['lineItemGroups']);
                }),
            Estimate::getPreviewAction(),
            Actions\DeleteAction::make(),
        ];
    }

    public function getMaxContentWidth(): MaxWidth | string | null
    {
        return MaxWidth::Full;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Estimate $record */
        $lineItems = collect($data['lineItemGroups'] ?? []);

        $this->deleteRemovedLineItems($record, $lineItems);

        $this->handleLineItems($record, $lineItems);

        $totals = $this->updateDocumentTotals($record, $data);

        $data = array_merge($data, $totals);

        return parent::handleRecordUpdate($record, $data);
    }
}
