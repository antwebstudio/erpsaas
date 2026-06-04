<?php

namespace App\Providers;

use App\Http\Responses\LoginRedirectResponse;
use App\Models\Common\Address;
use App\Models\Common\AllClient;
use App\Models\Common\Client;
use App\Models\Common\Contact;
use App\Models\Common\Lead;
use App\Models\Export;
use App\Models\Import;
use App\Models\Mail\MailLog;
use App\Models\Mail\MailSuppression;
use App\Models\Mail\MailTemplate;
use App\Models\Notification;
use App\Models\Permission;
use App\Models\Role;
use App\Observers\MailchimpBillingAddressObserver;
use App\Observers\MailchimpContactObserver;
use App\Observers\MailchimpPrimaryContactObserver;
use App\Observers\MailchimpTemplateObserver;
use App\Policies\Mail\MailLogPolicy;
use App\Policies\Mail\MailSuppressionPolicy;
use App\Policies\Mail\MailTemplatePolicy;
use App\Services\DateRangeService;
use Filament\Actions\Exports\Models\Export as BaseExport;
use Filament\Actions\Imports\Models\Import as BaseImport;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Filament\Notifications\Livewire\Notifications;
use Filament\Support\Assets\Js;
use Filament\Support\Enums\Alignment;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(DateRangeService::class);
        $this->app->singleton(LoginResponse::class, LoginRedirectResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Client::observe(MailchimpContactObserver::class);
        Lead::observe(MailchimpContactObserver::class);
        AllClient::observe(MailchimpContactObserver::class);
        Contact::observe(MailchimpPrimaryContactObserver::class);
        Address::observe(MailchimpBillingAddressObserver::class);
        MailTemplate::observe(MailchimpTemplateObserver::class);

        app(\Spatie\Permission\PermissionRegistrar::class)
            ->setPermissionClass(Permission::class)
            ->setRoleClass(Role::class);

        Gate::policy(MailTemplate::class, MailTemplatePolicy::class);
        Gate::policy(MailSuppression::class, MailSuppressionPolicy::class);
        Gate::policy(MailLog::class, MailLogPolicy::class);

        // Bind custom Import and Export models
        $this->app->bind(BaseImport::class, Import::class);
        $this->app->bind(BaseExport::class, Export::class);

        // Bind custom Notification model
        $this->app->bind(DatabaseNotification::class, Notification::class);

        Notifications::alignment(Alignment::Center);

        FilamentAsset::register([
            Js::make('top-navigation', __DIR__ . '/../../resources/js/top-navigation.js'),
            Js::make('history-fix', __DIR__ . '/../../resources/js/history-fix.js'),
            Js::make('custom-print', __DIR__ . '/../../resources/js/custom-print.js'),
        ]);

        if (config('app.env') !== 'testing' && \Illuminate\Support\Facades\Schema::hasTable('offerings')) {
            $offeringExists = \Illuminate\Support\Facades\DB::table('offerings')
                ->where('id', 0)
                ->exists();

            if (! $offeringExists) {
                // Get the first available company to avoid foreign key errors
                $companyId = \Illuminate\Support\Facades\DB::table('companies')->value('id');

                if ($companyId) {
                    try {
                        // Ensure ID 0 can be inserted (MySQL specific, but safe to try)
                        \Illuminate\Support\Facades\DB::statement("SET SESSION sql_mode='NO_AUTO_VALUE_ON_ZERO'");
                    } catch (\Exception $e) {
                        // Ignore if sql_mode cannot be set (e.g., non-MySQL)
                    }

                    \Illuminate\Support\Facades\DB::table('offerings')->insert([
                        'id' => 0,
                        'company_id' => $companyId,
                        'name' => 'Custom Item',
                        'description' => 'Manual entry item',
                        'type' => 'service',
                        'sellable' => 1,
                        'purchasable' => 0,
                        'price' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }
}
