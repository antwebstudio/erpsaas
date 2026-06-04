<?php

namespace Tests\Feature\Accounting;

use App\Filament\Company\Resources\Sales\InvoiceResource;
use App\Filament\Company\Resources\Sales\InvoiceResource\Pages\CreateInvoice;
use App\Filament\Company\Resources\Sales\InvoiceResource\Pages\EditInvoice;
use App\Models\Accounting\DocumentLineItem;
use App\Models\Accounting\DocumentLineItemGroup;
use App\Models\Accounting\Invoice;
use App\Models\Common\Client;
use App\Models\Common\Offering;
use Illuminate\Support\Str;

use function Pest\Livewire\livewire;

beforeEach(function () {
    InvoiceResource::skipAuthorization();
    $this->withOfferings();
});

it('can create an invoice with grouped line items', function () {
    $offering1 = Offering::factory()->state(['sellable' => true])->for($this->testCompany)->create();
    $offering2 = Offering::factory()->state(['sellable' => true])->for($this->testCompany)->create();

    $groupId = (string) Str::uuid();
    $itemId = (string) Str::uuid();
    $itemId2 = (string) Str::uuid();

    $groupData = [
        $groupId => [
            'name' => 'Test Group 1',
            'order' => 1,
            'items' => [
                $itemId => [
                    'offering_id' => (string) $offering1->id,
                    'quantity' => 1,
                    'unit_price' => 100,
                ],
                $itemId2 => [
                    'offering_id' => (string) $offering2->id,
                    'quantity' => 2,
                    'unit_price' => 200,
                ],
            ],
        ],
    ];

    $client = Client::factory()->create(['company_id' => $this->testCompany->id]);

    livewire(CreateInvoice::class)
        ->fillForm([
            'client_id' => $client->id,
            'currency_code' => 'USD',
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'lineItemGroups' => $groupData,
        ])
        ->call('create')
        ->assertHasNoErrors();

    $invoice = Invoice::first();
    expect($invoice)->not->toBeNull();
    $groups = $invoice->lineItemGroups;
    expect($groups)->toHaveCount(1);

    $group = $groups->firstWhere('name', 'Test Group 1');
    expect($group)->not->toBeNull();
    expect($group->items)->toHaveCount(2);

    $item1 = $group->items->first();
    expect($item1->offering_id)->toBe($offering1->id);
    // Cast to float/int to match potential string output from DB
    expect((float) $item1->quantity)->toBe(1.0);
    expect((float) $item1->unit_price)->toBe(10000.0);
    expect($item1->group_id)->toBe($group->id);
});

it('can update an invoice with grouped line items', function () {
    $invoice = Invoice::factory()->for($this->testCompany)->create();
    $offering1 = Offering::factory()->state(['sellable' => true])->for($this->testCompany)->create();
    $offering2 = Offering::factory()->state(['sellable' => true])->for($this->testCompany)->create();

    // Create initial group and item
    $group = DocumentLineItemGroup::create([
        'company_id' => $invoice->company_id,
        'documentable_type' => $invoice->getMorphClass(),
        'documentable_id' => $invoice->id,
        'name' => 'Initial Group',
        'order' => 1,
    ]);

    $item = DocumentLineItem::create([
        'company_id' => $invoice->company_id,
        'documentable_type' => $invoice->getMorphClass(),
        'documentable_id' => $invoice->id,
        'group_id' => $group->id,
        'offering_id' => $offering1->id,
        'quantity' => 1,
        'unit_price' => 500,
        'line_number' => 1,
    ]);

    $component = livewire(EditInvoice::class, ['record' => $invoice->getRouteKey()]);
    $existingData = $component->get('data.lineItemGroups');
    $existingKey = array_key_first($existingData);

    $component
        ->set("data.lineItemGroups.{$existingKey}.name", 'Updated Group Name')
        ->call('save')
        ->assertHasNoErrors();

    $invoice->refresh();
    $group->refresh();

    expect($invoice->lineItemGroups)->toHaveCount(1);
    expect($group->name)->toBe('Updated Group Name');
});
