<?php

namespace App\Filament\Company\Resources\Core;

use App\Filament\Company\Resources\Core\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    use \App\Filament\Traits\HasNavigationPermission;

    protected static ?string $model = User::class;

    protected static ?string $modelLabel = 'User';

    protected static ?string $slug = 'core/users';

    protected static ?string $navigationIcon = 'heroicon-o-user';

    protected static bool $isScopedToTenant = false;

    public static function getModelLabel(): string
    {
        return translate('User');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('User Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('password')
                            ->password()
                            ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                            ->dehydrated(fn ($state) => filled($state))
                            ->required(fn (string $context): bool => $context === 'create'),
                        Forms\Components\Toggle::make('advance_mode')
                            ->label('Advanced Mode')
                            ->live()
                            ->afterStateHydrated(function (Forms\Components\Toggle $component, ?\App\Models\User $record, Forms\Set $set) {
                                if (!$record) {
                                    $component->state(false);
                                    return;
                                }

                                $companies = $record->companies;
                                $companyIds = $companies->pluck('id')->toArray();
                                $allCompanyIds = \App\Models\Company::pluck('id')->toArray();
                                $erpSystemCompanyId = (int) config('erp.erp_system_company_id');

                                $isAllCompanies = empty(array_diff($allCompanyIds, $companyIds)) && empty(array_diff($companyIds, $allCompanyIds));
                                $isSystemCompanyOnly = count($companyIds) === 1 && in_array($erpSystemCompanyId, $companyIds);

                                $detectedRole = null;
                                $isAdvanced = true;

                                if ($isAllCompanies) {
                                    $roleNames = [];
                                    foreach ($allCompanyIds as $companyId) {
                                        $roles = $record->getRolesForCompany($companyId)->pluck('name')->toArray();
                                        if (count($roles) === 1) {
                                            $roleNames[] = $roles[0];
                                        } else {
                                            $roleNames[] = null;
                                        }
                                    }
                                    $uniqueRoleNames = array_unique($roleNames);
                                    if (count($uniqueRoleNames) === 1 && !empty($uniqueRoleNames[0]) && in_array(strtolower($uniqueRoleNames[0]), ['admin', 'super admin'])) {
                                        $detectedRole = $uniqueRoleNames[0];
                                        $isAdvanced = false;
                                    }
                                } elseif ($isSystemCompanyOnly) {
                                    $roles = $record->getRolesForCompany($erpSystemCompanyId)->pluck('name')->toArray();
                                    if (count($roles) === 1 && strtolower($roles[0]) === 'sales') {
                                        $detectedRole = $roles[0];
                                        $isAdvanced = false;
                                    }
                                }

                                $component->state($isAdvanced);
                                if (!$isAdvanced && $detectedRole) {
                                    $set('selected_role', $detectedRole);
                                }
                            })
                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                if ($state) {
                                    $roleName = $get('selected_role');
                                    if ($roleName) {
                                        $erpSystemCompanyId = config('erp.erp_system_company_id');
                                        $companiesToSelect = [];

                                        if (in_array(strtolower($roleName), ['admin', 'super admin'])) {
                                            $companiesToSelect = \App\Models\Company::pluck('id')->toArray();
                                        } elseif (strtolower($roleName) === 'sales') {
                                            if ($erpSystemCompanyId) {
                                                $companiesToSelect = [$erpSystemCompanyId];
                                            }
                                        }

                                        $set('companies', array_map('strval', $companiesToSelect));

                                        foreach ($companiesToSelect as $companyId) {
                                            $role = \Spatie\Permission\Models\Role::withoutGlobalScopes()
                                                ->where('company_id', $companyId)
                                                ->where('name', $roleName)
                                                ->first();

                                            if ($role) {
                                                $set("company_roles_{$companyId}", [(string) $role->id]);
                                            }
                                        }
                                    }
                                } else {
                                    $set('companies', []);
                                }
                            }),
                        Forms\Components\Select::make('selected_role')
                            ->label('Role')
                            ->options(function () {
                                return \Spatie\Permission\Models\Role::withoutGlobalScopes()
                                    ->distinct()
                                    ->pluck('name', 'name')
                                    ->toArray();
                            })
                            ->required(fn (Forms\Get $get) => ! $get('advance_mode'))
                            ->hidden(fn (Forms\Get $get) => $get('advance_mode'))
                            ->live()
                            ->dehydrated(false)
                            ->saveRelationshipsUsing(function (\App\Models\User $record, $state, Forms\Get $get) {
                                if ($get('advance_mode')) {
                                    return;
                                }

                                $roleName = $state;
                                if (empty($roleName)) {
                                    return;
                                }

                                $erpSystemCompanyId = config('erp.erp_system_company_id');
                                $companiesToSync = [];
                                $pivotRole = 'user';

                                if (in_array(strtolower($roleName), ['admin', 'super admin'])) {
                                    $companiesToSync = \App\Models\Company::pluck('id')->toArray();
                                    $pivotRole = 'admin';
                                } elseif (strtolower($roleName) === 'sales') {
                                    if ($erpSystemCompanyId) {
                                        $companiesToSync = [$erpSystemCompanyId];
                                    }
                                }

                                // Sync the companies with the pivot role
                                $companiesData = [];
                                foreach ($companiesToSync as $companyId) {
                                    $companiesData[$companyId] = ['role' => $pivotRole];
                                }
                                $record->companies()->sync($companiesData);

                                // Assign the role for each synced company
                                foreach ($companiesToSync as $companyId) {
                                    $sessionCompanyId = getPermissionsTeamId();
                                    setPermissionsTeamId($companyId);

                                    // Find the role in this company with the selected name
                                    $role = \Spatie\Permission\Models\Role::withoutGlobalScopes()
                                        ->where('company_id', $companyId)
                                        ->where('name', $roleName)
                                        ->first();

                                    if ($role) {
                                        $record->syncRoles([$role]);
                                    }

                                    setPermissionsTeamId($sessionCompanyId);
                                }

                                // Update current_company_id if not set, or if it's no longer one of the synced companies
                                if (!$record->current_company_id || !in_array($record->current_company_id, $companiesToSync)) {
                                    $record->current_company_id = !empty($companiesToSync) ? $companiesToSync[0] : null;
                                    $record->saveQuietly();
                                }
                            }),
                        Forms\Components\Placeholder::make('role_indication')
                            ->label('Access Level')
                            ->hidden(fn (Forms\Get $get) => $get('advance_mode') || !$get('selected_role'))
                            ->content(function (Forms\Get $get) {
                                $role = $get('selected_role');
                                if (! $role) {
                                    return '';
                                }

                                $erpSystemCompanyId = config('erp.erp_system_company_id');
                                $erpSystemCompany = \App\Models\Company::find($erpSystemCompanyId);
                                $erpSystemCompanyName = $erpSystemCompany?->name ?? 'System Company';
                                $companyCount = \App\Models\Company::count();

                                if (in_array(strtolower($role), ['admin', 'super admin'])) {
                                    return new \Illuminate\Support\HtmlString('<span class="text-success-600 dark:text-success-400 font-semibold">✓ This role will be applied to all (' . $companyCount . ') companies.</span>');
                                } elseif (strtolower($role) === 'sales') {
                                    return new \Illuminate\Support\HtmlString('<span class="text-primary-600 dark:text-primary-400 font-semibold">✓ This role will be applied to <strong>' . e($erpSystemCompanyName) . '</strong> only.</span>');
                                } else {
                                    return 'This role will be applied.';
                                }
                            }),
                        Forms\Components\CheckboxList::make('companies')
                            ->relationship('companies', 'name')
                            ->searchable()
                            ->live()
                            ->hidden(fn (Forms\Get $get) => ! $get('advance_mode'))
                            ->afterStateUpdated(function ($state, Forms\Set $set, $old) {
                                $newCompanies = array_diff($state ?? [], $old ?? []);
                                foreach ($newCompanies as $companyId) {
                                    $set("company_roles_{$companyId}", []);
                                }
                            })
                            ->saveRelationshipsUsing(function (\App\Models\User $record, $state, Forms\Get $get) {
                                if (! $get('advance_mode')) {
                                    return;
                                }
                                $record->companies()->sync($state ?? []);
                            }),
                        Forms\Components\Group::make()
                            ->schema(
                                fn (Forms\Get $get, ?\App\Models\User $record): array => collect($get('companies') ?? [])
                                    ->map(function ($companyId) use ($record) {
                                        $company = \App\Models\Company::find($companyId);
                                        if (! $company) {
                                            return null;
                                        }

                                        return Forms\Components\CheckboxList::make("company_roles_{$company->id}")
                                            ->label("Roles in {$company->name}")
                                            ->default([])
                                            ->options(\Spatie\Permission\Models\Role::withoutGlobalScopes()->where('company_id', $company->id)->orWhereNull('company_id')->pluck('name', 'id'))
                                            ->afterStateHydrated(function (Forms\Components\CheckboxList $component, ?\App\Models\User $record) use ($company) {
                                                if ($record) {
                                                    $component->state($record->getRolesForCompany($company->id)->pluck('id')->map(fn ($id) => (string) $id)->toArray());
                                                }
                                            })
                                            ->dehydrated(false)
                                            ->saveRelationshipsUsing(function (\App\Models\User $record, $state, Forms\Get $get) use ($company) {
                                                if (! $get('advance_mode')) {
                                                    return;
                                                }

                                                $sessionCompanyId = getPermissionsTeamId();
                                                setPermissionsTeamId($company->id);

                                                $roleIds = is_array($state) ? $state : [];
                                                $roles = \Spatie\Permission\Models\Role::withoutGlobalScopes()
                                                    ->whereIn('id', $roleIds)
                                                    ->get();

                                                $record->syncRoles($roles);

                                                setPermissionsTeamId($sessionCompanyId);
                                            });
                                    })
                                    ->filter()
                                    ->toArray()
                            )
                            ->hidden(fn (Forms\Get $get) => ! $get('advance_mode')),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('roles.name')
                    ->badge()
                    ->separator(','),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                \STS\FilamentImpersonate\Tables\Actions\Impersonate::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
