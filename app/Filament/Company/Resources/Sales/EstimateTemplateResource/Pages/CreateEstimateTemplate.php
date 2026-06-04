<?php

namespace App\Filament\Company\Resources\Sales\EstimateTemplateResource\Pages;

use App\Concerns\ManagesLineItems;
use App\Filament\Company\Resources\Sales\EstimateTemplateResource;
use App\Models\Accounting\Estimate;
use App\Models\Common\OfferingCategory;
use Filament\Actions;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Database\Eloquent\Model;

class CreateEstimateTemplate extends CreateRecord
{
    use ManagesLineItems;

    protected static string $resource = EstimateTemplateResource::class;

    public function mount(): void
    {
        ini_set('memory_limit', '1024M');

        parent::mount();
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['is_template'] = true;

        return $data;
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
                            $currentGroups = collect($this->data['lineItemGroups'] ?? []);

                            return $currentGroups->whereNotNull('offering_category_id')
                                ->pluck('offering_category_id')
                                ->toArray();
                        })
                        ->required(),
                ])
                ->action(function (array $data) {
                    $selectedIds = array_map('intval', $data['categories']);

                    // Fetch categories in correct order (Nested Set order for parents)
                    $sortedCategories = OfferingCategory::whereIn('id', $selectedIds)
                        ->defaultOrder()
                        ->get();

                    // Current state of groups in the form
                    $currentGroups = collect($this->data['lineItemGroups'] ?? []);

                    // Map existing groups by category ID for easy lookup
                    $existingGroupsByCat = $currentGroups->filter(fn ($g) => filled($g['offering_category_id'] ?? null))
                        ->keyBy(fn ($g) => (int) $g['offering_category_id']);

                    $newGroupsList = [];
                    $orderCounter = 1;

                    // We need company_id for new groups
                    $companyId = $this->data['company_id'] ?? auth()->user()->currentCompany->id;

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
                                'company_id' => $companyId,
                                'offering_category_id' => $category->id,
                                'name' => $category->name,
                                'order' => $orderCounter++,
                                'items' => [],
                                'children' => [],
                            ];
                        }
                    }

                    // Maintain custom groups (without offering_category_id) at the end
                    $customGroups = $currentGroups->filter(fn ($g) => blank($g['offering_category_id'] ?? null))
                        ->sortBy('order');

                    foreach ($customGroups as $group) {
                        $group['order'] = $orderCounter++;
                        $newGroupsList[] = $group;
                    }

                    // Update form state
                    $this->data['lineItemGroups'] = $newGroupsList;

                    Notification::make()
                        ->title('Work scopes updated in editor')
                        ->body('Direct changes applied to editor. Click "Create" to save.')
                        ->success()
                        ->send();
                }),
        ];
    }

    public function getMaxContentWidth(): MaxWidth | string | null
    {
        return MaxWidth::Full;
    }

    protected function handleRecordCreation(array $data): Model
    {
        /** @var Estimate $record */
        $record = parent::handleRecordCreation($data);

        $lineItems = collect($data['lineItemGroups'] ?? []);

        $this->handleLineItems($record, $lineItems);

        $totals = $this->updateDocumentTotals($record, $data);

        $record->update($totals);

        return $record;
    }
}
