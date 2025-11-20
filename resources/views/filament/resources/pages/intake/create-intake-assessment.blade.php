<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="flex justify-end gap-3 mt-6">
            <x-filament::button
                color="gray"
                tag="a"
                href="{{ route('filament.admin.resources.intake-assessments.index') }}"
            >
                Cancel
            </x-filament::button>

            <x-filament::button
                type="submit"
                color="success"
                icon="heroicon-o-check-circle"
                size="lg"
            >
                Complete Intake & Send to Billing
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>