<?php

namespace App\Filament\Imports\Common;

use App\Models\Common\Client;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ClientImporter extends Importer
{
    protected static ?string $model = Client::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('name')
                ->label('Client name')
                ->requiredMapping()
                ->example('Acme Corp'),
            ImportColumn::make('nric')
                ->label('NRIC last 4 digit')
                ->example('1234'),
            ImportColumn::make('account_number')
                ->example('ACC-1001'),
            ImportColumn::make('website')
                ->example('https://acme.example.com'),
            ImportColumn::make('currency_code')
                ->label('Currency Code')
                ->example('USD'),
            ImportColumn::make('notes')
                ->example('Key account'),
            ImportColumn::make('first_name')
                ->label('Contact First Name')
                ->example('Jane'),
            ImportColumn::make('last_name')
                ->label('Contact Last Name')
                ->example('Doe'),
            ImportColumn::make('email')
                ->label('Contact Email')
                ->requiredMapping()
                ->example('jane.doe@example.com'),
            ImportColumn::make('phone')
                ->label('Contact Phone')
                ->requiredMapping()
                ->example('5551234567'),
            ImportColumn::make('billing_address_line_1')
                ->label('Billing Address Line 1')
                ->example('123 Main St'),
            ImportColumn::make('billing_address_line_2')
                ->label('Billing Address Line 2'),
            ImportColumn::make('billing_city')
                ->label('Billing City')
                ->example('New York'),
            ImportColumn::make('billing_postal_code')
                ->label('Billing Postal Code')
                ->example('10001'),
            ImportColumn::make('billing_country_code')
                ->label('Billing Country Code')
                ->example('US'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $map
     * @param  array<string, mixed>  $options
     */
    public function import(array $data, array $map, array $options = []): void
    {
        $rules = [
            'name' => ['required', 'max:255'],
            'nric' => ['nullable', 'max:255'],
            'account_number' => ['nullable', 'max:255'],
            'website' => ['nullable', 'max:255'],
            'currency_code' => ['nullable', 'max:10'],
            'notes' => ['nullable'],
            'first_name' => ['nullable', 'max:255'],
            'last_name' => ['nullable', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'max:15'],
            'billing_address_line_1' => ['nullable', 'max:255'],
            'billing_address_line_2' => ['nullable', 'max:255'],
            'billing_city' => ['nullable', 'max:255'],
            'billing_postal_code' => ['nullable', 'max:255'],
            'billing_country_code' => ['nullable', 'size:2'],
        ];

        $validator = Validator::make($data, array_intersect_key($rules, array_filter($map, fn ($excelColumn) => filled($excelColumn))));

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        Client::createWithRelations([
            'type' => 'client',
            'name' => $data['name'],
            'nric' => $data['nric'] ?? null,
            'account_number' => $data['account_number'] ?? null,
            'website' => $data['website'] ?? null,
            'currency_code' => $data['currency_code'] ?? null,
            'notes' => $data['notes'] ?? null,
            'primaryContact' => [
                'first_name' => $data['first_name'] ?? null,
                'last_name' => $data['last_name'] ?? null,
                'email' => $data['email'] ?? null,
                'phones' => filled($data['phone'] ?? null)
                    ? [['type' => 'primary', 'number' => $data['phone']]]
                    : [],
            ],
            'billingAddress' => [
                'address_line_1' => $data['billing_address_line_1'] ?? null,
                'address_line_2' => $data['billing_address_line_2'] ?? null,
                'city' => $data['billing_city'] ?? null,
                'postal_code' => $data['billing_postal_code'] ?? null,
                'country_code' => filled($data['billing_country_code'] ?? null)
                    ? strtoupper($data['billing_country_code'])
                    : null,
            ],
        ]);
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $failedRowsCount = $import->getFailedRowsCount();
        $importedRowsCount = $import->total_rows - $failedRowsCount;

        $body = 'Your client import has completed and ' . number_format($importedRowsCount) . ' ' . str('row')->plural($importedRowsCount) . ' imported.';

        if ($failedRowsCount) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to import.';
        }

        return $body;
    }
}
