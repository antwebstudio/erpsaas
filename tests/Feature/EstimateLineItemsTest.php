<?php

use App\Enums\Accounting\EstimateStatus;
use App\Filament\Company\Resources\Sales\EstimateResource\Pages\EditEstimate;
use App\Models\Accounting\DocumentLineItem;
use App\Models\Accounting\DocumentLineItemGroup;
use App\Models\Accounting\Estimate;
use App\Models\Common\Client;
use App\Models\Common\Offering;
use Livewire\Livewire;

test('it deletes removed line item groups', function () {
    $company = $this->testCompany;

    $offering = Offering::factory()->for($company)->create(['price' => 100]);
    $client = Client::factory()->for($company)->create();

    $estimate = new Estimate;
    $estimate->company_id = $company->id;
    $estimate->client_id = $client->id;
    $estimate->estimate_number = 'EST-001';
    $estimate->date = '2026-01-01';
    $estimate->expiration_date = '2026-02-01';
    $estimate->status = EstimateStatus::Draft;
    $estimate->currency_code = $company->default->currency_code ?? 'USD';
    $estimate->save();

    // Create Group A
    $groupA = $estimate->lineItemGroups()->create([
        'company_id' => $company->id,
        'name' => 'Group A',
        'order' => 1,
    ]);
    $itemA = $estimate->lineItems()->create([
        'group_id' => $groupA->id,
        'offering_id' => $offering->id,
        'quantity' => 1,
        'unit_price' => 100,
        'subtotal' => 100,
        'total' => 100,
        'line_number' => 1,
    ]);

    // Create Group B
    $groupB = $estimate->lineItemGroups()->create([
        'company_id' => $company->id,
        'name' => 'Group B',
        'order' => 2,
    ]);
    $itemB = $estimate->lineItems()->create([
        'group_id' => $groupB->id,
        'offering_id' => $offering->id,
        'quantity' => 1,
        'unit_price' => 200,
        'subtotal' => 200,
        'total' => 200,
        'line_number' => 2,
    ]);

    // Simulate form submission where Group A is REMOVED, but Group B is RETAINED.
    // Use set() instead of fillForm() so the repeater state is fully replaced
    // (fillForm uses Arr::dot which merges nested keys rather than replacing the whole array).
    $livewire = Livewire::test(EditEstimate::class, ['record' => $estimate->getRouteKey()]);

    $formGroups = $livewire->instance()->data['lineItemGroups'] ?? [];
    $formGroups = array_filter(
        $formGroups,
        fn ($group) => (int) ($group['id'] ?? 0) !== (int) $groupA->id
    );

    $livewire->set('data.lineItemGroups', $formGroups)
        ->call('save')
        ->assertHasNoFormErrors();

    // Group A and its items should be deleted
    expect(DocumentLineItemGroup::find($groupA->id))->toBeNull();
    expect(DocumentLineItem::find($itemA->id))->toBeNull();

    // Group B should remain
    expect(DocumentLineItemGroup::find($groupB->id))->not->toBeNull();
});
