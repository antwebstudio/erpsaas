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
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class EditEstimateTemplate extends EditRecord
{
    use ManagesLineItems;

    protected static string $resource = EstimateTemplateResource::class;

    public function mount(int | string $record): void
    {
        ini_set('memory_limit', '1024M');
        config(['app.disable_custom_select_relationships' => true]);

        parent::mount($record);
    }

    public function hydrate(): void
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(300);
        config(['app.disable_custom_select_relationships' => true]);
    }

    protected function resolveRecord(int | string $key): \Illuminate\Database\Eloquent\Model
    {
        return parent::resolveRecord($key)->load([
            'lineItemGroups' => fn ($query) => $query->whereNull('parent_id'),
            'lineItemGroups.offeringCategory',
            'lineItemGroups.items.sellableOffering.salesTaxes',
            'lineItemGroups.items.sellableOffering.salesDiscounts',
            'lineItemGroups.items.salesTaxes',
            'lineItemGroups.items.salesDiscounts',
            'lineItemGroups.items.offering',
            'lineItemGroups.children.offeringCategory',
            'lineItemGroups.children.items.sellableOffering.salesTaxes',
            'lineItemGroups.children.items.sellableOffering.salesDiscounts',
            'lineItemGroups.children.items.salesTaxes',
            'lineItemGroups.children.items.salesDiscounts',
            'lineItemGroups.children.items.offering',
        ]);
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
                            ->defaultOrder()
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

                    // Build a map of category ID → [key, group] preserving original array keys
                    // (Filament Repeater uses these keys for extraItemActions — must be UUID strings)
                    $existingGroupsByCat = [];
                    foreach ($this->data['lineItemGroups'] ?? [] as $key => $group) {
                        if (filled($group['offering_category_id'] ?? null)) {
                            $existingGroupsByCat[(int) $group['offering_category_id']] = ['key' => $key, 'data' => $group];
                        }
                    }

                    $newGroupsList = [];
                    $orderCounter = 1;

                    foreach ($sortedCategories as $category) {
                        if (isset($existingGroupsByCat[$category->id])) {
                            // Preserve original key so Filament can resolve extraItemActions
                            $key = $existingGroupsByCat[$category->id]['key'];
                            $group = $existingGroupsByCat[$category->id]['data'];
                            $group['order'] = $orderCounter++;
                            $newGroupsList[$key] = $group;
                        } else {
                            // New group — generate a UUID key so Filament can resolve extraItemActions
                            $newKey = (string) \Illuminate\Support\Str::uuid();
                            $newGroupsList[$newKey] = [
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

                    // Maintain custom groups (without offering_category_id) at the end, preserving their keys
                    foreach ($this->data['lineItemGroups'] ?? [] as $key => $group) {
                        if (blank($group['offering_category_id'] ?? null)) {
                            $group['order'] = $orderCounter++;
                            $newGroupsList[$key] = $group;
                        }
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

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        $this->authorizeAccess();

        $this->beginDatabaseTransaction();

        try {
            // Skip form->getState() — for 300+ items it instantiates thousands of
            // PHP component objects for validation/dehydration, taking 20-40 seconds.
            // $this->data is the Livewire reactive state, which is already the correct
            // dehydrated form state and safe to use directly.
            $this->callHook('beforeSave');

            $data = $this->mutateFormDataBeforeSave($this->data);

            $this->handleRecordUpdate($this->getRecord(), $data);

            $this->callHook('afterSave');

            $this->commitDatabaseTransaction();
        } catch (Halt $exception) {
            $this->rollBackDatabaseTransaction();

            return;
        } catch (\Throwable $exception) {
            $this->rollBackDatabaseTransaction();

            throw $exception;
        }

        $this->rememberData();

        if ($shouldSendSavedNotification) {
            Notification::make()
                ->success()
                ->title(__('filament-panels::resources/pages/edit-record.notifications.saved.title'))
                ->send();
        }

        if ($shouldRedirect) {
            // Redirect instead of letting Livewire diff — re-rendering 300 items
            // to compute the Livewire diff takes as long as the save itself.
            $this->redirect($this->getResource()::getUrl('index'));
        }
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

    protected function afterSave(): void
    {
        $taxKey = $this->record::documentType()->getTaxKey();
        $taxIds = $this->data[$taxKey] ?? null;

        if ($taxIds !== null) {
            $this->record->{$taxKey}()->withoutGlobalScopes()->sync($taxIds);
        }
    }
}
