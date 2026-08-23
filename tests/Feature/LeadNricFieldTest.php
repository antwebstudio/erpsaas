<?php

use App\Filament\Company\Resources\Sales\LeadResource;
use App\Filament\Company\Resources\Sales\LeadResource\Pages\CreateLead;
use App\Filament\Company\Resources\Sales\LeadResource\Pages\EditLead;
use App\Models\Common\Contact;
use App\Models\Common\Lead;
use Illuminate\Support\Facades\Queue;

use function Pest\Livewire\livewire;

beforeEach(function () {
    LeadResource::skipAuthorization();
});

it('exposes an editable NRIC field on the create lead form', function () {
    livewire(CreateLead::class)
        ->assertFormFieldExists('nric')
        ->fillForm(['nric' => '5678'])
        ->assertHasNoFormErrors(['nric']);
});

it('persists the NRIC entered while editing a lead', function () {
    Queue::fake();

    $lead = Lead::factory()->for($this->testCompany)->create(['nric' => null]);

    Contact::factory()
        ->primary()
        ->for($lead, 'contactable')
        ->create([
            'company_id' => $lead->company_id,
            'email' => 'lead@example.com',
            'phones' => [['type' => 'primary', 'data' => ['number' => '5551234']]],
        ]);

    livewire(EditLead::class, ['record' => $lead->getRouteKey()])
        ->assertFormFieldExists('nric')
        ->fillForm(['nric' => '1234'])
        ->assertHasNoFormErrors(['nric'])
        ->call('save');

    expect($lead->fresh()->nric)->toBe('1234');
});
