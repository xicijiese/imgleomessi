<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        <div>
            <x-filament::button type="submit">
                保存设置
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>