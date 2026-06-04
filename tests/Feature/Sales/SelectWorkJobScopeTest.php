<?php

namespace Tests\Feature\Sales;

use App\Filament\Company\Resources\Sales\EstimateResource;
use App\Filament\Company\Resources\Sales\EstimateResource\Pages\EditEstimate;
use App\Filament\Company\Resources\Sales\VariationOrderResource;
use App\Filament\Company\Resources\Sales\VariationOrderResource\Pages\EditVariationOrder;
use App\Models\Accounting\DocumentLineItemGroup;
use App\Models\Accounting\Estimate;
use App\Models\Accounting\VariationOrder;
use App\Models\Common\Client;
use App\Models\Common\OfferingCategory;
use Illuminate\Support\Str;

use function Pest\Livewire\livewire;

/**
 * Regression tests for the bug where "Select Work" (header action) replaced all
 * lineItemGroups array keys with sequential numeric integers.  Filament's Repeater
 * resolves extraItemActions (e.g. "Select Job Scope") by looking up the item in
 * component state via the key dispatched from the browser, so numeric keys caused
 * "Select Job Scope" to silently fail on groups that were newly added by selectWork.
 *
 * Fix: preserve original UUID keys for existing groups and generate UUID keys for
 * new groups instead of appending with $newGroupsList[] (which yields numeric keys).
 */

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function createParentCategory(int $companyId, string $name): OfferingCategory
{
    return OfferingCategory::create([
        'company_id' => $companyId,
        'name' => $name,
    ]);
}

// ---------------------------------------------------------------------------
// EditEstimate
// ---------------------------------------------------------------------------

describe('EditEstimate selectWork / add_job_scope', function () {
    beforeEach(function () {
        EstimateResource::skipAuthorization();
        $this->withOfferings();

        $this->client = Client::factory()->create(['company_id' => $this->testCompany->id]);

        $this->catA = createParentCategory($this->testCompany->id, 'Category A');
        $this->catB = createParentCategory($this->testCompany->id, 'Category B');

        $this->estimate = Estimate::factory()
            ->for($this->testCompany)
            ->create([
                'client_id' => $this->client->id,
                'currency_code' => 'USD',
                'created_by' => $this->testUser->id,
                'updated_by' => $this->testUser->id,
            ]);

        // Existing group linked to Category A (simulates a group already in the form)
        DocumentLineItemGroup::create([
            'company_id' => $this->testCompany->id,
            'documentable_type' => $this->estimate->getMorphClass(),
            'documentable_id' => $this->estimate->id,
            'offering_category_id' => $this->catA->id,
            'name' => $this->catA->name,
            'order' => 1,
        ]);
    });

    it('selectWork gives all groups non-integer string keys (not 0, 1, 2...)', function () {
        $component = livewire(EditEstimate::class, ['record' => $this->estimate->getRouteKey()]);

        $component
            ->mountAction('selectWork')
            ->setActionData(['categories' => [(string) $this->catA->id, (string) $this->catB->id]])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $groups = $component->get('data.lineItemGroups');

        expect($groups)->toBeArray()->not->toBeEmpty();

        foreach (array_keys($groups) as $key) {
            // Keys must never be plain integers (0, 1, 2...) — Filament uses them as Repeater item identifiers.
            // Existing DB groups get 'record-{id}' keys; new unsaved groups get UUID keys. Both are valid.
            expect(is_int($key))->toBeFalse("Group key '{$key}' is a plain integer; Filament extraItemActions will fail.");
        }
    });

    it('selectWork adds new group with correct offering_category_id and UUID key', function () {
        $component = livewire(EditEstimate::class, ['record' => $this->estimate->getRouteKey()]);

        $component
            ->mountAction('selectWork')
            ->setActionData(['categories' => [(string) $this->catA->id, (string) $this->catB->id]])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $groups = $component->get('data.lineItemGroups');

        $newGroup = collect($groups)->firstWhere('offering_category_id', $this->catB->id);

        expect($newGroup)->not->toBeNull('Expected a new group for Category B after selectWork.')
            ->and($newGroup['id'])->toBeNull('Newly added group should not have a DB id yet.');

        // The key of the new group must be a UUID (the regression: it was numeric before the fix)
        $newGroupKey = array_search($newGroup, $groups);
        expect(Str::isUuid((string) $newGroupKey))->toBeTrue(
            "New group key '{$newGroupKey}' is not a UUID; Filament extraItemActions will fail with numeric keys."
        );
    });

    it('add_job_scope action is mountable on a group added by selectWork', function () {
        $component = livewire(EditEstimate::class, ['record' => $this->estimate->getRouteKey()]);

        // Add Category B as a new group via selectWork
        $component
            ->mountAction('selectWork')
            ->setActionData(['categories' => [(string) $this->catA->id, (string) $this->catB->id]])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $groups = $component->get('data.lineItemGroups');
        $newGroup = collect($groups)->firstWhere('offering_category_id', $this->catB->id);
        $newKey = array_search($newGroup, $groups);

        // Before the fix this silently did nothing (numeric key lookup in Repeater state failed).
        // After the fix the modal must open, which mountFormComponentAction asserts internally.
        $component->mountFormComponentAction(
            'data.lineItemGroups',
            'add_job_scope',
            ['item' => $newKey],
        );
    });
});

// ---------------------------------------------------------------------------
// EditVariationOrder
// ---------------------------------------------------------------------------

describe('EditVariationOrder selectWork / add_job_scope', function () {
    beforeEach(function () {
        VariationOrderResource::skipAuthorization();

        $this->catA = createParentCategory($this->testCompany->id, 'VO Category A');
        $this->catB = createParentCategory($this->testCompany->id, 'VO Category B');

        $this->variationOrder = VariationOrder::create([
            'company_id' => $this->testCompany->id,
            'currency_code' => 'USD',
            'date' => now()->toDateString(),
            'status' => \App\Enums\Accounting\VariationOrderStatus::Draft,
            'created_by' => $this->testUser->id,
            'updated_by' => $this->testUser->id,
        ]);

        DocumentLineItemGroup::create([
            'company_id' => $this->testCompany->id,
            'documentable_type' => $this->variationOrder->getMorphClass(),
            'documentable_id' => $this->variationOrder->id,
            'offering_category_id' => $this->catA->id,
            'name' => $this->catA->name,
            'order' => 1,
        ]);
    });

    it('selectWork gives all groups non-integer string keys (not 0, 1, 2...)', function () {
        $component = livewire(EditVariationOrder::class, ['record' => $this->variationOrder->getRouteKey()]);

        $component
            ->mountAction('selectWork')
            ->setActionData(['categories' => [(string) $this->catA->id, (string) $this->catB->id]])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $groups = $component->get('data.lineItemGroups');

        expect($groups)->toBeArray()->not->toBeEmpty();

        foreach (array_keys($groups) as $key) {
            // Keys must never be plain integers (0, 1, 2...) — Filament uses them as Repeater item identifiers.
            // Existing DB groups get 'record-{id}' keys; new unsaved groups get UUID keys. Both are valid.
            expect(is_int($key))->toBeFalse("Group key '{$key}' is a plain integer; Filament extraItemActions will fail.");
        }
    });

    it('selectWork adds new group with correct offering_category_id and UUID key', function () {
        $component = livewire(EditVariationOrder::class, ['record' => $this->variationOrder->getRouteKey()]);

        $component
            ->mountAction('selectWork')
            ->setActionData(['categories' => [(string) $this->catA->id, (string) $this->catB->id]])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $groups = $component->get('data.lineItemGroups');
        $newGroup = collect($groups)->firstWhere('offering_category_id', $this->catB->id);

        expect($newGroup)->not->toBeNull('Expected a new group for VO Category B after selectWork.')
            ->and($newGroup['id'])->toBeNull('Newly added group should not have a DB id yet.');

        $newGroupKey = array_search($newGroup, $groups);
        expect(Str::isUuid((string) $newGroupKey))->toBeTrue(
            "New group key '{$newGroupKey}' is not a UUID; Filament extraItemActions will fail with numeric keys."
        );
    });

    it('add_job_scope action is mountable on a group added by selectWork', function () {
        $component = livewire(EditVariationOrder::class, ['record' => $this->variationOrder->getRouteKey()]);

        $component
            ->mountAction('selectWork')
            ->setActionData(['categories' => [(string) $this->catA->id, (string) $this->catB->id]])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $groups = $component->get('data.lineItemGroups');
        $newGroup = collect($groups)->firstWhere('offering_category_id', $this->catB->id);
        $newKey = array_search($newGroup, $groups);

        $component->mountFormComponentAction(
            'data.lineItemGroups',
            'add_job_scope',
            ['item' => $newKey],
        );
    });
});
