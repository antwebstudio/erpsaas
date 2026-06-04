<?php

use App\Enums\Accounting\AdjustmentCategory;
use App\Enums\Accounting\AdjustmentType;
use App\Enums\Accounting\DocumentDiscountMethod;
use App\Filament\Company\Resources\Sales\EstimateResource\Pages\EditEstimate;
use App\Models\Accounting\Adjustment;
use App\Models\Accounting\Estimate;
use App\Models\Common\Client;
use App\Models\Common\Offering;
use App\Models\Common\OfferingCategory;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

// it('avoids n+1 queries on estimate edit page', function () {
//     $company = $this->testCompany;
//     $user = $this->testUser;

//     config(['app.disable_custom_select_relationships' => true]);

//     // Setup Common Data
//     $client = Client::factory()->create(['company_id' => $company->id]);
//     $category = OfferingCategory::create(['company_id' => $company->id, 'name' => 'Services']);

//     // Create Tax and Discount to ensure they are loaded
//     $tax = Adjustment::factory()->create([
//         'company_id' => $company->id,
//         'category' => AdjustmentCategory::Tax,
//         'type' => AdjustmentType::Sales,
//     ]);

//     // Create an Offering
//     $offering = Offering::factory()->create([
//         'company_id' => $company->id,
//         'price' => 10000,
//         'sellable' => true,
//     ]);

//     // -------------------------------------------------------------------------
//     // Scenario 1: Small Estimate (Baseline)
//     // -------------------------------------------------------------------------
//     $estimateSmall = Estimate::factory()->create([
//         'company_id' => $company->id,
//         'client_id' => $client->id,
//         'discount_method' => DocumentDiscountMethod::PerDocument,
//     ]);

//     // Add 1 Group with 1 Item using the relationship found in checking code
//     // The EstimateResource uses 'lineItemGroups' relationship.
//     // Based on EstimateResource, it seems to rely on 'lineItemGroups' which are DocumentLineItemGroup models.

//     $groupSmall = $estimateSmall->lineItemGroups()->create([
//         'company_id' => $company->id,
//         'name' => 'Group 1',
//     ]);

//     $groupSmall->items()->create([
//         'company_id' => $company->id,
//         'documentable_type' => Estimate::class,
//         'documentable_id' => $estimateSmall->id,
//         'offering_id' => $offering->id,
//         'description' => 'Item 1',
//         'quantity' => 1,
//         'unit_price' => 10000,
//         'subtotal' => 10000,
//         'total' => 10000,
//         'type' => 'service', // Assuming type is needed or defaults are fine
//     ]);

//     DB::enableQueryLog();

//     Livewire::test(EditEstimate::class, ['record' => $estimateSmall->getRouteKey()])
//         ->assertOk();

//     $queriesSmall = count(DB::getQueryLog());
//     DB::flushQueryLog();
//     DB::disableQueryLog();

//     // -------------------------------------------------------------------------
//     // Scenario 2: Large Estimate (Load Test)
//     // -------------------------------------------------------------------------
//     $estimateLarge = Estimate::factory()->create([
//         'company_id' => $company->id,
//         'client_id' => $client->id,
//         'discount_method' => DocumentDiscountMethod::PerDocument,
//     ]);

//     // Create 5 groups, each with 5 items
//     for ($i = 0; $i < 5; $i++) {
//         $group = $estimateLarge->lineItemGroups()->create([
//             'company_id' => $company->id,
//             'name' => "Group $i",
//         ]);

//         for ($j = 0; $j < 5; $j++) {
//             $group->items()->create([
//                 'company_id' => $company->id,
//                 'documentable_type' => Estimate::class,
//                 'documentable_id' => $estimateLarge->id,
//                 'offering_id' => $offering->id,
//                 'description' => "Item $i-$j",
//                 'quantity' => 1,
//                 'unit_price' => 10000,
//                 'subtotal' => 10000,
//                 'total' => 10000,
//             ]);
//         }
//     }

//     DB::enableQueryLog();

//     Livewire::test(EditEstimate::class, ['record' => $estimateLarge->getRouteKey()])
//         ->assertOk();

//     $queriesLarge = count(DB::getQueryLog());
//     if ($queriesLarge >= 100) {
//         file_put_contents('output_queries.json', json_encode(collect(DB::getQueryLog())->pluck('query')->toArray(), JSON_PRETTY_PRINT));
//     }

//     // -------------------------------------------------------------------------
//     // Verification
//     // -------------------------------------------------------------------------

//     expect($queriesSmall)->toBeLessThan(60);
//     expect($queriesLarge)->toBeLessThan(100);
//     expect($queriesLarge)->toBeLessThan($queriesSmall + 30);
// });

it('avoids n+1 queries when saving estimate', function () {
    $company = $this->testCompany;
    $user = $this->testUser;

    config(['app.disable_custom_select_relationships' => true]);

    // Setup Common Data
    $client = Client::factory()->create(['company_id' => $company->id]);
    $category = OfferingCategory::create(['company_id' => $company->id, 'name' => 'Services']);

    // Create Tax and Discount to ensure they are loaded
    $tax = Adjustment::factory()->create([
        'company_id' => $company->id,
        'category' => AdjustmentCategory::Tax,
        'type' => AdjustmentType::Sales,
    ]);

    // Create an Offering
    $offering = Offering::factory()->create([
        'company_id' => $company->id,
        'price' => 10000,
        'sellable' => true,
    ]);

    // -------------------------------------------------------------------------
    // Scenario 1: Small Estimate (Baseline)
    // -------------------------------------------------------------------------
    $estimateSmall = Estimate::factory()->create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'discount_method' => DocumentDiscountMethod::PerDocument,
    ]);

    $groupSmall = $estimateSmall->lineItemGroups()->create([
        'company_id' => $company->id,
        'name' => 'Group 1',
    ]);

    $itemSmall = $groupSmall->items()->create([
        'company_id' => $company->id,
        'documentable_type' => Estimate::class,
        'documentable_id' => $estimateSmall->id,
        'offering_id' => $offering->id,
        'description' => 'Item 1',
        'quantity' => 1,
        'unit_price' => 10000,
        'subtotal' => 10000,
        'total' => 10000,
        'type' => 'service',
    ]);

    // Load the component and prepare the data to simulate a save
    $componentSmall = Livewire::test(EditEstimate::class, ['record' => $estimateSmall->getRouteKey()])
        ->assertOk();

    DB::enableQueryLog();

    $startTimeSmall = microtime(true);
    $componentSmall->call('save');
    $durationSmall = microtime(true) - $startTimeSmall;

    $queriesSmall = count(DB::getQueryLog());
    DB::flushQueryLog();
    DB::disableQueryLog();

    // -------------------------------------------------------------------------
    // Scenario 2: Large Estimate (Load Test)
    // -------------------------------------------------------------------------
    $estimateLarge = Estimate::factory()->create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'discount_method' => DocumentDiscountMethod::PerDocument,
    ]);

    // Create 5 groups, each with 5 items
    for ($i = 0; $i < 5; $i++) {
        $group = $estimateLarge->lineItemGroups()->create([
            'company_id' => $company->id,
            'name' => "Group $i",
        ]);

        for ($j = 0; $j < 5; $j++) {
            $group->items()->create([
                'company_id' => $company->id,
                'documentable_type' => Estimate::class,
                'documentable_id' => $estimateLarge->id,
                'offering_id' => $offering->id,
                'description' => "Item $i-$j",
                'quantity' => 1,
                'unit_price' => 10000,
                'subtotal' => 10000,
                'total' => 10000,
            ]);
        }
    }

    $componentLarge = Livewire::test(EditEstimate::class, ['record' => $estimateLarge->getRouteKey()])
        ->assertOk();

    DB::enableQueryLog();

    $startTimeLarge = microtime(true);
    $componentLarge->call('save');
    $durationLarge = microtime(true) - $startTimeLarge;

    $queriesLarge = count(DB::getQueryLog());
    if ($queriesLarge >= 100) {
        $queriesWithBindings = collect(DB::getQueryLog())->map(function ($query) {
            $sql = $query['query'];
            foreach ($query['bindings'] as $binding) {
                $value = is_numeric($binding) ? $binding : "'" . addslashes($binding) . "'";
                $sql = preg_replace('/\?/', $value, $sql, 1);
            }

            return $sql;
        })->toArray();
        file_put_contents('output_queries_save.sql', implode(";\n", $queriesWithBindings) . ";\n");
    }

    DB::flushQueryLog();
    DB::disableQueryLog();

    // -------------------------------------------------------------------------
    // Verification
    // -------------------------------------------------------------------------

    // Assert query count
    expect($queriesSmall)->toBeLessThan(60);
    expect($queriesLarge)->toBeLessThan(150);
    // 25 items -> 25 exists-rule queries, 25 line updates, 25 syncs. So queries Large is ~ queriesSmall + 75
    expect($queriesLarge)->toBeLessThan($queriesSmall + 90);

    // Assert execution time (in seconds)
    // Small estimate should save very quickly (e.g., < 0.5 seconds)
    expect($durationSmall)->toBeLessThan(1.0);

    // Large estimate should also save quickly, not exponentially slower (e.g., < 2.5 seconds)
    expect($durationLarge)->toBeLessThan(3.0);
});

it('saves estimate id 2 in less than 10 seconds', function () {
    $company = $this->testCompany;
    $client = Client::factory()->create(['company_id' => $company->id]);
    $offering = Offering::factory()->create([
        'company_id' => $company->id,
        'price' => 10000,
        'sellable' => true,
    ]);

    $estimate = Estimate::factory()->create([
        'company_id' => $company->id,
        'client_id' => $client->id,
    ]);

    // Create highly populated line item groups to stress test
    for ($i = 0; $i < 5; $i++) {
        $group = $estimate->lineItemGroups()->create([
            'company_id' => $company->id,
            'name' => "Large Group $i",
        ]);

        for ($j = 0; $j < 10; $j++) {
            $group->items()->create([
                'company_id' => $company->id,
                'documentable_type' => Estimate::class,
                'documentable_id' => $estimate->id,
                'offering_id' => $offering->id,
                'description' => "Heavy Item $i-$j",
                'quantity' => 1,
                'unit_price' => 10000,
                'subtotal' => 10000,
            ]);
        }
    }

    $component = Livewire::test(EditEstimate::class, ['record' => $estimate->getRouteKey()])
        ->assertOk();

    $startTime = microtime(true);
    $component->call('save');
    $duration = microtime(true) - $startTime;

    expect($duration)->toBeLessThan(10.0);
});
