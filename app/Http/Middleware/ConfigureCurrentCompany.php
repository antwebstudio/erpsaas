<?php

namespace App\Http\Middleware;

use App\Events\CompanyConfigured;
use App\Models\Company;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ConfigureCurrentCompany
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Company $company */
        $company = Filament::getTenant();

        if ($company) {
            // Update session and registrar with the new company ID
            session(['current_company_id' => $company->id]);
            
            $registrar = app(\Spatie\Permission\PermissionRegistrar::class);
            
            // Set the team ID for Spatie
            $registrar->setPermissionsTeamId($company->id);
            
            // Use a unique cache key per company to prevent permission poisoning
            // between companies while still benefiting from caching.
            $baseKey = config('permission.cache.key', 'spatie.permission.cache');
            $registrar->cacheKey = $baseKey . '.company_' . $company->id;
            
            // Clear Spatie's internal in-memory collection for this request
            $registrar->clearPermissionsCollection();

            if (Filament::auth()->check()) {
                /** @var \App\Models\User $user */
                $user = Filament::auth()->user();
                
                // Clear cached roles and permissions relations on the user model object
                $user->unsetRelation('roles')->unsetRelation('permissions');
            }

            CompanyConfigured::dispatch($company);
        } else {
            // If no tenant is present, ensure Spatie doesn't use a stale team ID
            app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId(null);
        }

        return $next($request);
    }
}
