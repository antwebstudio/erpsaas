<?php

namespace App\Filament\User\Pages;

use App\Models\Common\OfferingCategory;
use App\Models\Accounting\Estimate;
use App\Models\Accounting\DocumentLineItemGroup;
use App\Filament\Company\Resources\Sales\EstimateResource;
use App\Enums\Accounting\EstimateStatus;
use App\Models\Setting\DocumentDefault;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Wallo\FilamentCompanies\FilamentCompanies;
use Filament\Notifications\Notification;
use App\Enums\Accounting\DocumentDiscountMethod;
use App\Enums\Accounting\AdjustmentComputation;

class CreateQuotation extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.user.pages.create-quotation';

    protected static ?string $navigationLabel = 'Quotation Builder';

    protected static ?string $title = 'User Quotation Builder';
    
    public array $data = [];
    public ?int $estimateId = null;

    public function mount()
    {
        $clientId = request()->query('client');
        $this->estimateId = request()->query('estimate_id') ? (int) request()->query('estimate_id') : null;
        
        $this->loadData($clientId ? (int) $clientId : null);
    }

    public function loadData(?int $clientId = null)
    {
        $scopes = OfferingCategory::query()
            ->whereNull('parent_id')
            ->with(['children.offerings'])
            ->get();

        $clients = \App\Models\Common\Client::query()
            ->withoutGlobalScope('type')
            ->orderBy('type')
            ->orderBy('name')
            ->get(['id', 'name', 'type']);

        $this->data['clients'] = $clients->map(function ($client) {
            return [
                'id' => $client->id,
                'name' => $client->name . ' (' . ucfirst($client->type) . ')',
            ];
        })->toArray();

        // Initialize from existing estimate if provided
        $existingEstimate = null;
        if ($this->estimateId) {
            $existingEstimate = Estimate::with(['lineItemGroups', 'lineItems'])->find($this->estimateId);
            if ($existingEstimate) {
                $clientId = $existingEstimate->client_id;
            }
        }

        $this->data['client_id'] = $clientId;
        if ($clientId) {
            $client = $clients->find($clientId);
            $this->data['client_name'] = $client ? $client->name : null;
        } else {
            $this->data['client_name'] = null;
        }

        // Determine initial step
        // Step 0: Client Selection (if no client)
        // Step 1: Scope Selection (if client is set)
        $this->data['initial_step'] = $clientId ? 1 : 0;

        $selectedScopeIds = [];
        if ($existingEstimate) {
            $selectedScopeIds = $existingEstimate->lineItemGroups->pluck('offering_category_id')->filter()->toArray();
        }

        $this->data['scopes'] = $scopes->map(function ($scope) use ($selectedScopeIds) {
            $isScopeSelected = in_array($scope->id, $selectedScopeIds);
            
            return [
                'id' => $scope->id,
                'name' => $scope->name,
                'selected' => $isScopeSelected,
                'descriptions' => $scope->children->map(function ($desc) {
                    return [
                        'id' => $desc->id,
                        'text' => $desc->name,
                        'description' => $desc->description,
                        'selected' => false, // We'll auto-select these in Alpine
                        'items' => $desc->offerings->map(function ($item) {
                            return [
                                'id' => $item->id,
                                'name' => $item->name,
                                'qty' => 1,
                                'uom' => $item->unit,
                                'price' => isset($item->price) ? $item->price / 100 : 0,
                                'selected' => false, // Items for now manual
                            ];
                        })->toArray()
                    ];
                })->toArray()
            ];
        })->toArray();
    }

    public function create()
    {
        // 1. Validation
        if (empty($this->data['client_id'])) {
            Notification::make()
                ->title('No client selected')
                ->body('Please select a client to create a quotation.')
                ->danger()
                ->send();
            return;
        }

        $selectedScopes = collect($this->data['scopes'])->where('selected', true);

        if ($selectedScopes->isEmpty()) {
            Notification::make()
                ->title('No scopes selected')
                ->body('Please select at least one work scope.')
                ->danger()
                ->send();
            return;
        }

        $user = Auth::user();
        $company = $user->currentCompany;
        $settings = $company->defaultEstimate;
        
        // 2. Create or Update Estimate
        if ($this->estimateId) {
            $estimate = Estimate::find($this->estimateId);
            $estimate->update([
                'client_id' => $this->data['client_id'],
                'updated_by' => $user->id,
            ]);
        } else {
            $estimate = Estimate::create([
                'company_id' => $company->id,
                'client_id' => $this->data['client_id'],
                'estimate_number' => Estimate::getNextDocumentNumber($company),
                'header' => $settings->header ?? '',
                'subheader' => $settings->subheader ?? '',
                'date' => now(),
                'expiration_date' => now()->addDays(30), 
                'status' => EstimateStatus::Draft,
                'currency_code' => $company->currency_code ?? 'USD',
                'discount_method' => DocumentDiscountMethod::PerLineItem, 
                'discount_computation' => AdjustmentComputation::Percentage,
                'discount_rate' => 0,
                'subtotal' => 0,
                'tax_total' => 0,
                'discount_total' => 0,
                'total' => 0,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);
        }

        // 3. Update Managed Groups and Items
        $managedGroups = $estimate->lineItemGroups()->whereNotNull('offering_category_id')->get();
        $selectedScopeIds = $selectedScopes->pluck('id')->toArray();

        // 3.1 Remove groups that are no longer selected
        $groupsToDelete = $managedGroups->whereNotIn('offering_category_id', $selectedScopeIds);
        foreach ($groupsToDelete as $group) {
            $group->items()->delete();
            $group->delete();
        }

        $order = 1;

        // 3.2 Update existing groups and create new ones
        foreach ($selectedScopes as $scope) {
            $existingGroup = $managedGroups->where('offering_category_id', $scope['id'])->first();

            if ($existingGroup) {
                // Update order for existing group
                $existingGroup->update([
                    'order' => $order++,
                    'name' => $scope['name'],
                ]);
            } else {
                // Create new group
                $group = $estimate->lineItemGroups()->create([
                    'offering_category_id' => $scope['id'],
                    'name' => $scope['name'],
                    'company_id' => $company->id,
                    'order' => $order++,
                ]);

                if ($addAllItems ?? false) {
                    foreach ($scope['descriptions'] as $desc) {
                        foreach ($desc['items'] as $item) {
                            $itemPrice = $item['price'] * 100;
                            $lineTotal = $item['qty'] * $itemPrice;

                            $estimate->lineItems()->create([
                                'group_id' => $group->id,
                                'offering_id' => $item['id'],
                                'name' => $item['name'], 
                                'description' => $item['name'],
                                'quantity' => $item['qty'],
                                'unit' => $item['uom'],
                                'unit_price' => $itemPrice,
                                'subtotal' => $lineTotal,
                                'total' => $lineTotal,
                                'company_id' => $company->id,
                            ]);
                        }
                    }
                }
            }
        }

        // 5. Update Order for Custom Groups (Push to end)
        $customGroups = $estimate->lineItemGroups()->whereNull('offering_category_id')->orderBy('order')->get();
        foreach ($customGroups as $group) {
            $group->update(['order' => $order++]);
        }
        
        // Recalculate totals
        $grandTotal = $estimate->lineItems()->sum('total');
        $estimate->update([
            'subtotal' => $grandTotal,
            'total' => $grandTotal,
        ]);

        Notification::make()
            ->title($this->estimateId ? 'Quotation updated' : 'Quotation created')
            ->success()
            ->send();
        
        return redirect()->to(EstimateResource::getUrl('edit', ['record' => $estimate, 'tenant' => $company], panel: 'company'));
    }
}
