<?php

namespace App\Filament\User\Pages;

use App\Models\Common\OfferingCategory;
use App\Models\Accounting\VariationOrder;
use App\Models\Accounting\DocumentLineItemGroup;
use App\Filament\Company\Resources\Sales\VariationOrderResource;
use App\Enums\Accounting\VariationOrderStatus;
use App\Models\Setting\DocumentDefault;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Wallo\FilamentCompanies\FilamentCompanies;
use Filament\Notifications\Notification;
use App\Enums\Accounting\DocumentDiscountMethod;
use App\Enums\Accounting\AdjustmentComputation;

class CreateVariationOrder extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-plus';

    public static function shouldRegisterNavigation(): bool
    {
        return ! config('erp.hide_estimate_in_navigation', false);
    }

    protected static string $view = 'filament.user.pages.create-variation-order';

    protected static ?string $navigationLabel = 'Variation Order Builder';

    protected static ?string $title = 'Variation Order Builder';

    public array $data = [];
    public ?int $variationOrderId = null;

    public function mount()
    {
        ini_set('memory_limit', '1024M');
        $clientId = request()->query('client');
        $this->variationOrderId = request()->query('variation_order_id') ? (int) request()->query('variation_order_id') : null;

        $this->loadData($clientId ? (int) $clientId : null);
    }

    public function loadData(?int $clientId = null)
    {
        $scopes = OfferingCategory::query()
            ->whereNull('parent_id')
            ->defaultOrder()
            ->with(['children' => fn($q) => $q->defaultOrder(), 'children.offerings'])
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

        // Initialize from existing variation order if provided
        $existingVariationOrder = null;
        if ($this->variationOrderId) {
            $existingVariationOrder = VariationOrder::with(['lineItemGroups', 'lineItems'])->find($this->variationOrderId);
            if ($existingVariationOrder) {
                $clientId = $existingVariationOrder->client_id;
            }
        }

        $this->data['client_id'] = $clientId;
        if ($clientId) {
            $client = $clients->find($clientId);
            $this->data['client_name'] = $client ? $client->name : null;
        } else {
            $this->data['client_name'] = null;
        }

        $this->data['initial_step'] = $clientId ? 1 : 0;

        $selectedScopeIds = [];
        if ($existingVariationOrder) {
            $selectedScopeIds = $existingVariationOrder->lineItemGroups->pluck('offering_category_id')->filter()->toArray();
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
                        'selected' => false,
                        'items' => $desc->offerings->map(function ($item) {
                            return [
                                'id' => $item->id,
                                'name' => $item->name,
                                'qty' => 1,
                                'uom' => $item->unit,
                                'price' => isset($item->price) ? $item->price / 100 : 0,
                                'selected' => false,
                            ];
                        })->toArray()
                    ];
                })->toArray()
            ];
        })->toArray();
    }

    public function create()
    {
        if (empty($this->data['client_id'])) {
            Notification::make()
                ->title('No client selected')
                ->body('Please select a client to create a variation order.')
                ->danger()
                ->send();
            return;
        }

        $selectedScopes = collect($this->data['scopes'])->where('selected', true);

        if ($selectedScopes->isEmpty()) {
            Notification::make()
                ->title('No scope selected')
                ->body('Please select at least one work scope.')
                ->danger()
                ->send();
            return;
        }

        $user = Auth::user();
        $company = $user->currentCompany;
        $settings = $company->defaultVariationOrder;

        // Create or Update Variation Order
        if ($this->variationOrderId) {
            $variationOrder = VariationOrder::find($this->variationOrderId);
            $variationOrder->update([
                'client_id' => $this->data['client_id'],
                'updated_by' => $user->id,
            ]);
        } else {
            $client = \App\Models\Common\Client::find($this->data['client_id']);
            $currencyCode = $client?->currency_code ?? \App\Utilities\Currency\CurrencyAccessor::getDefaultCurrency() ?? 'SGD';

            $variationOrder = VariationOrder::create([
                'company_id' => $company->id,
                'client_id' => $this->data['client_id'],
                'vo_number' => VariationOrder::getNextDocumentNumber($company),
                'header' => $settings->header ?? '',
                'subheader' => $settings->subheader ?? '',
                'date' => now(),
                'expiry_date' => now()->addDays(30),
                'status' => VariationOrderStatus::Draft,
                'currency_code' => $currencyCode,
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

        // Update Managed Groups and Items
        $managedGroups = $variationOrder->lineItemGroups()->whereNotNull('offering_category_id')->get();
        $selectedScopeIds = $selectedScopes->pluck('id')->toArray();

        // Remove groups that are no longer selected
        $groupsToDelete = $managedGroups->whereNotIn('offering_category_id', $selectedScopeIds);
        foreach ($groupsToDelete as $group) {
            $group->children()->each(function($child) {
                $child->items()->delete();
                $child->delete();
            });
            $group->items()->delete();
            $group->delete();
        }

        $order = 1;

        // Create or update parent groups
        foreach ($selectedScopes as $scope) {
            $existingGroup = $managedGroups->where('offering_category_id', $scope['id'])->first();

            if ($existingGroup) {
                $existingGroup->update([
                    'order' => $order,
                    'name' => $scope['name'],
                ]);
            } else {
                $variationOrder->lineItemGroups()->create([
                    'offering_category_id' => $scope['id'],
                    'name' => $scope['name'],
                    'company_id' => $company->id,
                    'order' => $order,
                    'parent_id' => null,
                ]);
            }

            $order++;
        }

        // Push custom groups to end
        $customGroups = $variationOrder->lineItemGroups()->whereNull('offering_category_id')->orderBy('order')->get();
        foreach ($customGroups as $group) {
            $group->update(['order' => $order++]);
        }

        $grandTotal = $variationOrder->lineItems()->sum('total');
        $variationOrder->update([
            'subtotal' => $grandTotal,
            'total' => $grandTotal,
        ]);

        Notification::make()
            ->title($this->variationOrderId ? 'Variation order updated' : 'Variation order created')
            ->success()
            ->send();

        return redirect()->to(VariationOrderResource::getUrl('edit', ['record' => $variationOrder, 'tenant' => $company], panel: 'company'));
    }
}
