<?php

use App\Models\Accounting\DocumentLineItemGroup;
use App\Models\Accounting\Invoice;
use App\Models\Common\OfferingCategory;

beforeEach(function () {
    $this->withOfferings();
});

it('can associate a document line item group with an offering category', function () {
    $category = OfferingCategory::create([
        'company_id' => $this->testCompany->id,
        'name' => 'Test Category',
    ]);

    $invoice = Invoice::factory()->for($this->testCompany)->create();

    $group = DocumentLineItemGroup::create([
        'company_id' => $this->testCompany->id,
        'documentable_type' => $invoice->getMorphClass(),
        'documentable_id' => $invoice->id,
        'offering_category_id' => $category->id,
        'name' => 'Group with Category',
    ]);

    expect($group->offeringCategory->id)->toBe($category->id);
    expect($category->documentLineItemGroups)->toHaveCount(1);
    expect($category->documentLineItemGroups->first()->id)->toBe($group->id);
});
