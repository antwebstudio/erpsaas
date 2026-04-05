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
                    
                    // Fetch categories in correct order (Nested Set order for parents)
                    $sortedCategories = \App\Models\Common\OfferingCategory::whereIn('id', $selectedIds)
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
                ->form([
                    \Filament\Forms\Components\Select::make('template_company_id')
                        ->label('Issue Company')
                        ->relationship('templateCompany', 'name')
                        ->default(fn (Estimate $record) => $record->template_company_id)
                        ->required()
                        ->searchable()
                        ->preload(),
                ])
                ->action(function (array $data) {
                    // Update the state with the selected company ID
                    $this->data['template_company_id'] = $data['template_company_id'];

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

                    $this->rememberData();
                    
                    // Refresh the model in-place to get all latest DB attributes & relationships
                    $this->getRecord()->refresh();

                    // Repopulate the Livewire form UI to reflect calculated backend changes
                    $this->fillForm();

                    /** @var Estimate $record */
                    $record = $this->getRecord();

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
