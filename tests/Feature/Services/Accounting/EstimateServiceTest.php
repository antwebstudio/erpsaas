<?php

use App\Enums\Accounting\EstimateStatus;
use App\Models\Accounting\Estimate;
use App\Models\Common\Client;
use App\Models\Common\Lead;
use App\Models\Common\Offering;
use App\Models\Company;
use App\Models\User;
use App\Services\Accounting\EstimateService;

test('it converts estimate to contract and updates client details', function () {
    $service = app(EstimateService::class);

    $oldCompany = $this->testCompany;
    $this->withOfferings(); // Creates offerings for testCompany

    $newCompany = Company::factory()->withCompanyProfile()->withCompanyDefaults()->create();
    // Also create offerings for the new company since it might need them
    Offering::factory()->for($newCompany)->withSalesAdjustments()->create();

    $user = User::factory()->create([
        'name' => 'Original User',
        'email' => 'original@example.com',
    ]);

    $lead = Lead::factory()
        ->withPrimaryContact()
        ->withAddresses()
        ->create([
            'company_id' => $oldCompany->id,
            'name' => 'Old Lead Name',
            'nric' => '1234',
        ]);

    $estimate = Estimate::factory()->create([
        'company_id' => $oldCompany->id,
        'template_company_id' => $newCompany->id,
        'client_id' => $lead->id,
        'created_by' => $user->id,
        'status' => EstimateStatus::Sent,
    ]);

    $data = [
        'estimate_number' => 'NEW-EST-001',
        'reference_number' => 'NEW-REF-001',
        'date' => '2026-05-01',
        'client_name' => 'New Client Name',
        'client_nric' => '4321',
        'client_phone' => '12345678',
        'client_email' => 'client@example.com',
        'client_address_line_1' => '123 New St',
        'client_address_line_2' => 'Apt 4',
        'client_postal_code' => '12345',
        'client_country_code' => 'US',
        'salesperson_name' => 'New User Name',
        'salesperson_email' => 'newuser@example.com',
    ];

    $service->convertToContract($estimate, $data);

    $estimate->refresh();
    $client = Client::withoutGlobalScopes()->find($lead->id);
    $user->refresh();

    // 1. Verify estimate details updated
    expect($estimate->estimate_number)->toBe('NEW-EST-001')
        ->and($estimate->reference_number)->toBe('NEW-REF-001')
        ->and($estimate->date->format('Y-m-d'))->toBe('2026-05-01')
        ->and($estimate->status)->toBe(EstimateStatus::Accepted)
        ->and($estimate->company_id)->toBe($newCompany->id);

    // 2. Verify client and relationships
    expect($client->name)->toBe('New Client Name')
        ->and($client->nric)->toBe('4321')
        ->and($client->company_id)->toBe($newCompany->id);

    $contact = $client->primaryContact()->withoutGlobalScopes()->first();
    expect($contact)->not->toBeNull()
        ->and($contact->email)->toBe('client@example.com')
        ->and($contact->company_id)->toBe($newCompany->id);

    $phones = collect($contact->phones);
    $primaryPhone = $phones->firstWhere('type', 'primary');
    expect($primaryPhone['data']['number'])->toBe('12345678');

    $address = $client->billingAddress()->withoutGlobalScopes()->first();
    expect($address)->not->toBeNull()
        ->and($address->address_line_1)->toBe('123 New St')
        ->and($address->address_line_2)->toBe('Apt 4')
        ->and($address->postal_code)->toBe('12345')
        ->and($address->country_code)->toBe('US')
        ->and($address->company_id)->toBe($newCompany->id);

    // 3. Verify salesperson
    expect($user->name)->toBe('New User Name')
        ->and($user->email)->toBe('newuser@example.com');

    // 4. Verify Morph Class conversion - no records should be bound to Lead morph class
    $leadMorphClass = (new Lead)->getMorphClass();
    $clientMorphClass = $client->getMorphClass();

    if ($leadMorphClass !== $clientMorphClass) {
        $contactWithLeadMorph = \App\Models\Common\Contact::withoutGlobalScopes()
            ->where('contactable_type', $leadMorphClass)
            ->where('contactable_id', $client->id)
            ->exists();

        $addressWithLeadMorph = \App\Models\Common\Address::withoutGlobalScopes()
            ->where('addressable_type', $leadMorphClass)
            ->where('addressable_id', $client->id)
            ->exists();

        expect($contactWithLeadMorph)->toBeFalse('Contacts still bound to Lead morph class')
            ->and($addressWithLeadMorph)->toBeFalse('Addresses still bound to Lead morph class');
    }
});
