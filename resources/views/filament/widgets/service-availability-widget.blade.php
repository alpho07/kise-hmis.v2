<x-filament-widgets::widget>
    <x-filament::section heading="Today's Service Availability">
        <div class="flex flex-wrap gap-2">
            @foreach($statuses as $status)
                <span class="inline-flex items-center gap-1 rounded-full px-3 py-1 text-sm font-medium
                    {{ $status['available'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                    @if($status['available'])
                        <x-heroicon-m-check-circle class="w-4 h-4"/>
                    @else
                        <x-heroicon-m-x-circle class="w-4 h-4"/>
                    @endif
                    {{ $status['department']->name }}
                    @if(!$status['available'] && $status['reason'])
                        — {{ ucwords(str_replace('_', ' ', $status['reason'])) }}
                    @endif
                </span>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
