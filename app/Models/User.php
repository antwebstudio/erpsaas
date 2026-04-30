<?php

namespace App\Models;

use App\Models\Common\Contact;
use App\Models\Core\Department;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Models\Contracts\HasDefaultTenant;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;
use Wallo\FilamentCompanies\HasCompanies;
use Wallo\FilamentCompanies\HasConnectedAccounts;
use Wallo\FilamentCompanies\HasProfilePhoto;
use Wallo\FilamentCompanies\SetsProfilePhotoFromUrl;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasAvatar, HasDefaultTenant, HasTenants
{
    use HasApiTokens;
    use HasCompanies;
    use HasConnectedAccounts;
    use HasFactory;
    use HasProfilePhoto;
    use Notifiable;
    use SetsProfilePhotoFromUrl;    
    use HasRoles {
        roles as traitRoles;
    }

    public function canForCompany($companyId, $permission)
    {
        $registrar = app(\Spatie\Permission\PermissionRegistrar::class);
        $baseKey = config('permission.cache.key', 'spatie.permission.cache');

        $sessionCompanyId = getPermissionsTeamId();
        $sessionCacheKey = $registrar->cacheKey;

        setPermissionsTeamId($companyId);
        $registrar->cacheKey = $baseKey . '.company_' . $companyId;
        $registrar->clearPermissionsCollection();
        $this->unsetRelation('roles')->unsetRelation('permissions');

        $can = $this->can($permission);

        setPermissionsTeamId($sessionCompanyId);
        $registrar->cacheKey = $sessionCacheKey;
        $registrar->clearPermissionsCollection();
        $this->unsetRelation('roles')->unsetRelation('permissions');

        return $can;
    }

    public function assignRolesForCompany($companyId, $roles)
    {
        $sessionCompanyId = getPermissionsTeamId();
        setPermissionsTeamId($companyId);
        
        if ($roles instanceof Model) {
            $rolesToAssign = collect([$roles]);
        } else {
            $rolesToAssign = collect($roles);
        }
        $newRoles = $rolesToAssign->reject(fn ($role) => $this->hasRole($role));

        if ($newRoles->isNotEmpty()) {
            foreach ($newRoles as $role) {
                $this->assignRole($role);
            }
        }

        setPermissionsTeamId($sessionCompanyId);
        return $this;
    }



    public function getRolesForCompany($companyId)
    {
        $sessionCompanyId = getPermissionsTeamId();
        setPermissionsTeamId($companyId);
        $roles = $this->roles()->withoutGlobalScopes()->get();
        setPermissionsTeamId($sessionCompanyId);
        return $roles;
    }

    public function getPermissionsForCompany($companyId)
    {
        $sessionCompanyId = getPermissionsTeamId();
        setPermissionsTeamId($companyId);
        $permissions = $this->getAllPermissions();
        setPermissionsTeamId($sessionCompanyId);
        return $permissions;
    }

    public function roles(): \Illuminate\Database\Eloquent\Relations\MorphToMany
    {
        // Remove CurrentCompanyScope from the Role model query — Spatie's wherePivot on
        // model_has_roles.company_id already scopes roles to the correct team/company.
        // The global scope conflicts with canForCompany() cross-company checks.
        return $this->traitRoles()
            ->withoutGlobalScope(\App\Scopes\CurrentCompanyScope::class);
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name', 'email', 'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
    ];

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'user' && is_demo_environment()) {
            return false;
        }

        return true;
    }

    public function getTenants(Panel $panel): array | Collection
    {
        return $this->allCompanies();
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $this->belongsToCompany($tenant);
    }

    public function getDefaultTenant(Panel $panel): ?Model
    {
        return $this->personalCompany();
    }

    public function getFilamentAvatarUrl(): string
    {
        return $this->profile_photo_url;
    }

    public function contacts(): MorphMany
    {
        return $this->morphMany(Contact::class, 'contactable');
    }

    public function managerOf(): HasMany
    {
        return $this->hasMany(Department::class, 'manager_id');
    }

    public function switchCompany(mixed $company): bool
    {
        if (! $this->belongsToCompany($company)) {
            return false;
        }

        $this->forceFill([
            'current_company_id' => $company->id,
        ])->save();

        $this->setRelation('currentCompany', $company);

        session(['current_company_id' => $company->id]);

        return true;
    }

    public function notifications(): MorphMany
    {
        return $this->morphMany(Notification::class, 'notifiable')->latest();
    }

    public function canImpersonate(): bool
    {
        return $this->hasRole(config('filament-shield.super_admin.name'));
    }

}
