<?php

namespace App\Filament\Company\Resources\Sales\EstimateTemplateResource\Pages;

use App\Concerns\ManagesLineItems;
use App\Filament\Company\Resources\Sales\EstimateTemplateResource;
use App\Models\Accounting\Estimate;
use App\Models\Common\OfferingCategory;
use Filament\Actions;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Database\Eloquent\Model;

class EditEstimateTemplate extends EditRecord
{
    use ManagesLineItems;

    protected static string $resource = EstimateTemplateResource::class;

    public function mount(int | string $record): void
    {
        ini_set('memory_limit', '1024M');

        parent::mount($record);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('selectWork')
                ->label('Select Work')
                ->icon('heroicon-o-briefcase')
                ->form([
                    CheckboxList::make('categories')
                        ->label('Work Scopes')
                        ->extraAttributes(['class' => 'job-scope-checkbox-list'])
                        ->searchable()
                        ->bulkToggleable()
                        ->columns(2)
                        ->options(OfferingCategory::query()
                            ->whereNull('parent_id')
                            ->pluck('name', 'id'))
                        ->default(function () {
                            $selected = [];
                            foreach ($this->data['lineItemGroups'] ?? [] as $group) {
                                if (filled($group['offering_category_id'] ?? null)) {
                                    $selected[] = (int) $group['offering_category_id'];
                                }
                            }
                            return $selected;
                        })
                        ->required(),
                ])
                ->action(function (array $data, Estimate $record) {
                    $selectedIds = array_map('intval', $data['categories']);
                    
                    // Fetch categories in correct order (Nested Set order for parents)
                    $sortedCategories = OfferingCategory::whereIn('id', $selectedIds)
                        ->defaultOrder()
                        ->get();

                    // Current state of groups in the form
                    $currentGroups = collect($this->data['lineItemGroups'] ?? []);
                    
                    // Map existing groups by category ID for easy lookup
                    // We only care about groups that have an offering_category_id
                    $existingGroupsByCat = $currentGroups->filter(fn($g) => filled($g['offering_category_id'] ?? null))
                        ->keyBy(fn($g) => (int) $g['offering_category_id']);
                    
                    $newGroupsList = [];
                    $orderCounter = 1;

                    foreach ($sortedCategories as $category) {
                        if ($existingGroupsByCat->has($category->id)) {
                            // Update existing group's order
                            $group = $existingGroupsByCat->get($category->id);
                            $group['order'] = $orderCounter++;
                            $newGroupsList[] = $group;
                        } else {
                            // Create new group "stub" with correct order
                            $newGroupsList[] = [
                                'id' => null,
                                'company_id' => $record->company_id,
                                'offering_category_id' => $category->id,
                                'name' => $category->name,
                                'order' => $orderCounter++,
                                'items' => [],
                                'children' => [],
                            ];
                        }
                    }
                    
                    // Maintain custom groups (without offering_category_id) at the end
                    $customGroups = $currentGroups->filter(fn($g) => blank($g['offering_category_id'] ?? null))
                        ->sortBy('order');
                        
                    foreach ($customGroups as $group) {
                        $group['order'] = $orderCounter++;
                        $newGroupsList[] = $group;
                    }

                    // Update form state
                    $this->data['lineItemGroups'] = $newGroupsList;

                    Notification::make()
                        ->title('Work scopes updated in editor')
                        ->body('Direct changes applied to editor. Click "Save Changes" to persist.')
                        ->success()
                        ->send();
                }),
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
