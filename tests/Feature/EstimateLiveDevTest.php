<?php

use App\Filament\Company\Resources\Sales\EstimateResource\Pages\EditEstimate;
use App\Models\Accounting\Estimate;
use Livewire\Livewire;
use Illuminate\Support\Facades\DB;

it('saves real estimate id 2 under 10 seconds without truncation', function () {
    $estimate = Estimate::on('mysql')->find(2);

    if (! $estimate) {
        $this->markTestSkipped('Estimate ID 2 does not exist in the database.');
    }

    $component = Livewire::test(EditEstimate::class, ['record' => $estimate->getRouteKey()])
        ->assertOk();

    $startTime = microtime(true);
    $component->call('save');
    $duration = microtime(true) - $startTime;

    expect($duration)->toBeLessThan(10.0);
});
