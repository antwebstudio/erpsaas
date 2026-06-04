<?php

$bootStart = microtime(true);

require __DIR__ . '/vendor/autoload.php';

/**
 * Executes a callback and reports execution time and DB query count.
 *
 * @param  callable  $callback  The function to execute.
 * @return mixed The callback's return value.
 */
function executeWithMetrics(callable $callback, string $title = 'Execution started', $executeTime = 1, $sleepTime = 0)
{
    // Reset query log for accurate measurement
    DB::flushQueryLog();
    DB::enableQueryLog();

    echo "\n======================================\n";
    echo $title . "\n";
    echo "======================================\n";

    $duration = 0;
    for ($i = 0; $i < $executeTime; $i++) {
        $start = microtime(true);
        $result = $callback();
        $duration += microtime(true) - $start;
        sleep($sleepTime);
    }

    $queries = DB::getQueryLog();
    $queryCount = count($queries);

    echo "-------------------------------\n";
    echo 'Execution time: ' . number_format($duration, 4) . " seconds\n";
    echo 'Average execution time: ' . number_format($duration / $executeTime, 4) . " seconds\n";
    echo 'Number of DB queries executed: ' . $queryCount . "\n";

    $log = DB::getQueryLog();
    $counter = 0;

    $queriesWithBindings = collect($log)->map(function ($query) use (&$counter) {
        $sql = $query['query'];
        foreach ($query['bindings'] as $binding) {
            if ($binding instanceof \DateTime) {
                $value = "'" . $binding->format('Y-m-d H:i:s') . "'";
            } elseif (is_bool($binding)) {
                $value = $binding ? '1' : '0';
            } elseif (is_null($binding)) {
                $value = 'NULL';
            } else {
                $value = is_numeric($binding) ? $binding : "'" . addslashes((string) $binding) . "'";
            }
            $sql = preg_replace('/\?/', (string) $value, $sql, 1);
        }
        $counter++;

        return "-- [{$counter}] Execution time: {$query['time']}ms\n" . $sql;
    })->toArray();

    file_put_contents('output_queries_executed.sql', implode(";\n\n", $queriesWithBindings) . ";\n");
    echo 'Saved ' . count($queriesWithBindings) . " queries to output_queries_executed.sql\n";

    foreach ($log as $l) {
        if ($l['time'] > 1000) { // > 1 second
            echo 'Slow query found (' . $l['time'] . 'ms): ' . $l['query'] . "\n";
        }
    }

    echo "-------------------------------\n\n";

    return $result;
}

$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$kernel->bootstrap();

$bootTime = microtime(true) - $bootStart;

// Set Memory Limit to avoid crash with large dataset
ini_set('memory_limit', '4G');

executeWithMetrics(function () {
    $user = \App\Models\User::first();
    if ($user) {
        \Illuminate\Support\Facades\Auth::login($user);
        $company = $user->ownedCompanies->first();
        if ($company) {
            $user->switchCompany($company);
            \Filament\Facades\Filament::setTenant($company);
        }
    }

    $estimate = \App\Models\Accounting\Estimate::find(1);

    $component = \Livewire\Livewire::test(\App\Filament\Company\Resources\Sales\EstimateResource\Pages\EditEstimate::class, [
        'tenant' => $company->id ?? null,
        'record' => $estimate->getRouteKey(),
    ]);

    $component->call('save');
});
