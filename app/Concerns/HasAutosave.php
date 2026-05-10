<?php

namespace App\Concerns;

use Filament\Actions\Action;
use Filament\Forms;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

trait HasAutosave
{
    public bool $autosaveEnabled = false;

    public int $autosaveInterval = 30;

    protected function initAutosave(): void
    {
        $this->autosaveEnabled = (bool) config('erp.autosave_default', false);
        $this->autosaveInterval = (int) config('erp.autosave_interval', 30);
    }

    public function toggleAutosave(): void
    {
        $this->autosaveEnabled = ! $this->autosaveEnabled;
        $this->dispatch('autosave-toggled', enabled: $this->autosaveEnabled, interval: $this->autosaveInterval);
    }

    public function performAutosave(): void
    {
        try {
            $this->save(shouldRedirect: false, shouldSendSavedNotification: false);

            // After save, newly added items have real DB IDs but $this->data still holds
            // null IDs for them. On the next autosave, deleteRemovedLineItemGroups would
            // see those DB IDs as "missing from form" and delete them. Refilling the form
            // from DB syncs the real IDs back into form state, preventing that.
            $this->record->refresh();
            $this->fillForm();
        } catch (ValidationException) {
            // Silently skip autosave when form has validation errors
        } catch (\Throwable) {
            // Silently skip on other errors to avoid disrupting the user
        }
    }

    public function form(Forms\Form $form): Forms\Form
    {
        $form = parent::form($form);

        $initJs = 'if ($wire.autosaveEnabled) { window._autosaveTimer = setInterval(function() { $wire.performAutosave() }, $wire.autosaveInterval * 1000); }';
        $toggleJs = 'clearInterval(window._autosaveTimer); if ($event.detail.enabled) { window._autosaveTimer = setInterval(function() { $wire.performAutosave() }, $event.detail.interval * 1000); }';
        $html = '<div x-data="{}" x-init="' . $initJs . '" x-on:autosave-toggled.window="' . $toggleJs . '" style="display:none"></div>';

        $bridge = Forms\Components\Placeholder::make('_autosave_bridge')
            ->hiddenLabel()
            ->content(new HtmlString($html));

        return $form->schema([
            ...$form->getComponents(withHidden: true),
            $bridge,
        ]);
    }

    protected function getAutosaveToggleFormAction(): Action
    {
        return Action::make('autosaveToggle')
            ->label(fn () => $this->autosaveEnabled ? 'Autosave: On' : 'Autosave')
            ->icon(fn () => $this->autosaveEnabled ? 'heroicon-o-check-circle' : 'heroicon-o-clock')
            ->color(fn () => $this->autosaveEnabled ? 'success' : 'gray')
            ->tooltip(fn () => ($this->autosaveEnabled ? 'Disable autosave' : 'Enable autosave') . ' (every ' . $this->autosaveInterval . 's)')
            ->action(fn () => $this->toggleAutosave());
    }
}
