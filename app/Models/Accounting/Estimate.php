<?php

namespace App\Models\Accounting;

use App\Casts\RateCast;
use App\Collections\Accounting\DocumentCollection;
use App\Enums\Accounting\AdjustmentComputation;
use App\Enums\Accounting\DocumentDiscountMethod;
use App\Enums\Accounting\DocumentType;
use App\Enums\Accounting\EstimateStatus;
use App\Enums\Accounting\InvoiceStatus;
use App\Filament\Company\Resources\Sales\EstimateResource;
use App\Filament\Company\Resources\Sales\ContractResource;
use App\Filament\Company\Resources\Sales\InvoiceResource;
use App\Filament\Company\Resources\Sales\VariationOrderResource;
use App\Models\Accounting\VariationOrder;
use App\Models\Common\Client;
use App\Models\Common\ClientAndLead;
use App\Models\Common\Lead;
use App\Models\Company;
use App\Models\Setting\DocumentDefault;
use App\Observers\EstimateObserver;
use Filament\Actions\Action;
use Filament\Actions\MountableAction;
use Filament\Actions\ReplicateAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Attributes\CollectedBy;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use App\Models\Accounting\DocumentLineItemGroup;
use Illuminate\Support\Facades\Mail;
use App\Mail\Sales\EstimateMail;


#[CollectedBy(DocumentCollection::class)]
#[ObservedBy(EstimateObserver::class)]
class Estimate extends Document
{
    protected $fillable = [
        'company_id',
        'client_id',
        'logo',
        'header',
        'subheader',
        'estimate_number',
        'reference_number',
        'date',
        'expiration_date',
        'approved_at',
        'accepted_at',
        'converted_at',
        'declined_at',
        'last_sent_at',
        'last_viewed_at',
        'status',
        'currency_code',
        'discount_method',
        'discount_computation',
        'discount_rate',
        'subtotal',
        'tax_total',
        'discount_total',
        'total',
        'terms',
        'footer',
        'template_company_id',
        'is_template',
        'archived_at',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'date' => 'date',
        'expiration_date' => 'date',
        'approved_at' => 'datetime',
        'accepted_at' => 'datetime',
        'declined_at' => 'datetime',
        'last_sent_at' => 'datetime',
        'last_viewed_at' => 'datetime',
        'archived_at' => 'datetime',
        'status' => EstimateStatus::class,
        'discount_method' => DocumentDiscountMethod::class,
        'discount_computation' => AdjustmentComputation::class,
        'discount_rate' => RateCast::class,
        'is_template' => 'boolean',
    ];

    protected $appends = [
        'logo_url',
    ];

    protected function logoUrl(): Attribute
    {
        return Attribute::get(static function (mixed $value, array $attributes): ?string {
            return $attributes['logo'] ? Storage::disk('public')->url($attributes['logo']) : null;
        });
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withoutGlobalScopes()->where('type', 'client');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'client_id')->withoutGlobalScopes();
    }

    public function clientAndLead(): BelongsTo
    {
        return $this->belongsTo(ClientAndLead::class, 'client_id')->withoutGlobalScopes();
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function variationOrders(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(VariationOrder::class);
    }

    public static function documentType(): DocumentType
    {
        return DocumentType::Estimate;
    }

    public function documentNumber(): ?string
    {
        return $this->estimate_number;
    }

    public function documentDate(): ?string
    {
        return $this->date?->toDateString();
    }

    public function dueDate(): ?string
    {
        return $this->expiration_date?->toDateString();
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
        return $this->expiration_date?->isBefore(company_today()) && $this->canBeExpired();
    }

    public function isDraft(): bool
    {
        return $this->status === EstimateStatus::Draft;
    }

    public function wasApproved(): bool
    {
        return $this->approved_at !== null;
    }

    public function wasAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    public function wasDeclined(): bool
    {
        return $this->declined_at !== null;
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
            EstimateStatus::Draft,
            EstimateStatus::Accepted,
            EstimateStatus::Declined,
            EstimateStatus::Converted,
            EstimateStatus::Expired,
        ]);
    }

    public function canBeApproved(): bool
    {
        return $this->isDraft() && ! $this->wasApproved();
    }

    public function canBeConverted(): bool
    {
        return $this->wasAccepted() && ! $this->wasConverted();
    }

    public function canBeMarkedAsDeclined(): bool
    {
        return $this->hasBeenSent()
            && ! $this->wasDeclined()
            && ! $this->wasConverted()
            && ! $this->wasAccepted();
    }

    public function canBeMarkedAsSent(): bool
    {
        return ! $this->hasBeenSent() && $this->wasApproved();
    }

    public function canBeMarkedAsAccepted(): bool
    {
        return $this->hasBeenSent()
            && ! $this->wasAccepted()
            && ! $this->wasDeclined()
            && ! $this->wasConverted();
    }

    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->whereIn('status', [
            EstimateStatus::Unsent,
            EstimateStatus::Sent,
            EstimateStatus::Viewed,
            EstimateStatus::Accepted,
        ]);
    }

    public static function getNextDocumentNumber(?Company $company = null): string
    {
        $company ??= \Illuminate\Support\Facades\Auth::user()?->currentCompany;


        $defaultEstimateSettings = $company->defaultEstimate;

        $numberPrefix = $defaultEstimateSettings->number_prefix ?? '';

        $latestDocument = static::query()
            ->withoutGlobalScopes()
            ->whereNotNull('estimate_number')
            ->when($numberPrefix !== '', function ($query) use ($numberPrefix) {
                return $query->where('estimate_number', 'LIKE', "{$numberPrefix}%");
            }, function ($query) use ($company) {
                return $query->where('company_id', $company->id);
            })
            ->latest('id')
            ->first();

        $lastNumberNumericPart = $latestDocument
            ? (int) substr($latestDocument->estimate_number, strlen($numberPrefix))
            : DocumentDefault::getBaseNumber();

        $numberNext = $lastNumberNumericPart + 1;

        return $defaultEstimateSettings->getNumberNext(
            prefix: $numberPrefix,
            next: $numberNext
        );
    }

    public function approveDraft(?Carbon $approvedAt = null): void
    {
        if (! $this->isDraft()) {
            throw new \RuntimeException('Estimate is not in draft status.');
        }

        $approvedAt ??= company_now();

        $this->update([
            'approved_at' => $approvedAt,
            'status' => EstimateStatus::Unsent,
        ]);
    }
    public function scopeIsTemplate(Builder $query): Builder
    {
        return $query->where('is_template', true);
    }

    public function scopeIsNotTemplate(Builder $query): Builder
    {
        return $query->where('is_template', false);
    }

    public static function getApproveDraftAction(string $action = Action::class): MountableAction
    {
        return $action::make('approveDraft')
            ->label('Approve')
            ->icon('heroicon-m-check-circle')
            ->visible(function (self $record) {
                return $record->canBeApproved();
            })
            ->form(function (self $record, Component $livewire) {
                $templateCompanyId = $livewire->data['template_company_id'] ?? $record->template_company_id;

                return $templateCompanyId ? [] : [
                    Forms\Components\Select::make('template_company_id')
                        ->label('Issue Company')
                        ->relationship('templateCompany', 'name', fn (Builder $query) => $query->where('id', '!=', config('erp.erp_system_company_id')))
                        ->required()
                        ->default(fn (self $record) => $record->template_company_id),
                ];
            })
            ->modalHidden(function (self $record, Component $livewire) {
                $templateCompanyId = $livewire->data['template_company_id'] ?? $record->template_company_id;

                return $templateCompanyId !== null;
            })
            ->action(function (self $record, array $data, MountableAction $action, Component $livewire) {
                 $templateCompanyId = $data['template_company_id'] ?? ($livewire->data['template_company_id'] ?? $record->template_company_id);
                 $templateCompany = Company::withoutGlobalScopes()->find($templateCompanyId);

                if (! $templateCompanyId) {
                    Notification::make()
                        ->warning()
                        ->title('Issue Company Required')
                        ->body('Please select an issue company before approving.')
                        ->send();

                    $action->halt();

                    return;
                }

                if ($record->hasInactiveAdjustments()) {
                    $isViewPage = $livewire instanceof EstimateResource\Pages\ViewEstimate;

                    if (! $isViewPage) {
                        redirect(EstimateResource\Pages\ViewEstimate::getUrl(['record' => $record->id]));
                    } else {
                        Notification::make()
                            ->warning()
                            ->title('Cannot approve estimate')
                            ->body('This estimate has inactive adjustments that must be addressed first.')
                            ->persistent()
                            ->send();
                    }
                } else {
                    $record->template_company_id = $templateCompanyId;

                    // Automatically include default tax from template company
                    $defaultTaxId = \App\Models\Setting\CompanyProfile::withoutGlobalScopes()
                        ->where('company_id', $templateCompanyId)
                        ->value('default_sales_tax_id');
                    if ($defaultTaxId) {
                        $taxKey = static::documentType()->getTaxKey();
                        $record->{$taxKey}()->syncWithoutDetaching([$defaultTaxId]);
                    }

                    $record->save();
                    $record->approveDraft();

                    if (method_exists($livewire, 'refresh')) {
                        $livewire->refresh();
                    }

                    $action->success();
                }
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
            ->successNotificationTitle('Estimate sent')
            ->action(function (self $record, \Filament\Actions\MountableAction $action) {
                $record->markAsSent();
                $action->success();
            });
    }

    public static function getSendEmailAction(string $action = \Filament\Actions\Action::class): \Filament\Actions\MountableAction
    {
        return $action::make('sendEmail')
            ->label('Send Email')
            ->icon('heroicon-m-envelope')
            ->visible(fn (self $record) => $record->wasApproved())
            ->form([
                Forms\Components\TextInput::make('email')
                    ->email()
                    ->required()
                    ->default(fn (self $record) => $record->clientOrLead?->primaryContact?->email),
                Forms\Components\TextInput::make('subject')
                    ->required()
                    ->default(fn (self $record) => "Estimate #" . $record->estimate_number),
                Forms\Components\Textarea::make('message')
                    ->required()
                    ->rows(5)
                    ->default(fn (self $record) => "Dear " . ($record->clientOrLead?->name ?? 'Client') . ",\n\nPlease find the attached estimate " . $record->estimate_number . ".\n\nBest regards."),
            ])
            ->action(function (self $record, array $data, \Filament\Actions\MountableAction $action) {
                Mail::to($data['email'])->send(new EstimateMail($record, $data['message'], $data['subject']));
                
                $record->markAsSent();
                
                $action->success();
            });
    }

    public function markAsSent(?Carbon $sentAt = null): void
    {
        $sentAt ??= company_now();

        $this->update([
            'status' => EstimateStatus::Sent,
            'last_sent_at' => $sentAt,
        ]);
    }

    public function markAsViewed(?Carbon $viewedAt = null): void
    {
        $viewedAt ??= company_now();

        $this->update([
            'status' => EstimateStatus::Viewed,
            'last_viewed_at' => $viewedAt,
        ]);
    }

    public static function getReplicateAction(string $action = ReplicateAction::class): MountableAction
    {
        return $action::make()
            ->excludeAttributes([
                'estimate_number',
                'date',
                'expiration_date',
                'approved_at',
                'accepted_at',
                'converted_at',
                'declined_at',
                'last_sent_at',
                'last_viewed_at',
                'status',
                'created_by',
                'updated_by',
                'created_at',
                'updated_at',
            ])
            ->modal(false)
            ->beforeReplicaSaved(function (self $original, self $replica) {
                $replica->status = EstimateStatus::Draft;
                $replica->estimate_number = self::getNextDocumentNumber();
                $replica->date = company_today();
                $replica->expiration_date = company_today()->addDays($original->company->defaultInvoice->payment_terms->getDays());
            })
            ->databaseTransaction()
            ->after(function (self $original, self $replica) {
                $original->replicateLineItems($replica);
            })
            ->successRedirectUrl(static function (self $replica) {
                return EstimateResource::getUrl('edit', ['record' => $replica]);
            });
    }

    public static function getMarkAsAcceptedAction(string $action = Action::class): MountableAction
    {
        return $action::make('markAsAccepted')
            ->label('Mark as Accepted')
            ->icon('heroicon-m-check-badge')
            ->visible(static function (self $record) {
                return $record->canBeMarkedAsAccepted();
            })
            ->databaseTransaction()
            ->successNotificationTitle('Estimate accepted')
            ->action(function (self $record, MountableAction $action) {
                $record->markAsAccepted();

                $action->success();
            });
    }

    public static function getConvertToContractAction(string $action = Action::class): MountableAction
    {
        return $action::make('convertToContract')
            ->label('Convert to Contract')
            ->icon('heroicon-o-document-check')
            ->color('success')
            ->modalHeading('Convert Estimate to Contract')
            ->modalDescription('Please verify and complete the missing client details before converting this estimate to a contract.')
            ->modalSubmitActionLabel('Convert & Update Details')
            ->modalWidth('lg')
            ->modal(fn (self $record) => $record->hasMissingRequiredContractData())
            ->visible(static function (self $record) {
                return $record->status !== EstimateStatus::Accepted && $record->template_company_id !== null;
            })
            ->mountUsing(fn (Forms\ComponentContainer $form, self $record) => $form->fill([
                'estimate_number' => $record->estimate_number,
                'reference_number' => Contract::getNextDocumentNumber($record->company),
                'date' => $record->date?->toDateString(),
                'client_name' => $record->clientOrLead?->name,
                'client_nric' => $record->clientOrLead?->nric,
                'client_phone' => $record->clientOrLead?->primaryContact?->primaryPhone ?? '',
                'client_email' => $record->clientOrLead?->primaryContact?->email ?? '',
                'client_address_line_1' => $record->clientOrLead?->billingAddress?->address_line_1 ?? '',
                'client_address_line_2' => $record->clientOrLead?->billingAddress?->address_line_2,
                'client_postal_code' => $record->clientOrLead?->billingAddress?->postal_code,
                'client_country_code' => $record->clientOrLead?->billingAddress?->country_code ?? 
                    $record->templateCompany?->profile?->address?->country_code ?? 
                    $record->company?->profile?->address?->country_code,
                'salesperson_name' => $record->createdBy?->name,
                'salesperson_email' => $record->createdBy?->email,
            ]))
            ->form(function (Estimate $record): array {
                $schema = [
                    Forms\Components\Placeholder::make('instructions')
                        ->label('Instructions')
                        ->content('The following information is required for the contract. Please fill in any missing details.'),
                ];

                if (blank($record->estimate_number)) {
                    $schema[] = Forms\Components\TextInput::make('estimate_number')
                        ->label('Estimate Number')
                        ->required();
                }

                if (blank($record->date)) {
                    $schema[] = Forms\Components\DatePicker::make('date')
                        ->label('Date')
                        ->required();
                }

                if (blank($record->clientOrLead?->name)) {
                    $schema[] = Forms\Components\TextInput::make('client_name')
                        ->label('Customer Name')
                        ->required();
                }

                if (blank($record->clientOrLead?->nric)) {
                    $schema[] = Forms\Components\TextInput::make('client_nric')
                        ->label('NRIC last 4 digit')
                        ->required();
                }

                if (blank($record->clientOrLead?->primaryContact?->primaryPhone)) {
                    $schema[] = Forms\Components\TextInput::make('client_phone')
                        ->label('Contact No')
                        ->required();
                }

                if (blank($record->clientOrLead?->primaryContact?->email)) {
                    $schema[] = Forms\Components\TextInput::make('client_email')
                        ->label('Email')
                        ->email()
                        ->required();
                }

                if (blank($record->clientOrLead?->billingAddress?->address_line_1)) {
                    $schema[] = Forms\Components\TextInput::make('client_address_line_1')
                        ->label('Address Line 1')
                        ->required();

                    $schema[] = Forms\Components\TextInput::make('client_address_line_2')
                        ->label('Address Line 2');
                }


                if (blank($record->clientOrLead?->billingAddress?->postal_code)) {
                    $schema[] = Forms\Components\TextInput::make('client_postal_code')
                        ->label('Postal Code')
                        ->required();
                }

                if (blank($record->clientOrLead?->billingAddress?->country_code)) {
                    $schema[] = Forms\Components\Select::make('client_country_code')
                        ->label('Country')
                        ->searchable()
                        ->options(\App\Models\Locale\Country::getAvailableCountryOptions())
                        ->required();
                }

                if (blank($record->createdBy?->name)) {
                    $schema[] = Forms\Components\TextInput::make('salesperson_name')
                        ->label('Sale Person Name')
                        ->required();
                }

                if (blank($record->createdBy?->email)) {
                    $schema[] = Forms\Components\TextInput::make('salesperson_email')
                        ->label('Sale Person Email')
                        ->email()
                        ->required();
                }

                return $schema;
            })
            ->action(function (Estimate $record, array $data) {
                app(\App\Services\Accounting\EstimateService::class)->convertToContract($record, $data);

                Notification::make()
                    ->title('Estimate converted to contract')
                    ->success()
                    ->send();

                return redirect()->to(ContractResource::getUrl('view', ['record' => $record]));
            });
    }

    public function hasMissingRequiredContractData(): bool
    {
        return blank($this->estimate_number) ||
            blank($this->date) ||
            blank($this->clientOrLead?->name) ||
            blank($this->clientOrLead?->nric) ||
            blank($this->clientOrLead?->primaryContact?->primaryPhone) ||
            blank($this->clientOrLead?->primaryContact?->email) ||
            blank($this->clientOrLead?->billingAddress?->address_line_1) ||
            blank($this->clientOrLead?->billingAddress?->postal_code) ||
            blank($this->clientOrLead?->billingAddress?->country_code) ||
            blank($this->createdBy?->name) ||
            blank($this->createdBy?->email);
    }

    public function markAsAccepted(?Carbon $acceptedAt = null): void
    {
        $acceptedAt ??= company_now();

        $this->update([
            'status' => EstimateStatus::Accepted,
            'accepted_at' => $acceptedAt,
        ]);
    }

    public static function getMarkAsDeclinedAction(string $action = Action::class): MountableAction
    {
        return $action::make('markAsDeclined')
            ->label('Mark as Declined')
            ->icon('heroicon-m-x-circle')
            ->visible(static function (self $record) {
                return $record->canBeMarkedAsDeclined();
            })
            ->color('danger')
            ->requiresConfirmation()
            ->databaseTransaction()
            ->successNotificationTitle('Estimate declined')
            ->action(function (self $record, MountableAction $action) {
                $record->markAsDeclined();

                $action->success();
            });
    }

    public function markAsDeclined(?Carbon $declinedAt = null): void
    {
        $declinedAt ??= company_now();

        $this->update([
            'status' => EstimateStatus::Declined,
            'declined_at' => $declinedAt,
        ]);
    }

    public static function getConvertToInvoiceAction(string $action = Action::class): MountableAction
    {
        return $action::make('convertToInvoice')
            ->label('Convert to Invoice')
            ->icon('heroicon-m-arrow-right-on-rectangle')
            ->visible(static function (self $record) {
                return $record->canBeConverted();
            })
            ->databaseTransaction()
            ->successNotificationTitle('Estimate converted to invoice')
            ->action(function (self $record, MountableAction $action) {
                $record->convertToInvoice();

                $action->success();
            })
            ->successRedirectUrl(static function (self $record) {
                return InvoiceResource::getUrl('edit', ['record' => $record->refresh()->invoice]);
            });
    }

    public function convertToInvoice(?Carbon $convertedAt = null): void
    {
        if ($this->invoice) {
            throw new \RuntimeException('Estimate has already been converted to an invoice.');
        }

        $invoice = $this->invoice()->create([
            'company_id' => $this->company_id,
            'client_id' => $this->client_id,
            'logo' => $this->logo,
            'header' => $this->company->defaultInvoice->header,
            'subheader' => $this->company->defaultInvoice->subheader,
            'invoice_number' => Invoice::getNextDocumentNumber($this->company),
            'date' => company_today(),
            'due_date' => company_today()->addDays($this->company->defaultInvoice->payment_terms->getDays()),
            'status' => InvoiceStatus::Draft,
            'currency_code' => $this->currency_code,
            'discount_method' => $this->discount_method,
            'discount_computation' => $this->discount_computation,
            'discount_rate' => $this->getRawOriginal('discount_rate'),
            'subtotal' => $this->subtotal,
            'tax_total' => $this->tax_total,
            'discount_total' => $this->discount_total,
            'total' => $this->total,
            'terms' => $this->terms,
            'footer' => $this->footer,
            'created_by' => \Illuminate\Support\Facades\Auth::id(),
            'updated_by' => \Illuminate\Support\Facades\Auth::id(),
        ]);

        $this->replicateLineItems($invoice);

        $convertedAt ??= company_now();

        $this->update([
            'status' => EstimateStatus::Converted,
            'converted_at' => $convertedAt,
        ]);
    }

    public function replicateLineItems(Model $target): void
    {
        $groupMap = [];

        // Replicate Groups (Top-level first, then children)
        // Ensure that we explicitly sort by parent_id so that parent groups are
        // processed before their children. lineItemGroups() has a default orderBy('order').
        $groups = $this->lineItemGroups()->get()->sortBy(function (DocumentLineItemGroup $group) {
            return $group->parent_id === null ? -1 : $group->parent_id;
        });

        $groups->each(function (DocumentLineItemGroup $group) use ($target, &$groupMap) {
            $replicaGroup = $group->replicate([
                'documentable_id',
                'documentable_type',
                'parent_id',
                'created_by',
                'updated_by',
                'created_at',
                'updated_at'
            ]);
            $replicaGroup->documentable_id = $target->id;
            $replicaGroup->documentable_type = $target->getMorphClass();
            
            if ($group->parent_id && isset($groupMap[$group->parent_id])) {
                $replicaGroup->parent_id = $groupMap[$group->parent_id];
            } else {
                $replicaGroup->parent_id = null;
            }

            $replicaGroup->save();
            $groupMap[$group->id] = $replicaGroup->id;

            $group->items->each(function (DocumentLineItem $lineItem) use ($target, $replicaGroup) {
                $replica = $lineItem->replicate([
                    'documentable_id',
                    'documentable_type',
                    'group_id',
                    'subtotal',
                    'total',
                    'created_by',
                    'updated_by',
                    'created_at',
                    'updated_at',
                ]);

                $replica->documentable_id = $target->id;
                $replica->documentable_type = $target->getMorphClass();
                $replica->group_id = $replicaGroup->id;
                $replica->save();

                $replica->adjustments()->sync($lineItem->adjustments->pluck('id'));
            });
        });

        // Replicate items without group
        $this->lineItems()->whereNull('group_id')->each(function (DocumentLineItem $lineItem) use ($target) {
            $replica = $lineItem->replicate([
                'documentable_id',
                'documentable_type',
                'subtotal',
                'total',
                'created_by',
                'updated_by',
                'created_at',
                'updated_at',
            ]);

            $replica->documentable_id = $target->id;
            $replica->documentable_type = $target->getMorphClass();
            $replica->save();

            $replica->adjustments()->sync($lineItem->adjustments->pluck('id'));
        });

        //if ($this->adjustments()->exists()) {
        //    $target->adjustments()->sync($this->adjustments->pluck('id'));
        //}
    }

    public static function createFromTemplate(self $template, int $clientId, ?int $estimateId = null, ?Company $company = null, ?int $userId = null): self
    {
        $company ??= \Illuminate\Support\Facades\Auth::user()?->currentCompany;
        $userId ??= \Illuminate\Support\Facades\Auth::id();
        $currencyCode = \App\Utilities\Currency\CurrencyAccessor::getDefaultCurrency();

        if ($estimateId) {
            $estimate = self::findOrFail($estimateId);
            
            // Clear existing lines
            $estimate->lineItems()->delete();
            $estimate->lineItemGroups()->delete();

            // Update header
            $estimate->update([
                'client_id' => $clientId,
                'header' => '',
                'subheader' => $template->subheader,
                'currency_code' => $currencyCode,
                'discount_method' => $template->discount_method,
                'discount_computation' => $template->discount_computation,
                'discount_rate' => $template->discount_rate,
                'terms' => $template->terms,
                'footer' => $template->footer,
                'updated_by' => $userId,
            ]);
        } else {
            // New Estimate
            $estimate = $template->replicate([
                'is_template',
                'estimate_number',
                'date',
                'expiration_date',
                'approved_at',
                'accepted_at',
                'converted_at',
                'declined_at',
                'last_sent_at',
                'last_viewed_at',
                'status',
                'created_by',
                'updated_by',
                'created_at',
                'updated_at',
            ]);

            $estimate->is_template = false;
            $estimate->company_id = $company->id;
            $estimate->client_id = $clientId;
            $estimate->estimate_number = self::getNextDocumentNumber($company);
            $estimate->status = EstimateStatus::Draft;
            $estimate->date = now();
            $estimate->expiration_date = now()->addDays(30);
            $estimate->currency_code = $currencyCode;
            $estimate->created_by = $userId;
            $estimate->updated_by = $userId;
            $estimate->save();
        }

        // Replicate line items
        $template->replicateLineItems($estimate);

        // Recalculate totals
        $grandTotal = $estimate->lineItems()->sum('total');
        // Calculate other totals if needed, for now assuming simple sum
        $subtotal = $estimate->lineItems()->sum('subtotal');
        // taxes etc... 

        $estimate->update([
            'subtotal' => $subtotal,
            'total' => $grandTotal,
        ]);

        return $estimate;
    }

    public static function getDownloadMergedPdfAction(string $action = Action::class, string $name = 'downloadMergedPdf'): MountableAction
    {
        $downloadAction = $action::make($name)
            ->label('Download PDF')
            ->icon('heroicon-m-arrow-down-tray')
            ->form(function (self $record, Component $livewire) {
                $templateCompanyId = $livewire->data['template_company_id'] ?? $record->template_company_id;

                return $templateCompanyId ? [] : [
                    Forms\Components\Select::make('template_company_id')
                        ->label('Issue Company')
                        ->relationship('templateCompany', 'name', fn (Builder $query) => $query->where('id', '!=', config('erp.erp_system_company_id')))
                        ->default(fn (self $record) => $record->template_company_id)
                        ->required()
                        ->searchable()
                        ->preload(),
                ];
            })
            ->modalHidden(function (self $record, Component $livewire) {
                $templateCompanyId = $livewire->data['template_company_id'] ?? $record->template_company_id;

                return $templateCompanyId !== null;
            })
            ->modalSubmitActionLabel('Download')
            ->action(function (self $record, array $data, Component $livewire) {
                $templateCompanyId = $data['template_company_id'] ?? ($livewire->data['template_company_id'] ?? $record->template_company_id);

                // Save the selected template company
                if ($record->template_company_id !== (int) $templateCompanyId) {
                    $record->update(['template_company_id' => $templateCompanyId]);
                    $record->refresh();
                }

                $pdfService = new \App\Services\EstimatePdfService();
                $finalPdfOutput = $pdfService->generate($record);

                return response()->streamDownload(function () use ($finalPdfOutput) {
                    echo $finalPdfOutput;
                }, "Estimate-{$record->documentNumber()}.pdf");
            });

        return $downloadAction;
    }

    public function getClientOrLeadAttribute()
    {
        return $this->client ?? $this->lead;
    }

    public function archive(): void
    {
        $this->update(['archived_at' => now()]);
    }

    public function unarchive(): void
    {
        $this->update(['archived_at' => null]);
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->whereNotNull('archived_at');
    }

    public function scopeNotArchived(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }
}
