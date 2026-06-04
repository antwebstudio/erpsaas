<?php

namespace App\Filament\Company\Resources\Sales\InvoiceResource\Pages;

use App\Concerns\HandlePageRedirect;
use App\Concerns\ManagesLineItems;
use App\Enums\Accounting\EstimateStatus;
use App\Enums\Accounting\PaymentMethod;
use App\Filament\Company\Resources\Sales\InvoiceResource;
use App\Models\Accounting\Invoice;
use App\Models\Banking\BankAccount;
use App\Services\InvoicePdfService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
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
            $this->getSaveFormAction()
                ->visible(! config('erp.hide_invoice_save_button', false)),
            // For contract-draft invoices: mark as paid then generate PDF
            Actions\Action::make('generateInvoice')
                ->label('Generate Invoice')
                ->color('success')
                ->icon('heroicon-m-arrow-down-tray')
                ->visible(function () {
                    $record = $this->getRecord();

                    return $record->isDraft()
                        && $record->estimate_id !== null
                        && $record->estimate?->status === EstimateStatus::Accepted;
                })
                ->modalHeading('Record Payment')
                ->slideOver()
                ->modalWidth(MaxWidth::Large)
                ->form([
                    Forms\Components\DatePicker::make('posted_at')
                        ->label('Payment Date')
                        ->required()
                        ->default(fn () => company_today()->toDateString()),
                    Forms\Components\Select::make('bank_account_id')
                        ->label('Deposit Account')
                        ->required()
                        ->options(function () {
                            return BankAccount::query()
                                ->join('accounts', 'bank_accounts.account_id', '=', 'accounts.id')
                                ->select(['bank_accounts.id', 'accounts.name', 'accounts.currency_code'])
                                ->get()
                                ->mapWithKeys(function ($account) {
                                    $label = $account->name;
                                    if ($account->currency_code) {
                                        $label .= " ({$account->currency_code})";
                                    }

                                    return [$account->id => $label];
                                })
                                ->toArray();
                        })
                        ->searchable(),
                    Forms\Components\Select::make('payment_method')
                        ->label('Payment Method')
                        ->required()
                        ->options(PaymentMethod::class),
                    Forms\Components\Textarea::make('notes')
                        ->label('Notes'),
                ])
                ->action(function (Invoice $record, array $data) {
                    $this->authorizeAccess();
                    $this->beginDatabaseTransaction();

                    try {
                        $formData = $this->form->getState(afterValidate: function () {
                            $this->callHook('afterValidate');
                            $this->callHook('beforeSave');
                        });

                        $formData = $this->mutateFormDataBeforeSave($formData);

                        $this->handleRecordUpdate($record, $formData);

                        $this->form->model($record)->saveRelationships();

                        $this->callHook('afterSave');

                        $record->refresh();

                        if ($record->total <= 0) {
                            $this->rollBackDatabaseTransaction();
                            Notification::make()
                                ->title('Invoice total must be greater than zero to record a payment.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $record->approveDraft();

                        $record->recordPayment([
                            'posted_at' => $data['posted_at'],
                            'bank_account_id' => $data['bank_account_id'],
                            'payment_method' => $data['payment_method'],
                            'amount' => $record->total,
                            'notes' => $data['notes'] ?? null,
                        ]);

                        $this->commitDatabaseTransaction();
                    } catch (\Throwable $exception) {
                        $this->rollBackDatabaseTransaction();

                        throw $exception;
                    }

                    $record->refresh();
                    $record->load(['salesTaxes', 'lineItems', 'client', 'company', 'estimate.templateCompany', 'estimate.company', 'createdBy']);

                    $pdfService = new InvoicePdfService;
                    $pdfOutput = $pdfService->generate($record);

                    $filename = "Invoice-{$record->invoice_number}.pdf";

                    return response()->streamDownload(function () use ($pdfOutput) {
                        echo $pdfOutput;
                    }, $filename);
                }),
            // For all other invoices (not contract draft): save and generate PDF directly
            Actions\Action::make('generateInvoiceSimple')
                ->label('Generate Invoice')
                ->color('success')
                ->icon('heroicon-m-arrow-down-tray')
                ->visible(function () {
                    $record = $this->getRecord();

                    return ! ($record->isDraft()
                        && $record->estimate_id !== null
                        && $record->estimate?->status === EstimateStatus::Accepted);
                })
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

                    $pdfService = new InvoicePdfService;
                    $pdfOutput = $pdfService->generate($record);

                    $filename = "Invoice-{$record->invoice_number}.pdf";

                    return response()->streamDownload(function () use ($pdfOutput) {
                        echo $pdfOutput;
                    }, $filename);
                }),
            Actions\Action::make('saveAndMarkAsPaid')
                ->label('Save & Mark as Paid')
                ->icon('heroicon-m-check-circle')
                ->visible(function () {
                    $record = $this->getRecord();

                    return $record->isDraft()
                        && $record->estimate_id !== null
                        && $record->estimate?->status === EstimateStatus::Accepted;
                })
                ->modalHeading('Record Payment')
                ->slideOver()
                ->modalWidth(MaxWidth::Large)
                ->form([
                    Forms\Components\DatePicker::make('posted_at')
                        ->label('Payment Date')
                        ->required()
                        ->default(fn () => company_today()->toDateString()),
                    Forms\Components\Select::make('bank_account_id')
                        ->label('Deposit Account')
                        ->required()
                        ->options(function () {
                            return BankAccount::query()
                                ->join('accounts', 'bank_accounts.account_id', '=', 'accounts.id')
                                ->select(['bank_accounts.id', 'accounts.name', 'accounts.currency_code'])
                                ->get()
                                ->mapWithKeys(function ($account) {
                                    $label = $account->name;
                                    if ($account->currency_code) {
                                        $label .= " ({$account->currency_code})";
                                    }

                                    return [$account->id => $label];
                                })
                                ->toArray();
                        })
                        ->searchable(),
                    Forms\Components\Select::make('payment_method')
                        ->label('Payment Method')
                        ->required()
                        ->options(PaymentMethod::class),
                    Forms\Components\Textarea::make('notes')
                        ->label('Notes'),
                ])
                ->action(function (Invoice $record, array $data) {
                    $this->authorizeAccess();
                    $this->beginDatabaseTransaction();

                    try {
                        $formData = $this->form->getState(afterValidate: function () {
                            $this->callHook('afterValidate');
                            $this->callHook('beforeSave');
                        });

                        $formData = $this->mutateFormDataBeforeSave($formData);

                        $this->handleRecordUpdate($record, $formData);

                        $this->form->model($record)->saveRelationships();

                        $this->callHook('afterSave');

                        $record->refresh();

                        if ($record->total <= 0) {
                            $this->rollBackDatabaseTransaction();
                            Notification::make()
                                ->title('Invoice total must be greater than zero to record a payment.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $record->approveDraft();

                        $record->recordPayment([
                            'posted_at' => $data['posted_at'],
                            'bank_account_id' => $data['bank_account_id'],
                            'payment_method' => $data['payment_method'],
                            'amount' => $record->total,
                            'notes' => $data['notes'] ?? null,
                        ]);

                        $this->commitDatabaseTransaction();
                    } catch (\Throwable $exception) {
                        $this->rollBackDatabaseTransaction();

                        throw $exception;
                    }

                    Notification::make()
                        ->title('Invoice saved and marked as paid.')
                        ->success()
                        ->send();

                    $this->redirect(InvoiceResource::getUrl('view', ['record' => $record]));
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
