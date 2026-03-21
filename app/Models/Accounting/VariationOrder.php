<?php

namespace App\Models\Accounting;

use App\Concerns\Blamable;
use App\Concerns\CompanyOwned;
use App\Enums\Accounting\VariationOrderStatus;
use App\Models\Common\Client;
use App\Models\Common\ClientAndLead;
use App\Models\Setting\Currency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VariationOrder extends Document
{
    use Blamable;
    use CompanyOwned;
    use HasFactory;

    protected $fillable = [
        'company_id',
        'estimate_id',
        'client_id',
        'logo',
        'header',
        'subheader',
        'vo_number',
        'reference_number',
        'date',
        'expiry_date',
        'status',
        'currency_code',
        'discount_method',
        'discount_computation',
        'discount_rate',
        'subtotal',
        'tax_total',
        'discount_total',
        'total',
        'title',
        'description',
        'terms',
        'notes',
        'footer',
        'approved_at',
        'accepted_at',
        'converted_at',
        'declined_at',
        'last_sent_at',
        'last_viewed_at',
        'is_template',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'date'                 => 'date',
        'expiry_date'          => 'date',
        'approved_at'          => 'datetime',
        'accepted_at'          => 'datetime',
        'converted_at'         => 'datetime',
        'declined_at'          => 'datetime',
        'last_sent_at'         => 'datetime',
        'last_viewed_at'       => 'datetime',
        'status'               => VariationOrderStatus::class,
        'discount_method'      => \App\Enums\Accounting\DocumentDiscountMethod::class,
        'discount_computation' => \App\Enums\Accounting\AdjustmentComputation::class,
        'is_template'          => 'boolean',
    ];

    public function estimate(): BelongsTo
    {
        return $this->belongsTo(Estimate::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function clientAndLead(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Common\ClientAndLead::class, 'client_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    public static function getNextDocumentNumber(?\App\Models\Company $company = null): string
    {
        $company ??= \Illuminate\Support\Facades\Auth::user()?->currentCompany;

        if (! $company) {
            throw new \RuntimeException('No current company is set for the user.');
        }

        $defaultSettings = $company->defaultVariationOrder;

        $numberPrefix = $defaultSettings?->number_prefix ?? '';

        $latestDocument = static::query()
            ->whereNotNull('vo_number')
            ->latest('id') // Change to id to be safer if number isn't numeric
            ->first();

        $lastNumberNumericPart = $latestDocument
            ? (int) substr($latestDocument->vo_number, strlen($numberPrefix))
            : \App\Models\Setting\DocumentDefault::getBaseNumber();

        $numberNext = $lastNumberNumericPart + 1;

        if ($defaultSettings) {
            return $defaultSettings->getNumberNext(
                prefix: $numberPrefix,
                next: $numberNext
            );
        }

        return $numberPrefix . $numberNext;
    }

    public static function documentType(): \App\Enums\Accounting\DocumentType
    {
        return \App\Enums\Accounting\DocumentType::VariationOrder;
    }

    public function documentNumber(): ?string
    {
        return $this->vo_number;
    }

    public function documentDate(): ?string
    {
        return $this->date ? Carbon::parse($this->date)->toDateString() : null;
    }

    public function dueDate(): ?string
    {
        return $this->expiry_date ? Carbon::parse($this->expiry_date)->toDateString() : null;
    }

    public function referenceNumber(): ?string
    {
        return $this->reference_number;
    }

    public function amountDue(): ?string
    {
        return $this->total;
    }

    public function shouldBeExpired(): bool
    {
        return $this->expiry_date?->isBefore(company_today()) && $this->canBeExpired();
    }

    public function isDraft(): bool
    {
        return $this->status === VariationOrderStatus::Draft;
    }

    public function wasApproved(): bool
    {
        return $this->approved_at !== null;
    }

    public function wasAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    public function wasRejected(): bool
    {
        return $this->rejected_at !== null;
    }

    public function wasConverted(): bool
    {
        return $this->converted_at !== null;
    }

    public function hasBeenSent(): bool
    {
        return $this->last_sent_at !== null;
    }

    public function hasBeenViewed(): bool
    {
        return $this->last_viewed_at !== null;
    }

    public function canBeExpired(): bool
    {
        return ! in_array($this->status, [
            VariationOrderStatus::Draft,
            VariationOrderStatus::Approved,
            VariationOrderStatus::Rejected,
        ]);
    }

    public function canBeApproved(): bool
    {
        return $this->isDraft() && ! $this->wasApproved();
    }

    public function canBeMarkedAsRejected(): bool
    {
        return $this->hasBeenSent()
            && ! $this->wasRejected()
            && ! $this->wasConverted()
            && ! $this->wasAccepted();
    }

    public function canBeMarkedAsSent(): bool
    {
        return ! $this->hasBeenSent();
    }

    public function canBeMarkedAsAccepted(): bool
    {
        return $this->hasBeenSent()
            && ! $this->wasAccepted()
            && ! $this->wasRejected()
            && ! $this->wasConverted();
    }

    public function approveDraft(?Carbon $approvedAt = null): void
    {
        if (! $this->isDraft()) {
            throw new \RuntimeException('Variation Order is not in draft status.');
        }

        $approvedAt ??= company_now();

        $this->update([
            'approved_at' => $approvedAt,
            'status' => VariationOrderStatus::Approved,
        ]);
    }

    public function markAsSent(?Carbon $sentAt = null): void
    {
        $sentAt ??= company_now();

        $this->update([
            'status' => VariationOrderStatus::Sent,
            'last_sent_at' => $sentAt,
        ]);
    }

    public function markAsRejected(?Carbon $rejectedAt = null): void
    {
        $rejectedAt ??= company_now();

        $this->update([
            'status' => VariationOrderStatus::Rejected,
            'rejected_at' => $rejectedAt,
        ]);
    }

    public static function getApproveDraftAction(string $action = \Filament\Actions\Action::class): \Filament\Actions\MountableAction
    {
        return $action::make('approveDraft')
            ->label('Approve')
            ->icon('heroicon-m-check-circle')
            ->visible(function (self $record) {
                return $record->canBeApproved();
            })
            ->requiresConfirmation()
            ->databaseTransaction()
            ->successNotificationTitle('Variation Order approved')
            ->action(function (self $record, \Filament\Actions\MountableAction $action) {
                $record->approveDraft();
                $action->success();
            });
    }

    public static function getMarkAsSentAction(string $action = \Filament\Actions\Action::class): \Filament\Actions\MountableAction
    {
        return $action::make('markAsSent')
            ->label('Mark as sent')
            ->icon('heroicon-m-paper-airplane')
            ->visible(static function (self $record) {
                return $record->canBeMarkedAsSent();
            })
            ->successNotificationTitle('Variation Order sent')
            ->action(function (self $record, \Filament\Actions\MountableAction $action) {
                $record->markAsSent();
                $action->success();
            });
    }

    public static function getMarkAsRejectedAction(string $action = \Filament\Actions\Action::class): \Filament\Actions\MountableAction
    {
        return $action::make('markAsRejected')
            ->label('Mark as Rejected')
            ->icon('heroicon-m-x-circle')
            ->visible(static function (self $record) {
                return $record->canBeMarkedAsRejected();
            })
            ->color('danger')
            ->requiresConfirmation()
            ->databaseTransaction()
            ->successNotificationTitle('Variation Order rejected')
            ->action(function (self $record, \Filament\Actions\MountableAction $action) {
                $record->markAsRejected();
                $action->success();
            });
    }

    public static function getReplicateAction(string $action = \Filament\Actions\ReplicateAction::class): \Filament\Actions\MountableAction
    {
        return $action::make()
            ->excludeAttributes([
                'vo_number',
                'date',
                'expiry_date',
                'approved_at',
                'accepted_at',
                'converted_at',
                'rejected_at',
                'last_sent_at',
                'last_viewed_at',
                'status',
                'created_by',
                'updated_by',
            ])
            ->modal(false)
            ->beforeReplicaSaved(function (self $original, self $replica) {
                $replica->status = VariationOrderStatus::Draft;
                $replica->vo_number = self::getNextDocumentNumber();
                $replica->date = company_today();
                $replica->expiry_date = company_today()->addDays(30); // Default 30 days
            })
            ->databaseTransaction()
            ->successRedirectUrl(static function (self $replica) {
                return \App\Filament\Company\Resources\Sales\VariationOrderResource::getUrl('edit', ['record' => $replica]);
            });
    }

    public static function getPreviewAction(string $action = \Filament\Actions\Action::class): \Filament\Actions\MountableAction
    {
        return $action::make('preview')
            ->label('Preview')
            ->icon('heroicon-m-magnifying-glass')
            ->url(fn (self $record) => \App\Filament\Company\Resources\Sales\VariationOrderResource::getUrl('view', ['record' => $record]), shouldOpenInNewTab: true);
    }

    public static function getPrintDocumentAction(string $action = \Filament\Actions\Action::class): \Filament\Actions\MountableAction
    {
        return $action::make('print')
            ->label('Print')
            ->icon('heroicon-m-printer')
            ->url(fn (self $record) => '#'); // Placeholder for print functionality
    }

    public static function getDownloadMergedPdfAction(string $action = \Filament\Actions\Action::class): \Filament\Actions\MountableAction
    {
        $downloadAction = $action::make('downloadMergedPdf')
            ->label('Download PDF')
            ->icon('heroicon-m-arrow-down-tray');

        if (config('erp.async_pdf_generation')) {
            $downloadAction->modalHeading('Generating PDF')
                ->modalSubmitAction(false)
                ->modalCancelAction(false)
                ->modalContent(fn (self $record) => view('components.variation-order-pdf-modal', ['record' => $record]))
                ->action(fn () => null);
        } else {
            $downloadAction->action(function (self $record) {
                $pdfService = new \App\Services\VariationOrderPdfService();
                $finalPdfOutput = $pdfService->generate($record);
                
                return response()->streamDownload(function () use ($finalPdfOutput) {
                    echo $finalPdfOutput;
                }, "VariationOrder-{$record->documentNumber()}.pdf");
            });
        }

        return $downloadAction;
    }

    public function scopeIsTemplate(Builder $query): Builder
    {
        return $query->where('is_template', true);
    }

    public function scopeIsNotTemplate(Builder $query): Builder
    {
        return $query->where('is_template', false);
    }
}
