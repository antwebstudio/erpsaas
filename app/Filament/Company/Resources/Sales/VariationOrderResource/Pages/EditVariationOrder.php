<?php

namespace App\Filament\Company\Resources\Sales\VariationOrderResource\Pages;

use App\Concerns\HandlePageRedirect;
use App\Concerns\ManagesLineItems;
use App\Filament\Company\Resources\Sales\VariationOrderResource;
use App\Models\Accounting\VariationOrder;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class EditVariationOrder extends EditRecord
{
    use HandlePageRedirect;
    use ManagesLineItems;

    protected static string $resource = VariationOrderResource::class;

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

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('backToBuilder')
                ->label('Back to Page Builder')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn () => \App\Filament\User\Pages\CreateVariationOrder::getUrl([
                    'variation_order_id' => $this->getRecord()->id,
                    'client' => $this->getRecord()->client_id,
                ], panel: 'user')),
            Actions\Action::make('selectWork')
                ->label('Select Work')
                ->icon('heroicon-o-briefcase')
                ->form([
                    Forms\Components\CheckboxList::make('categories')
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
                ->action(function (array $data, VariationOrder $record) {
                    $selectedIds = array_map('intval', $data['categories']);

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

                    $this->data['lineItemGroups'] = $newGroupsList;

                    \Filament\Notifications\Notification::make()
                        ->title('Work scopes updated in editor')
                        ->body('Direct changes applied to editor. Click "Save Changes" to persist.')
                        ->success()
                        ->send();
                }),
            VariationOrder::getPreviewAction(),
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction(),
            Actions\Action::make('generateVariationOrder')
                ->label('Generate Variation Order')
                ->color('success')
                ->icon('heroicon-m-arrow-down-tray')
                ->modalWidth(MaxWidth::Medium)
                ->modalSubmitActionLabel('Generate')
                ->form(fn (VariationOrder $record) => ($this->data['template_company_id'] ?? $record->template_company_id) ? [] : [
                    Forms\Components\Select::make('template_company_id')
                        ->label('Issue Company')
                        ->relationship('templateCompany', 'name', fn (Builder $query) => $query->where('id', '!=', config('erp.erp_system_company_id')))
                        ->default(fn (VariationOrder $record) => $record->template_company_id)
                        ->required()
                        ->searchable()
                        ->preload(),
                ])
                ->modalHidden(fn (VariationOrder $record) => ($this->data['template_company_id'] ?? $record->template_company_id) !== null)
                ->action(function (array $data, VariationOrder $record) {
                    $templateCompanyId = $data['template_company_id'] ?? ($this->data['template_company_id'] ?? $record->template_company_id);

                    if (! $templateCompanyId) {
                        \Filament\Notifications\Notification::make()
                            ->warning()
                            ->title('Issue Company Required')
                            ->body('Please select an issue company before generating variation order.')
                            ->send();

                        return;
                    }

                    $this->data['template_company_id'] = $templateCompanyId;

                    $defaultTaxId = \App\Models\Setting\CompanyProfile::withoutGlobalScopes()
                        ->where('company_id', $templateCompanyId)
                        ->value('default_sales_tax_id');
                    $taxKey = VariationOrder::documentType()->getTaxKey();
                    if ($defaultTaxId) {
                        $this->data[$taxKey] ??= [];
                        if (! in_array((string) $defaultTaxId, $this->data[$taxKey])) {
                            $this->data[$taxKey][] = (string) $defaultTaxId;
                        }
                    } else {
                        $this->data[$taxKey] = [];
                    }

                    $this->authorizeAccess();
                    $this->beginDatabaseTransaction();

                    try {
                        $formData = $this->form->getState(afterValidate: function () {
                            $this->callHook('afterValidate');
                            $this->callHook('beforeSave');
                        });

                        $formData = $this->mutateFormDataBeforeSave($formData);

                        $formData['template_company_id'] = $templateCompanyId;
                        if ($defaultTaxId) {
                            $formData[$taxKey] ??= [];
                            if (! in_array((string) $defaultTaxId, $formData[$taxKey])) {
                                $formData[$taxKey][] = (string) $defaultTaxId;
                            }
                        } else {
                            $formData[$taxKey] = [];
                        }

                        $this->handleRecordUpdate($this->getRecord(), $formData);

                        $this->form->model($this->getRecord())->saveRelationships();

                        $this->callHook('afterSave');

                        $this->commitDatabaseTransaction();
                    } catch (\Throwable $exception) {
                        $this->rollBackDatabaseTransaction();
                        throw $exception;
                    }

                    $correctTaxIds = $defaultTaxId ? [(string) $defaultTaxId] : [];
                    $record->$taxKey()->withoutGlobalScopes()->sync($correctTaxIds);
                    $this->data[$taxKey] = $correctTaxIds;

                    $this->rememberData();

                    $record = $this->getRecord();
                    $record->refresh();
                    $record->load(['salesTaxes', 'lineItems', 'templateCompany']);

                    $this->fillForm();

                    $pdfService = new \App\Services\VariationOrderPdfService();
                    $finalPdfOutput = $pdfService->generate($record);

                    $filename = "VariationOrder-{$record->vo_number}.pdf";

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

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var VariationOrder $record */
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
