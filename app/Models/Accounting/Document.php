<?php

namespace App\Models\Accounting;

use App\Concerns\Blamable;
use App\Concerns\CompanyOwned;
use App\Enums\Accounting\DocumentType;
use App\Models\Setting\Currency;
use App\Filament\Infolists\Components\DocumentPreview;
use Filament\Actions\Action;
use Filament\Actions\MountableAction;
use Filament\Infolists\Infolist;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use App\Enums\Accounting\AdjustmentCategory;
use App\Enums\Accounting\AdjustmentType;
use Livewire\Component;

abstract class Document extends Model
{
    use Blamable;
    use CompanyOwned;
    use HasFactory;

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    public function lineItems(): MorphMany
    {
        return $this->morphMany(DocumentLineItem::class, 'documentable')->orderBy('line_number');
    }

    public function lineItemGroups(): MorphMany
    {
        return $this->morphMany(DocumentLineItemGroup::class, 'documentable')->orderBy('order');
    }

    public function templateCompany(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Company::class, 'template_company_id');
    }

    public function adjustments(): MorphToMany
    {
        return $this->morphToMany(Adjustment::class, 'adjustmentable', 'adjustmentables')->withoutGlobalScopes();
    }

    public function salesTaxes(): MorphToMany
    {
        return $this->adjustments()->where('category', AdjustmentCategory::Tax)->where('type', AdjustmentType::Sales);
    }

    public function purchaseTaxes(): MorphToMany
    {
        return $this->adjustments()->where('category', AdjustmentCategory::Tax)->where('type', AdjustmentType::Purchase);
    }

    public function hasLineItems(): bool
    {
        return $this->lineItems()->exists();
    }

    public function hasInactiveAdjustments(): bool
    {
        return $this->lineItems->contains(function (DocumentLineItem $lineItem) {
            return $lineItem->adjustments->contains(function (Adjustment $adjustment) {
                return $adjustment->isInactive();
            });
        });
    }

    public function hasLineItemsWithMissingIncomeAccounts(): bool
    {
        return $this->lineItems->contains(function (DocumentLineItem $lineItem) {
            return $lineItem->offering !== null && $lineItem->offering->income_account_id === null;
        });
    }

    public static function getPrintDocumentAction(string $action = Action::class, string $name = 'printPdf'): MountableAction
    {
        return $action::make($name)
            ->label('Print')
            ->icon('heroicon-m-printer')
            ->action(function (self $record, Component $livewire) {
                $url = route('documents.print', [
                    'documentType' => $record::documentType(),
                    'id' => $record->id,
                ]);

                $livewire->js("window.printPdf('{$url}', '{$record::documentType()->getLabel()} #{$record->documentNumber()}'); ");
            });
    }

    public static function getPreviewAction(string $action = Action::class, string $name = 'preview'): MountableAction
    {
        return $action::make($name)
            ->label('Preview')
            ->icon('heroicon-o-eye')
            ->infolist(fn (Infolist $infolist) => $infolist
                ->schema([
                    DocumentPreview::make()
                        ->type(static::documentType()),
                ])
            )
            ->modalSubmitAction(false)
            ->modalWidth(MaxWidth::SixExtraLarge);
    }

    abstract public static function documentType(): DocumentType;

    abstract public function documentNumber(): ?string;

    abstract public function documentDate(): ?string;

    abstract public function dueDate(): ?string;

    abstract public function referenceNumber(): ?string;

    abstract public function amountDue(): ?string;

    public function getLabels(): \App\DTO\DocumentLabelDTO
    {
        return static::documentType()->getLabels();
    }
}
