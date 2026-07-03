<?php

namespace App\Filament\Actions;

use Filament\Actions\ImportAction;
use Filament\Actions\Imports\Events\ImportCompleted;
use Filament\Actions\Imports\Events\ImportStarted;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\ImportAction as ImportTableAction;
use HayderHatem\FilamentExcelImport\Actions\FullImportAction;
use HayderHatem\FilamentExcelImport\Models\Import;
use Illuminate\Bus\PendingBatch;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * A copy of HayderHatem\FilamentExcelImport\Actions\FullImportAction's import
 * pipeline, minus the "download failed rows" notification action, since that
 * package exposes no hook to remove it from the completion notification.
 */
class ExcelImportAction extends FullImportAction
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->action(function (ImportAction | ImportTableAction $action, array $data) {
            /** @var TemporaryUploadedFile $excelFile */
            $excelFile = $data['file'];

            if (! $excelFile instanceof TemporaryUploadedFile) {
                Notification::make()
                    ->title(__('filament-excel-import::import.invalid_file'))
                    ->body(__('filament-excel-import::import.please_upload_valid_excel'))
                    ->danger()
                    ->send();

                return;
            }

            $activeSheetIndex = $data['activeSheet'] ?? $action->getActiveSheet() ?? 0;
            $additionalFormData = $this->extractAdditionalFormData($data);

            $totalRows = $this->getExcelRowCount($excelFile, $activeSheetIndex, $action->getHeaderOffset() ?? 0);

            $maxRows = $action->getMaxRows() ?? $totalRows;
            if ($maxRows < $totalRows) {
                Notification::make()
                    ->title(__('filament-actions::import.notifications.max_rows.title'))
                    ->body(trans_choice('filament-actions::import.notifications.max_rows.body', $maxRows, [
                        'count' => \Illuminate\Support\Number::format($maxRows),
                    ]))
                    ->danger()
                    ->send();

                return;
            }

            $user = Auth::check() ? Auth::user() : null;

            $permanentFilePath = $this->storePermanentFile($excelFile);

            $import = app(Import::class);
            if ($user) {
                $import->user()->associate($user);
            }
            $import->file_name = $excelFile->getClientOriginalName();
            $import->file_path = $permanentFilePath;
            $import->importer = $action->getImporter();
            $import->total_rows = $totalRows;
            $import->save();

            $importId = $import->id;

            $options = array_merge(
                $action->getOptions(),
                Arr::except($data, ['file', 'columnMap']),
                [
                    'additional_form_data' => $additionalFormData,
                    'activeSheet' => $activeSheetIndex,
                    'headerOffset' => $action->getHeaderOffset() ?? 0,
                ]
            );

            $import->unsetRelation('user');

            $columnMap = $data['columnMap'];

            $useStreaming = $this->shouldUseStreaming($excelFile);

            if ($useStreaming) {
                $chunkSize = $action->getChunkSize();
                $headerOffset = $action->getHeaderOffset() ?? 0;
                $startDataRow = $headerOffset + 2;
                $endDataRow = $headerOffset + 1 + $totalRows;

                $importChunks = collect();
                for ($currentRow = $startDataRow; $currentRow <= $endDataRow; $currentRow += $chunkSize) {
                    $endChunkRow = min($currentRow + $chunkSize - 1, $endDataRow);

                    $importChunks->push(app($action->getJob(), [
                        'importId' => $importId,
                        'rows' => null,
                        'startRow' => $currentRow,
                        'endRow' => $endChunkRow,
                        'columnMap' => $columnMap,
                        'options' => $options,
                    ]));
                }
            } else {
                try {
                    $spreadsheet = $this->getUploadedFileSpreadsheet($excelFile);
                    if (! $spreadsheet) {
                        Notification::make()
                            ->title(__('filament-excel-import::import.error_reading_file'))
                            ->body(__('filament-excel-import::import.unable_to_read_uploaded_file'))
                            ->danger()
                            ->send();

                        return;
                    }

                    $worksheet = $spreadsheet->getSheet((int) $activeSheetIndex);
                    $headerOffset = $action->getHeaderOffset() ?? 0;
                    $highestRow = $worksheet->getHighestDataRow();
                    $highestColumn = $worksheet->getHighestDataColumn();

                    $headers = [];
                    $headerRowNumber = $headerOffset + 1;
                    foreach ($worksheet->getRowIterator($headerRowNumber, $headerRowNumber) as $row) {
                        $cellIterator = $row->getCellIterator('A', $highestColumn);
                        $cellIterator->setIterateOnlyExistingCells(false);
                        foreach ($cellIterator as $cell) {
                            $headers[] = $cell->getValue();
                        }
                    }

                    $rows = [];
                    for ($rowIndex = $headerRowNumber + 1; $rowIndex <= $highestRow; $rowIndex++) {
                        $rowData = [];
                        $hasData = false;
                        foreach ($worksheet->getRowIterator($rowIndex, $rowIndex) as $row) {
                            $cellIterator = $row->getCellIterator('A', $highestColumn);
                            $cellIterator->setIterateOnlyExistingCells(false);
                            $columnIndex = 0;
                            foreach ($cellIterator as $cell) {
                                $value = $cell->getValue();
                                if ($value !== null) {
                                    $hasData = true;
                                }
                                $rowData[$headers[$columnIndex] ?? $columnIndex] = $value;
                                $columnIndex++;
                            }
                        }
                        if ($hasData) {
                            $rows[] = $rowData;
                        }
                    }

                    $importChunks = collect($rows)->chunk($action->getChunkSize())
                        ->map(fn ($chunk) => app($action->getJob(), [
                            'importId' => $importId,
                            'rows' => base64_encode(serialize($chunk->all())),
                            'startRow' => null,
                            'endRow' => null,
                            'columnMap' => $columnMap,
                            'options' => $options,
                        ]));
                } catch (\Exception $e) {
                    Log::warning('Traditional import failed, falling back to streaming', [
                        'error' => $e->getMessage(),
                    ]);

                    Notification::make()
                        ->title(__('filament-excel-import::import.switching_to_streaming_mode'))
                        ->body(__('filament-excel-import::import.file_too_large_streaming'))
                        ->info()
                        ->send();

                    $chunkSize = $action->getChunkSize();
                    $headerOffset = $action->getHeaderOffset() ?? 0;
                    $startDataRow = $headerOffset + 2;
                    $endDataRow = $headerOffset + 1 + $totalRows;

                    $importChunks = collect();
                    for ($currentRow = $startDataRow; $currentRow <= $endDataRow; $currentRow += $chunkSize) {
                        $endChunkRow = min($currentRow + $chunkSize - 1, $endDataRow);

                        $importChunks->push(app($action->getJob(), [
                            'importId' => $importId,
                            'rows' => null,
                            'startRow' => $currentRow,
                            'endRow' => $endChunkRow,
                            'columnMap' => $columnMap,
                            'options' => $options,
                        ]));
                    }
                }
            }

            $importer = $import->getImporter(columnMap: $columnMap, options: $options);

            event(new ImportStarted($import, $columnMap, $options));

            Bus::batch($importChunks->all())
                ->allowFailures()
                ->when(
                    filled($jobQueue = $importer->getJobQueue()),
                    fn (PendingBatch $batch) => $batch->onQueue($jobQueue),
                )
                ->when(
                    filled($jobConnection = $importer->getJobConnection()),
                    fn (PendingBatch $batch) => $batch->onConnection($jobConnection),
                )
                ->when(
                    filled($jobBatchName = $importer->getJobBatchName()),
                    fn (PendingBatch $batch) => $batch->name($jobBatchName),
                )
                ->finally(function () use ($importId, $columnMap, $options, $jobConnection, $permanentFilePath) {
                    $import = Import::query()->find($importId);

                    if (! $import) {
                        return;
                    }

                    $import->touch('completed_at');

                    try {
                        if (! str_starts_with($permanentFilePath, 's3://') && file_exists($permanentFilePath)) {
                            @unlink($permanentFilePath);
                        }
                    } catch (\Throwable $e) {
                        Log::warning('Failed to cleanup temporary file', [
                            'path' => $permanentFilePath,
                            'error' => $e->getMessage(),
                        ]);
                    }

                    event(new ImportCompleted($import, $columnMap, $options));

                    $user = $import->user;
                    if (! $user instanceof Authenticatable) {
                        return;
                    }

                    $failedRowsCount = $import->getFailedRowsCount();

                    Notification::make()
                        ->title($import->importer::getCompletedNotificationTitle($import))
                        ->body($import->importer::getCompletedNotificationBody($import))
                        ->when(
                            ! $failedRowsCount,
                            fn (Notification $notification) => $notification->success(),
                        )
                        ->when(
                            $failedRowsCount && ($failedRowsCount < $import->total_rows),
                            fn (Notification $notification) => $notification->warning(),
                        )
                        ->when(
                            $failedRowsCount === $import->total_rows,
                            fn (Notification $notification) => $notification->danger(),
                        )
                        ->when(
                            ($jobConnection === 'sync') ||
                                (blank($jobConnection) && (config('queue.default') === 'sync')),
                            fn (Notification $notification) => $notification
                                ->persistent()
                                ->send(),
                            fn (Notification $notification) => $notification->sendToDatabase($import->user, isEventDispatched: true),
                        );
                })
                ->dispatch();

            if (
                (filled($jobConnection) && ($jobConnection !== 'sync')) ||
                (blank($jobConnection) && (config('queue.default') !== 'sync'))
            ) {
                Notification::make()
                    ->title($action->getSuccessNotificationTitle())
                    ->body(trans_choice('filament-actions::import.notifications.started.body', $import->total_rows, [
                        'count' => \Illuminate\Support\Number::format($import->total_rows),
                    ]))
                    ->success()
                    ->send();
            }
        });
    }
}
