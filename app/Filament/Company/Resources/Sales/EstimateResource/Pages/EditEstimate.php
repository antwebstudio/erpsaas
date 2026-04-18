<?php

namespace App\Filament\Company\Resources\Sales\EstimateResource\Pages;

use App\Concerns\HandlePageRedirect;
use App\Concerns\ManagesLineItems;
use App\Filament\Company\Resources\Sales\EstimateResource;
use App\Models\Accounting\Estimate;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class EditEstimate extends EditRecord
{
    use HandlePageRedirect;
    use ManagesLineItems;

    protected static string $resource = EstimateResource::class;

    public function mount(int | string $record): void
    {
        ini_set('memory_limit', '1024M');
        config(['app.disable_custom_select_relationships' => true]);

        parent::mount($record);
    }

    public function hydrate(): void
    {
        config(['app.disable_custom_select_relationships' => true]);
    }

    protected function resolveRecord(int | string $key): Model
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
            'lineItemGroups.children.items.purchaseTaxes',
            'lineItemGroups.children.items.purchaseDiscounts',
            'lineItemGroups.children.items.taxes',
            'lineItemGroups.children.items.discounts',
            'lineItemGroups.children.items.offering.salesTaxes',
            'lineItemGroups.children.items.offering.salesDiscounts',
            'lineItemGroups.children.items.offering',
        ]);
    }

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
                        ->extraAttributes(['class' => 'job-scope-checkbox-list'])
                        ->searchable()
                        ->bulkToggleable()
                        ->columns(2)
                        ->options(\App\Models\Common\OfferingCategory::query()
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
                    $sortedCategories = \App\Models\Common\OfferingCategory::whereIn('id', $selectedIds)
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

                    \Filament\Notifications\Notification::make()
                        ->title('Work scopes updated in editor')
                        ->body('Direct changes applied to editor. Click "Save Changes" to persist.')
                        ->success()
                        ->send();
                }),
            Estimate::getPreviewAction(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction(),
            Actions\Action::make('generateQuotation')
                ->label('Generate Quotation')
                ->color('success')
                ->icon('heroicon-m-arrow-down-tray')
                ->modalWidth(MaxWidth::Medium)
                ->modalSubmitActionLabel('Generate')
                ->form(fn (Estimate $record) => ($this->data['template_company_id'] ?? $record->template_company_id) ? [] : [
                    \Filament\Forms\Components\Select::make('template_company_id')
                        ->label('Issue Company')
                        ->relationship('templateCompany', 'name', fn (Builder $query) => $query->where('id', '!=', config('erp.erp_system_company_id')))
                        ->default(fn (Estimate $record) => $record->template_company_id)
                        ->required()
                        ->searchable()
                        ->preload(),
                ])
                ->modalHidden(fn (Estimate $record) => ($this->data['template_company_id'] ?? $record->template_company_id) !== null)
                ->action(function (array $data, Estimate $record) {
                    $templateCompanyId = $data['template_company_id'] ?? ($this->data['template_company_id'] ?? $record->template_company_id);

                    if (! $templateCompanyId) {
                        \Filament\Notifications\Notification::make()
                            ->warning()
                            ->title('Issue Company Required')
                            ->body('Please select an issue company before generating quotation.')
                            ->send();

                        return;
                    }

                    // Update the state with the selected company ID
                    $this->data['template_company_id'] = $templateCompanyId;

                    // Sync the default tax from the selected issue company (add if present, clear if not)
                    $defaultTaxId = \App\Models\Setting\CompanyProfile::withoutGlobalScopes()
                        ->where('company_id', $templateCompanyId)
                        ->value('default_sales_tax_id');
                    $taxKey = \App\Models\Accounting\Estimate::documentType()->getTaxKey();
                    if ($defaultTaxId) {
                        $this->data[$taxKey] ??= [];
                        if (! in_array((string) $defaultTaxId, $this->data[$taxKey])) {
                            $this->data[$taxKey][] = (string) $defaultTaxId;
                        }
                    } else {
                        $this->data[$taxKey] = [];
                    }

                    // Start exactly like the native save to ensure data consistency
                    $this->authorizeAccess();
                    $this->beginDatabaseTransaction();

                    try {
                        // 1. Retrieve & validate the form data
                        $formData = $this->form->getState(afterValidate: function () {
                            $this->callHook('afterValidate');
                            $this->callHook('beforeSave');
                        });

                        // 2. Allow Filament/Page to mutate data before saving
                        $formData = $this->mutateFormDataBeforeSave($formData);

                        // Set the template_company_id and salesTaxes in the form data so it's persisted during save
                        $formData['template_company_id'] = $templateCompanyId;
                        if ($defaultTaxId) {
                            $formData[$taxKey] ??= [];
                            if (! in_array((string) $defaultTaxId, $formData[$taxKey])) {
                                $formData[$taxKey][] = (string) $defaultTaxId;
                            }
                        } else {
                            $formData[$taxKey] = [];
                        }

                        // 3. Update the Model using the page's handler (which also handles line items!)
                        $this->handleRecordUpdate($this->getRecord(), $formData);

                        // 4. ESSENTIAL: Save relationships (belongsToMany, repeater items built natively, etc)
                        $this->form->model($this->getRecord())->saveRelationships();

                        // 5. Post-save hooks (like syncing taxes)
                        $this->callHook('afterSave');

                        $this->commitDatabaseTransaction();
                    } catch (\Throwable $exception) {
                        $this->rollBackDatabaseTransaction();
                        throw $exception;
                    }

                    // Directly sync taxes after transaction to guarantee DB accuracy.
                    // form->getState() can trigger relationship hydration which reloads $this->data[$taxKey]
                    // from the DB, undoing the cleared state — so we enforce the correct value here.
                    $correctTaxIds = $defaultTaxId ? [(string) $defaultTaxId] : [];
                    $record->$taxKey()->withoutGlobalScopes()->sync($correctTaxIds);
                    $this->data[$taxKey] = $correctTaxIds;

                    $this->rememberData();

                    // Refresh the model in-place to get all latest DB attributes & relationships
                    $record = $this->getRecord();
                    $record->refresh();
                    $record->load(['salesTaxes', 'lineItems', 'templateCompany']);

                    // Repopulate the Livewire form UI to reflect calculated backend changes
                    $this->fillForm();

                    // Generate the PDF
                    $pdfService = new \App\Services\EstimatePdfService();
                    $finalPdfOutput = $pdfService->generate($record);

                    $filename = "Quotation-{$record->estimate_number}.pdf";

                    // Return the stream download response directly from the action
                    return response()->streamDownload(function () use ($finalPdfOutput) {
                        echo $finalPdfOutput;
                    }, $filename);
                }),
            $this->getCancelFormAction(),
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

    protected function afterSave(): void
    {
        $taxKey = $this->record::documentType()->getTaxKey();
        $taxIds = $this->data[$taxKey] ?? null;

        if ($taxIds !== null) {
            $this->record->{$taxKey}()->withoutGlobalScopes()->sync($taxIds);
        }
    }
}
