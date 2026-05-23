<?php

namespace App\Filament\Tables\Actions;

use App\Jobs\SendTemplateEmailJob;
use App\Models\Common\Client;
use App\Models\Mail\MailTemplate;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\BulkAction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class BulkSendEmailAction extends BulkAction
{
    public static function getDefaultName(): ?string
    {
        return 'bulk_send_email';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Send Email')
            ->icon('heroicon-o-envelope')
            ->color('info')
            ->modalHeading('Send Email to Selected')
            ->modalDescription('Choose a mail template to send to all selected records with a primary contact email.')
            ->form([
                Forms\Components\Select::make('template_id')
                    ->label('Mail Template')
                    ->options(function () {
                        $companyId = Auth::user()->currentCompany?->id
                            ?? session('current_company_id');

                        if (! $companyId) {
                            return [];
                        }

                        return MailTemplate::where('tenant_id', $companyId)
                            ->where('is_active', true)
                            ->orderBy('name')
                            ->pluck('name', 'id');
                    })
                    ->required()
                    ->searchable()
                    ->noSearchResultsMessage('No active mail templates found for this company.'),
            ])
            ->action(function (Collection $records, array $data): void {
                $template = MailTemplate::find($data['template_id']);

                if (! $template) {
                    Notification::make()
                        ->title('Template not found')
                        ->danger()
                        ->send();

                    return;
                }

                $queued = 0;
                $skipped = 0;

                foreach ($records as $record) {
                    /** @var Client $record */
                    $contact = $record->primaryContact;

                    if (! $contact?->email) {
                        $skipped++;

                        continue;
                    }

                    SendTemplateEmailJob::dispatch(
                        $contact->email,
                        $contact->full_name,
                        $template->id,
                        [
                            'name' => $record->name,
                            'first_name' => $contact->first_name,
                            'last_name' => $contact->last_name,
                            'email' => $contact->email,
                            'client_id' => $record->id,
                        ],
                    );

                    $queued++;
                }

                $body = "Queued {$queued} email(s).";
                if ($skipped > 0) {
                    $body .= " Skipped {$skipped} record(s) with no email.";
                }

                Notification::make()
                    ->title('Emails queued successfully')
                    ->body($body)
                    ->success()
                    ->send();
            });
    }
}
