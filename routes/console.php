<?php

use App\Console\Commands\SyncContactsToMailchimp;
use App\Console\Commands\SyncEmailTemplatesToMailchimp;
use App\Console\Commands\TriggerRecurringInvoiceGeneration;
use App\Console\Commands\UpdateOverdueInvoices;
use Illuminate\Support\Facades\Schedule;

Schedule::command(UpdateOverdueInvoices::class)->everyFiveMinutes();
Schedule::command(TriggerRecurringInvoiceGeneration::class, ['--queue'])->everyMinute();
Schedule::command(SyncContactsToMailchimp::class)->hourly();
Schedule::command(SyncEmailTemplatesToMailchimp::class)->daily();
