<div class="p-4" wire:poll.1s="checkStatus" @open-url.window="window.open($event.detail.url, '_blank')">
    @if($status === 'pending')
        <div class="flex flex-col items-center justify-center space-y-4">
            <x-filament::loading-indicator class="h-8 w-8 text-primary-500" />
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Generating PDF... Please wait, this may take a few moments.
            </p>
        </div>
    @elseif($status === 'completed')
        <div class="flex flex-col items-center justify-center space-y-4">
            <x-filament::icon
                icon="heroicon-m-check-circle"
                class="h-8 w-8 text-success-500"
            />
            <p class="text-sm font-medium text-success-600 dark:text-success-400">
                PDF generated successfully!
            </p>
            <p class="text-sm text-gray-500 dark:text-gray-400 text-center">
                Your download should start automatically. <br>
                If it doesn't, <a href="{{ $downloadUrl }}" target="_blank" class="text-primary-600 underline text-sm font-medium">click here to download</a>.
            </p>
        </div>
    @elseif($status === 'failed')
        <div class="flex flex-col items-center justify-center space-y-4">
            <x-filament::icon
                icon="heroicon-m-x-circle"
                class="h-8 w-8 text-danger-500"
            />
            <p class="text-sm font-medium text-danger-600 dark:text-danger-400">
                Failed to generate PDF.
            </p>
            <p class="text-sm text-gray-500 dark:text-gray-400 text-center">
                {{ $errorMessage }}
            </p>
        </div>
    @endif
</div>
