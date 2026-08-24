<?php

namespace App\DTO;

use App\Enums\Accounting\DocumentType;
use App\Enums\Setting\Font;
use App\Enums\Setting\PaymentTerms;
use App\Enums\Setting\TextAlign;
use App\Models\Setting\DocumentDefault;
use App\Utilities\Currency\CurrencyAccessor;
use Illuminate\Support\Facades\Storage;

readonly class DocumentPreviewDTO extends DocumentDTO
{
    public static function fromSettings(DocumentDefault $settings, ?array $data = null): self
    {
        $company = $settings->company;

        $paymentTerms = PaymentTerms::parse($data['payment_terms']) ?? $settings->payment_terms;

        $amountDue = $settings->type !== DocumentType::Estimate ?
            self::formatToMoney(95000, null) :
            null;

        return new self(
            header: $data['header'] ?? $settings->header ?? 'Invoice',
            subheader: $data['subheader'] ?? $settings->subheader,
            footer: $data['footer'] ?? $settings->footer,
            terms: $data['terms'] ?? $settings->terms,
            logo: self::getPreviewLogo($settings, $data),
            number: self::generatePreviewNumber($settings, $data),
            referenceNumber: $settings->getNumberNext('ORD-'),
            date: $company->locale->date_format->getLabel(),
            dueDate: $paymentTerms->getDueDate($company->locale->date_format->value),
            currencyCode: CurrencyAccessor::getDefaultCurrency(),
            subtotal: self::formatToMoney(100000, null), // $1000.00
            discount: self::formatToMoney(10000, null), // $100.00
            tax: self::formatToMoney(5000, null), // $50.00
            total: self::formatToMoney(95000, null), // $950.00
            amountDue: $amountDue, // $950.00 or null for estimates
            company: CompanyDTO::fromModel($company),
            client: ClientPreviewDTO::fake(),
            lineItems: LineItemPreviewDTO::fakeItems(),
            lineItemGroups: collect([new LineItemGroupDTO(name: null, items: LineItemPreviewDTO::fakeItems())]),
            label: $settings->type->getLabels(),
            columnLabel: self::generateColumnLabels($settings, $data),
            createdBy: $settings->company->owner,
            accentColor: $data['accent_color'] ?? $settings->accent_color ?? '#000000',
            showLogo: $data['show_logo'] ?? $settings->show_logo ?? true,
            font: Font::tryFrom($data['font']) ?? $settings->font ?? Font::Inter,
            backgroundImage: self::getPreviewBackgroundImage($settings, $data),
            colorSecondary: $data['color_secondary'] ?? $settings->color_secondary ?? '#f7f1eb',
            colorSecondaryText: $data['color_secondary_text'] ?? $settings->color_secondary_text ?? '#96693c',
            colorSectionBg: $data['color_section_bg'] ?? $settings->color_section_bg ?? '#e0b182',
            colorSectionBgText: $data['color_section_bg_text'] ?? $settings->color_section_bg_text ?? '#293834',
            colorGroupBg: $data['color_group_bg'] ?? $settings->color_group_bg ?? '#d4b896',
            colorGroupBgText: $data['color_group_bg_text'] ?? $settings->color_group_bg_text ?? '#293834',
            colorSubgroupBg: $data['color_subgroup_bg'] ?? $settings->color_subgroup_bg ?? '#f7f1eb',
            colorSubgroupText: $data['color_subgroup_text'] ?? $settings->color_subgroup_text ?? '#96693c',
            colorText: $data['color_text'] ?? $settings->color_text ?? '#293834',
            sectionHeaderAlign: TextAlign::parse($data['section_header_align'] ?? null) ?? $settings->section_header_align ?? TextAlign::Left,
            groupHeaderAlign: TextAlign::parse($data['group_header_align'] ?? null) ?? $settings->group_header_align ?? TextAlign::Left,
            subgroupHeaderAlign: TextAlign::parse($data['subgroup_header_align'] ?? null) ?? $settings->subgroup_header_align ?? TextAlign::Left,
        );
    }

    protected static function getPreviewBackgroundImage(DocumentDefault $settings, ?array $data): ?string
    {
        $backgroundImage = $data['background_image'] ?? $settings->background_image;

        if (is_array($backgroundImage)) {
            $backgroundImage = reset($backgroundImage);
        }

        if (! is_string($backgroundImage) || empty($backgroundImage)) {
            return null;
        }

        return Storage::disk('public')->url($backgroundImage);
    }

    protected static function getPreviewLogo(DocumentDefault $settings, ?array $data): ?string
    {
        $logo = $data['logo'] ?? $settings->logo;

        if (is_array($logo)) {
            $logo = reset($logo);
        }

        if (! is_string($logo) || empty($logo)) {
            return null;
        }

        return Storage::disk('public')->url($logo);
    }

    protected static function generatePreviewNumber(DocumentDefault $settings, ?array $data): string
    {
        $prefix = $data['number_prefix'] ?? $settings->number_prefix ?? 'INV-';

        return $settings->getNumberNext($prefix);
    }

    protected static function generateColumnLabels(DocumentDefault $settings, ?array $data): DocumentColumnLabelDTO
    {
        return new DocumentColumnLabelDTO(
            items: $settings->resolveColumnLabel('item_name', 'Items', $data),
            units: $settings->resolveColumnLabel('unit_name', 'Quantity', $data),
            price: $settings->resolveColumnLabel('price_name', 'Price', $data),
            amount: $settings->resolveColumnLabel('amount_name', 'Amount', $data),
        );
    }
}
