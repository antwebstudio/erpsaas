<?php

namespace App\Filament\Company\Resources\Sales\InvoiceResource\Pages;

use App\Concerns\HandlePageRedirect;
use App\Concerns\ManagesLineItems;
use App\Filament\Company\Resources\Sales\InvoiceResource;
use App\Models\Accounting\Invoice;
use App\Services\InvoicePdfService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Database\Eloquent\Model;

class EditInvoice extends EditRecord
{
    use HandlePageRedirect;
    use ManagesLineItems;

    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction(),
            Actions\Action::make('generateInvoice')
                ->label('Generate Invoice')
                ->color('success')
                ->icon('heroicon-m-arrow-down-tray')
                ->action(function (Invoice $record) {
                    $this->authorizeAccess();
                    $this->beginDatabaseTransaction();

                    try {
                        $formData = $this->form->getState(afterValidate: function () {
                            $this->callHook('afterValidate');
                            $this->callHook('beforeSave');
                        });

                        $formData = $this->mutateFormDataBeforeSave($formData);

                        $this->handleRecordUpdate($this->getRecord(), $formData);

                        $this->form->model($this->getRecord())->saveRelationships();

                        $this->callHook('afterSave');

                        $this->commitDatabaseTransaction();
                    } catch (\Throwable $exception) {
                        $this->rollBackDatabaseTransaction();
                        throw $exception;
                    }

                    $record = $this->getRecord();
                    $record->refresh();
                    $record->load(['salesTaxes', 'lineItems', 'client', 'company', 'estimate.templateCompany', 'estimate.company', 'createdBy']);

                    $this->fillForm();

                    $pdfService = new InvoicePdfService();
                    $pdfOutput = $pdfService->generate($record);

                    $filename = "Invoice-{$record->invoice_number}.pdf";

                    return response()->streamDownload(function () use ($pdfOutput) {
                        echo $pdfOutput;
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
        /** @var Invoice $record */
        $lineItems = collect($data['lineItemGroups'] ?? []);

        $this->deleteRemovedLineItems($record, $lineItems);

        $this->handleLineItems($record, $lineItems);

        $totals = $this->updateDocumentTotals($record, $data);

        $data = array_merge($data, $totals);

        $record = parent::handleRecordUpdate($record, $data);

        if ($record->approved_at && $record->approvalTransaction) {
            $record->updateApprovalTransaction();
        }

        return $record;
    }
}
