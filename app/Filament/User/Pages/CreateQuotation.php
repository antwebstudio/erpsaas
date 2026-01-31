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

    public function mount()
    {
        $this->loadData();
    }

    public function loadData()
    {
        // Fetch Root Scopes (Offering Categories with no parent)
        // Adjust logic based on your actual data structure for specific "Scopes" if needed
        // Assuming 'JobScope' logic as per request - effectively root categories
        // We need to ensure we only get categories for the current company or are global?
        // OfferingCategory has CompanyOwned concern, so default scope should handle it if set.
        // NestedSet 'children' relation might need scoping? 
        
        $scopes = OfferingCategory::query()
            ->whereNull('parent_id')
            ->with(['children.offerings']) // Eager load Descriptions (children) and their Items (offerings)
            ->get();

        $this->data['scopes'] = $scopes->map(function ($scope) {
            return [
                'id' => $scope->id,
                'name' => $scope->name,
                'selected' => false,
                'descriptions' => $scope->children->map(function ($desc) {
                    return [
                        'id' => $desc->id,
                        'text' => $desc->name, // Using name as text initially
                        'description' => $desc->description, // Keep original description if needed
                        'selected' => false,
                        'items' => $desc->offerings->map(function ($item) {
                            return [
                                'id' => $item->id,
                                'name' => $item->name,
                                'qty' => 0,
                                'uom' => $item->unit ?? 'ls',
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
        // 1. Validation to ensure at least one item is selected
        $hasItems = false;
        foreach ($this->data['scopes'] as $scope) {
            if ($scope['selected']) {
                foreach ($scope['descriptions'] as $desc) {
                    if ($desc['selected']) {
                        foreach ($desc['items'] as $item) {
                            if ($item['selected']) {
                                $hasItems = true;
                                break 3;
                            }
                        }
                    }
                }
            }
        }

        if (!$hasItems) {
            Notification::make()
                ->title('No items selected')
                ->body('Please select at least one item to create a quotation.')
                ->danger()
                ->send();
            return;
        }

        $user = Auth::user();
        $company = $user->currentCompany;

        // 2. Create Estimate
        $estimate = Estimate::create([
            'company_id' => $company->id,
            'client_id' => null, // Or a default client if applicable? Leaving null for now or requires adjustment
            'estimate_number' => Estimate::getNextDocumentNumber($company),
            'date' => now(),
            'expiration_date' => now()->addDays(30), // Default 30 days
            'status' => EstimateStatus::Draft,
            'currency_code' => $company->currency_code ?? 'USD', // Default currency
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

        $grandTotal = 0;

        // 3. Process Selected Items
        foreach ($this->data['scopes'] as $scope) {
            if (!$scope['selected']) continue;

             // Check if any description in this scope is selected and has items
             $scopeHasItems = false;
             foreach($scope['descriptions'] as $desc) {
                 if ($desc['selected'] && collect($desc['items'])->contains('selected', true)) {
                     $scopeHasItems = true;
                     break;
                 }
             }
             
             $scopeGroup = null;
             if ($scopeHasItems) {
                 $scopeGroup = $estimate->lineItemGroups()->create([
                     'name' => $scope['name'],
                 ]);
             }

             foreach ($scope['descriptions'] as $desc) {
                 if (!$desc['selected']) continue;

                 foreach ($desc['items'] as $item) {
                     if (!$item['selected']) continue;

                     $itemPrice = $item['price'] * 100;
                     $lineTotal = $item['qty'] * $itemPrice;
                     $grandTotal += $lineTotal;

                     $estimate->lineItems()->create([
                         'group_id' => $scopeGroup?->id,
                         'name' => $item['name'], 
                         'description' => $item['name'], // Use item name (offering) as description
                         'quantity' => $item['qty'],
                         'unit_price' => $itemPrice,
                         'subtotal' => $lineTotal,
                         'total' => $lineTotal,
                     ]);
                 }
             }
        }
        
        $estimate->update([
            'subtotal' => $grandTotal,
            'total' => $grandTotal,
        ]);

        Notification::make()
            ->title('Quotation created successfully')
            ->success()
            ->send();
        
        return redirect()->to(EstimateResource::getUrl('edit', ['record' => $estimate, 'tenant' => $company], panel: 'company'));
    }
}
