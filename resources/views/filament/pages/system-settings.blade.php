<x-filament-panels::page>
    <form wire:submit="save" class="admin-system-settings">
        {{ $this->form }}

        <div class="admin-settings-actions">
            <x-filament::button type="submit">
                保存设置
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
