<?php

namespace App\DTO;

use App\Enums\Accounting\DocumentType;
use App\Enums\Setting\Font;
use App\Models\Accounting\Document;
use App\Models\Setting\DocumentDefault;
use App\Utilities\Currency\CurrencyAccessor;
use App\Utilities\Currency\CurrencyConverter;
use Filament\FontProviders\BunnyFontProvider;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;

readonly class DocumentDTO
{
    /**
     * @param  LineItemDTO[]  $lineItems
     */
    public function __construct(
        public string $header,
        public ?string $subheader,
        public ?string $footer,
        public ?string $terms,
        public ?string $logo,
        public string $number,
        public ?string $referenceNumber,
        public ?string $date,
        public ?string $dueDate,
        public string $currencyCode,
        public ?string $subtotal,
        public ?string $discount,
        public ?string $tax,
        public string $total,
        public ?string $amountDue,
        public CompanyDTO $company,
        public ?ClientDTO $client,
        public iterable $lineItems,
        public iterable $lineItemGroups,
        public DocumentLabelDTO $label,
        public DocumentColumnLabelDTO $columnLabel,
        public ?Model $createdBy,
        public string $accentColor = '#080707ff',
        public bool $showLogo = true,
        public Font $font = Font::Inter,
        public ?string $backgroundImage = null,
        public ?string $materialsGuide = null,
        public ?string $termsAndConditions = null,
    ) {}

    public static function fromModel(Document $document): self
    {
        $document->loadMissing(['lineItems.offering', 'lineItemGroups.items.offering', 'lineItemGroups.children.items.offering', 'clientAndLead', 'company', 'templateCompany', 'createdBy']);

        $issuingCompany = $document->templateCompany ?? $document->company;

        /** @var DocumentDefault $settings */
        $settings = $issuingCompany->documentDefaults()
            ->withoutGlobalScopes()
            ->type($document::documentType())
            ->first() ?? $issuingCompany->defaultInvoice()->withoutGlobalScopes()->first();

        $currencyCode = $document->currency_code ?? CurrencyAccessor::getDefaultCurrency();

        $discount = $document->discount_total > 0
            ? self::formatToMoney($document->discount_total, $currencyCode)
            : null;

        $taxTotal = $document->tax_total;
        if ($taxTotal === 0) {
            $document->load(['salesTaxes']);
            if ($document->salesTaxes->isNotEmpty()) {
                $subtotalCents = $document->subtotal;
                $documentTaxTotalCents = $document->salesTaxes->reduce(function (int $carry, \App\Models\Accounting\Adjustment $tax) use ($subtotalCents) {
                    if ($tax->computation->isPercentage()) {
                        return $carry + \App\Utilities\RateCalculator::calculatePercentage($subtotalCents, $tax->getRawOriginal('rate'));
                    } else {
                        return $carry + $tax->getRawOriginal('rate');
                    }
                }, 0);
                $taxTotal = $documentTaxTotalCents;
            }
        }

        $tax = $taxTotal !== 0
            ? self::formatToMoney($taxTotal, $currencyCode)
            : null;

        if ($taxTotal !== 0 && $document->tax_total === 0) {
            // If we had to calculate it manually, we should also update the total of the DTO
            $document->total = $document->subtotal + $taxTotal - $document->discount_total;
        }

        $subtotal = ($discount || $tax)
            ? self::formatToMoney($document->subtotal, $currencyCode)
            : null;

        $documentType = $document::documentType();
        if ($document instanceof \App\Models\Accounting\Estimate && $document->status === \App\Enums\Accounting\EstimateStatus::Accepted) {
            $documentType = DocumentType::Contract;
        }

        $labels = $documentType->getLabels();

        $amountDue = ! in_array($documentType, [DocumentType::Estimate, DocumentType::Contract, DocumentType::VariationOrder]) ?
            self::formatToMoney($document->amountDue(), $currencyCode) :
            null;

        return new self(
            header: ($documentType === DocumentType::Contract) ? $labels->title : $document->header,
            subheader: $document->subheader,
            footer: $document->footer,
            terms: $document->terms,
            logo: $document->logo_url ?? $settings?->logo_url,
            number: $document->documentNumber(),
            referenceNumber: $document->referenceNumber(),
            date: $document->documentDate(),
            dueDate: $document->dueDate(),
            currencyCode: $currencyCode,
            subtotal: $subtotal,
            discount: $discount,
            tax: $tax,
            total: self::formatToMoney($document->total, $currencyCode),
            amountDue: $amountDue,
            company: CompanyDTO::fromModel($issuingCompany),
            client: $document->clientOrLead ? ClientDTO::fromModel($document->clientOrLead) : null,
            lineItems: $document->lineItems()->withoutGlobalScopes()->with('offering')->get()->map(fn ($item) => LineItemDTO::fromModel($item)),
            label: $labels,
            columnLabel: $settings ? DocumentColumnLabelDTO::fromModel($settings) : DocumentColumnLabelDTO::getDefaultLabels(),
            createdBy: $document->createdBy,
            accentColor: $settings?->accent_color ?? '#000000',
            showLogo: $settings?->show_logo ?? false,
            font: $settings?->font ?? Font::Inter,
            backgroundImage: $settings?->background_image_url,
            materialsGuide: $settings?->materials_guide,
            termsAndConditions: $settings?->terms_and_conditions,
            lineItemGroups: $document->lineItemGroups()->withoutGlobalScopes()->whereNull('parent_id')->get()->isNotEmpty() 
                ? $document->lineItemGroups()
                    ->withoutGlobalScopes()
                    ->whereNull('parent_id')
                    ->with(['children.items', 'items'])
                    ->orderBy('order')
                    ->get()
                    ->flatMap(function ($group) {
                        $groups = [LineItemGroupDTO::fromModel($group)];
                        foreach ($group->children as $child) {
                            $groups[] = LineItemGroupDTO::fromModel($child);
                        }
                        return $groups;
                    })
                : collect([new LineItemGroupDTO(name: null, items: $document->lineItems()->withoutGlobalScopes()->with('offering')->get()->map(fn ($item) => LineItemDTO::fromModel($item)))]),
        );
    }

    protected static function formatToMoney(float | string | int $value, ?string $currencyCode): string
    {
        if (is_int($value)) {
            return CurrencyConverter::formatCentsToMoney($value, $currencyCode);
        }

        return CurrencyConverter::formatToMoney($value, $currencyCode);
    }

    public function getFontHtml(): Htmlable
    {
        return app(BunnyFontProvider::class)->getHtml($this->font->getLabel());
    }
}
