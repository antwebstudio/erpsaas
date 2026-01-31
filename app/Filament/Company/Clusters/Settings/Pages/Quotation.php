<?php

namespace App\Filament\Company\Clusters\Settings\Pages;

use App\Enums\Accounting\AccountCategory;
use App\Enums\Accounting\AccountType;
use App\Events\CompanyDefaultUpdated;
use App\Filament\Company\Clusters\Settings;
use App\Filament\Forms\Components\CreateAccountSelect;
use App\Models\Setting\CompanyDefault as CompanyDefaultModel;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Filament\Support\Exceptions\Halt;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Locked;

use function Filament\authorize;

/**
 * @property Form $form
 */
class Quotation extends Page
{
    use InteractsWithFormActions;

    protected static ?string $title = 'Quotation';

    protected static string $view = 'filament.company.pages.setting.quotation';

    protected static ?string $cluster = Settings::class;

    /**
     * @var array<string, mixed> | null
     */
    public ?array $data = [];

    #[Locked]
    public ?CompanyDefaultModel $record = null;

    public function getTitle(): string | Htmlable
    {
        return translate(static::$title);
    }

    public static function getNavigationLabel(): string
    {
        return translate(static::$title);
    }

    public function getMaxContentWidth(): MaxWidth | string | null
    {
        return MaxWidth::ScreenTwoExtraLarge;
    }

    public function mount(): void
    {
        $this->record = CompanyDefaultModel::firstOrNew([
            'company_id' => auth()->user()->current_company_id,
        ]);

        abort_unless(static::canView($this->record), 404);

        $this->fillForm();
    }

    public function fillForm(): void
    {
        $data = $this->record->attributesToArray();

        $this->form->fill($data);
    }

    public function save(): void
    {
        try {
            $data = $this->form->getState();

            $this->handleRecordUpdate($this->record, $data);

        } catch (Halt $exception) {
            return;
        }

        $this->getSavedNotification()->send();
    }

    protected function getSavedNotification(): Notification
    {
        return Notification::make()
            ->success()
            ->title(__('filament-panels::resources/pages/edit-record.notifications.saved.title'));
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                $this->getGeneralSection(),
            ])
            ->model($this->record)
            ->statePath('data')
            ->operation('edit');
    }

    protected function getGeneralSection(): Component
    {
        return Section::make('General')
            ->schema([
                CreateAccountSelect::make('income_account_id')
                    ->label('Default Income Account')
                    ->category(AccountCategory::Revenue)
                    ->type(AccountType::OperatingRevenue)
                    ->required()
                    ->helperText('This account will be used when creating Job Scope Options.'),
            ])->columns();
    }

    protected function handleRecordUpdate(CompanyDefaultModel $record, array $data): CompanyDefaultModel
    {
        CompanyDefaultUpdated::dispatch($record, $data);

        $record->update($data);

        return $record;
    }

    /**
     * @return array<Action | ActionGroup>
     */
    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction(),
        ];
    }

    protected function getSaveFormAction(): Action
    {
        return Action::make('save')
            ->label(__('filament-panels::resources/pages/edit-record.form.actions.save.label'))
            ->submit('save')
            ->keyBindings(['mod+s']);
    }

    public static function canView(Model $record): bool
    {
        try {
            return authorize('update', $record)->allowed();
        } catch (AuthorizationException $exception) {
            return $exception->toResponse()->allowed();
        }
    }
}
