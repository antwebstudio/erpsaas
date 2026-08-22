<?php

namespace App\Filament\Company\Clusters\Settings\Pages;

use App\Enums\Setting\EmailAccountType;
use App\Filament\Company\Clusters\Settings;
use App\Models\Setting\EmailAccount as EmailAccountModel;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Filament\Support\Exceptions\Halt;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Locked;

/**
 * @property Form $form
 */
class EmailAccounts extends Page
{
    use InteractsWithFormActions;

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user ? $user->can('page_EmailAccounts') : false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    protected static ?string $title = 'Email Accounts';

    protected static string $view = 'filament.company.pages.setting.email-accounts';

    protected static ?string $cluster = Settings::class;

    /**
     * @var array<string, mixed> | null
     */
    public ?array $data = [];

    #[Locked]
    public ?int $companyId = null;

    #[Locked]
    public ?int $defaultAccountId = null;

    #[Locked]
    public ?int $marketingAccountId = null;

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
        abort_unless(static::canAccess(), 404);

        $this->companyId = Auth::user()->current_company_id;

        $defaultAccount = $this->findAccount(EmailAccountType::DefaultAccount);
        $marketingAccount = $this->findAccount(EmailAccountType::Marketing);

        $this->defaultAccountId = $defaultAccount?->id;
        $this->marketingAccountId = $marketingAccount?->id;

        $this->form->fill([
            'default' => $this->accountToState($defaultAccount),
            'marketing' => $this->accountToState($marketingAccount),
        ]);
    }

    protected function findAccount(EmailAccountType $type): ?EmailAccountModel
    {
        return EmailAccountModel::where('company_id', $this->companyId)
            ->where('type', $type)
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    protected function accountToState(?EmailAccountModel $account): array
    {
        return [
            'mailer' => $account->mailer ?? 'smtp',
            'from_name' => $account->from_name ?? null,
            'from_address' => $account->from_address ?? null,
            'host' => $account->host ?? null,
            'port' => $account->port ?? null,
            'username' => $account->username ?? null,
            'password' => null,
            'encryption' => $account->encryption ?? 'tls',
            'is_active' => $account->is_active ?? true,
        ];
    }

    public function save(): void
    {
        try {
            $state = $this->form->getState();

            $this->persistAccount(EmailAccountType::DefaultAccount, $state['default']);
            $this->persistAccount(EmailAccountType::Marketing, $state['marketing']);
        } catch (Halt) {
            return;
        }

        $this->getSavedNotification()->send();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function persistAccount(EmailAccountType $type, array $data): void
    {
        $account = EmailAccountModel::firstOrNew([
            'company_id' => $this->companyId,
            'type' => $type,
        ]);

        $password = $data['password'] ?? null;
        unset($data['password']);

        $account->fill($data);

        if (filled($password)) {
            $account->password = $password;
        }

        $account->save();
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
                $this->getAccountSection(
                    statePath: 'default',
                    type: EmailAccountType::DefaultAccount,
                ),
                $this->getAccountSection(
                    statePath: 'marketing',
                    type: EmailAccountType::Marketing,
                ),
            ])
            ->statePath('data');
    }

    protected function getAccountSection(string $statePath, EmailAccountType $type): Component
    {
        return Section::make($type->getLabel())
            ->key($statePath)
            ->description($type->description())
            ->statePath($statePath)
            ->schema([
                Toggle::make('is_active')
                    ->label('Enabled')
                    ->helperText('When disabled, this account is treated as unconfigured and email falls back to the default account (or the system mailer).')
                    ->columnSpanFull(),
                Select::make('mailer')
                    ->label('Mailer')
                    ->options([
                        'smtp' => 'SMTP',
                        'sendmail' => 'Sendmail',
                        'ses' => 'Amazon SES',
                        'postmark' => 'Postmark',
                        'resend' => 'Resend',
                        'log' => 'Log (testing only)',
                    ])
                    ->default('smtp')
                    ->live()
                    ->selectablePlaceholder(false)
                    ->required(),
                TextInput::make('from_name')
                    ->label('From Name')
                    ->maxLength(255),
                TextInput::make('from_address')
                    ->label('From Address')
                    ->email()
                    ->required()
                    ->maxLength(255),
                TextInput::make('host')
                    ->label('SMTP Host')
                    ->required(fn (\Filament\Forms\Get $get) => $get('mailer') === 'smtp')
                    ->visible(fn (\Filament\Forms\Get $get) => $get('mailer') === 'smtp')
                    ->maxLength(255),
                TextInput::make('port')
                    ->label('SMTP Port')
                    ->numeric()
                    ->visible(fn (\Filament\Forms\Get $get) => $get('mailer') === 'smtp')
                    ->default(587),
                Select::make('encryption')
                    ->label('Encryption')
                    ->options([
                        'tls' => 'TLS',
                        'ssl' => 'SSL',
                        '' => 'None',
                    ])
                    ->visible(fn (\Filament\Forms\Get $get) => $get('mailer') === 'smtp'),
                TextInput::make('username')
                    ->label('SMTP Username')
                    ->visible(fn (\Filament\Forms\Get $get) => $get('mailer') === 'smtp')
                    ->maxLength(255),
                TextInput::make('password')
                    ->label('SMTP Password')
                    ->password()
                    ->revealable()
                    ->visible(fn (\Filament\Forms\Get $get) => $get('mailer') === 'smtp')
                    ->placeholder('Leave blank to keep the current password')
                    ->maxLength(255),
            ])
            ->columns()
            ->headerActions([
                FormAction::make("sendTest_{$statePath}")
                    ->label('Send Test Email')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('gray')
                    ->form([
                        TextInput::make('email')
                            ->label('Recipient Email')
                            ->email()
                            ->required(),
                    ])
                    ->action(function (array $data) use ($statePath, $type): void {
                        $this->sendTestEmail($statePath, $type, $data['email']);
                    }),
            ]);
    }

    protected function sendTestEmail(string $statePath, EmailAccountType $type, string $email): void
    {
        $state = $this->form->getState()[$statePath] ?? [];

        $accountId = $statePath === 'default' ? $this->defaultAccountId : $this->marketingAccountId;

        $account = EmailAccountModel::make(array_merge($state, [
            'company_id' => $this->companyId,
            'type' => $type,
        ]));

        if (blank($state['password'] ?? null) && $accountId) {
            $account->password = EmailAccountModel::find($accountId)?->password;
        }

        if (! $account->isConfigured()) {
            Notification::make()
                ->title('Cannot send test email')
                ->body('Please fill in the required fields (at least the from address, and SMTP host if using SMTP) before sending a test.')
                ->danger()
                ->send();

            return;
        }

        $mailerName = 'email_account_test_' . uniqid();

        config(["mail.mailers.{$mailerName}" => $account->toMailerConfig()]);

        try {
            Mail::mailer($mailerName)->raw(
                "This is a test email sent from the {$type->getLabel()} in ERPSAAS.",
                function ($message) use ($email, $type): void {
                    $message->to($email)->subject("Test Email – {$type->getLabel()}");
                },
            );

            Notification::make()
                ->title('Test email sent')
                ->body("Sent to {$email}")
                ->success()
                ->send();
        } catch (\Throwable $exception) {
            Notification::make()
                ->title('Failed to send test email')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
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
}
