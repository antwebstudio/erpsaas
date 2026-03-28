<?php

namespace App\Enums\Accounting;

use App\DTO\DocumentLabelDTO;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Str;

enum DocumentType: string implements HasIcon, HasLabel
{
    case Invoice = 'invoice';
    case Bill = 'bill';
    case Estimate = 'estimate';
    case Contract = 'contract';
    case RecurringInvoice = 'recurring_invoice';
    case VariationOrder = 'variation_order';

    public const DEFAULT = self::Invoice->value;

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Invoice, self::Bill, self::Estimate, self::Contract => $this->name,
            self::RecurringInvoice => 'Recurring Invoice',
            self::VariationOrder => 'Variation Order',
        };
    }

    public function getPluralLabel(): ?string
    {
        return Str::plural($this->getLabel());
    }

    public function getIcon(): ?string
    {
        return match ($this->value) {
            self::Invoice->value, self::RecurringInvoice->value => 'heroicon-o-document-duplicate',
            self::Bill->value => 'heroicon-o-clipboard-document-list',
            self::Estimate->value, self::Contract->value, self::VariationOrder->value => 'heroicon-o-document-text',
        };
    }

    public function getTaxKey(): string
    {
        return match ($this) {
            self::Invoice, self::RecurringInvoice, self::Estimate, self::Contract, self::VariationOrder => 'salesTaxes',
            self::Bill => 'purchaseTaxes',
        };
    }

    public function getDiscountKey(): string
    {
        return match ($this) {
            self::Invoice, self::RecurringInvoice, self::Estimate, self::Contract, self::VariationOrder => 'salesDiscounts',
            self::Bill => 'purchaseDiscounts',
        };
    }

    public function getLabels(): DocumentLabelDTO
    {
        return match ($this) {
            self::Invoice => new DocumentLabelDTO(
                title: self::Invoice->getLabel(),
                number: 'Invoice Number',
                referenceNumber: 'P.O/S.O Number',
                date: 'Invoice Date',
                dueDate: 'Payment Due',
                amountDue: 'Amount Due',
            ),
            self::RecurringInvoice => new DocumentLabelDTO(
                title: self::RecurringInvoice->getLabel(),
                number: 'Invoice Number',
                referenceNumber: 'P.O/S.O Number',
                date: 'Invoice Date',
                dueDate: 'Payment Due',
                amountDue: 'Amount Due',
            ),
            self::Estimate => new DocumentLabelDTO(
                title: self::Estimate->getLabel(),
                number: 'Estimate Number',
                referenceNumber: 'Reference Number',
                date: 'Estimate Date',
                dueDate: 'Expiration Date',
                amountDue: null,
            ),
            self::Bill => new DocumentLabelDTO(
                title: self::Bill->getLabel(),
                number: 'Bill Number',
                referenceNumber: 'P.O/S.O Number',
                date: 'Bill Date',
                dueDate: 'Payment Due',
                amountDue: 'Amount Due',
            ),
            self::Contract => new DocumentLabelDTO(
                title: self::Contract->getLabel(),
                number: 'Contract Number',
                referenceNumber: 'Reference Number',
                date: 'Date',
                dueDate: 'Expiry Date',
                amountDue: null,
            ),
            self::VariationOrder => new DocumentLabelDTO(
                title: self::VariationOrder->getLabel(),
                number: 'VO Number',
                referenceNumber: 'Reference Number',
                date: 'Date',
                dueDate: 'Expiry Date',
                amountDue: null,
            ),
        };
    }

    public function getDefaultPrefix(): ?string
    {
        return match ($this) {
            self::Invoice => 'INV-',
            self::Estimate => 'EST-',
            self::Contract => 'CON-',
            self::Bill => 'BILL-',
            self::VariationOrder => 'VO-',
            default => null,
        };
    }
}
