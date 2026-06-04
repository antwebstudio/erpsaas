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
                        Forms\Components\CheckboxList::make('companies')
                            ->relationship('companies', 'name')
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set, $old) {
                                $newCompanies = array_diff($state ?? [], $old ?? []);
                                foreach ($newCompanies as $companyId) {
                                    $set("company_roles_{$companyId}", []);
                                }
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
                                            ->saveRelationshipsUsing(function (\App\Models\User $record, $state) use ($company) {
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
                            ),
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
