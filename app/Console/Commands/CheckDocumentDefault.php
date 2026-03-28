<?php

namespace App\Console\Commands;

use App\Models\Setting\DocumentDefault;
use Illuminate\Console\Command;

class CheckDocumentDefault extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:check-document-default';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check document default values including background image and cover PDF URLs';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $defaults = DocumentDefault::with('company')->get();

        if ($defaults->isEmpty()) {
            $this->warn('No document defaults found.');
            return self::FAILURE;
        }

        $headers = ['Company', 'Type', 'BG Image Path', 'BG Image URL', 'Cover PDF Path', 'Cover PDF URL'];
        $data = $defaults->map(function (DocumentDefault $default) {
            return [
                'Company'        => $default->company?->name ?? 'N/A',
                'Type'           => $default->type->value,
                'BG Image Path'  => $default->getRawOriginal('background_image') ?? 'N/A', // Using getRawOriginal to show actual column value
                'BG Image URL'   => $default->background_image_url ?? 'N/A',
                'Cover PDF Path' => $default->getRawOriginal('cover_pdf') ?? 'N/A',    // Using getRawOriginal to show actual column value
                'Cover PDF URL'  => $default->cover_pdf_url ?? 'N/A'
            ];
        });

        $this->table($headers, $data);

        return self::SUCCESS;
    }
}
