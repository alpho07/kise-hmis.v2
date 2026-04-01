<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Today's Visit Pipeline
        </x-slot>

        <x-slot name="headerEnd">
            <span style="font-size:.75rem;color:var(--kise-muted-text,#6b7280);font-weight:600;">
                {{ $this->getTodayTotal() }} visits today
            </span>
        </x-slot>

        <div class="kise-pipeline">
            @foreach($this->getStages() as $index => $stage)

                @if($index > 0)
                    <svg class="kise-pipeline-arrow" width="14" height="14" viewBox="0 0 14 14" fill="none">
                        <path d="M3 7h8M8 4l3 3-3 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                @endif

                <div class="kise-pipeline-stage {{ $stage['class'] }}">
                    <div class="kise-pipeline-count">{{ $stage['count'] }}</div>
                    <div class="kise-pipeline-label">{{ $stage['label'] }}</div>
                    <div class="kise-pipeline-dot"></div>
                </div>

            @endforeach
        </div>

        {{-- Progress bar --}}
        <div style="margin-top:14px;display:flex;gap:2px;border-radius:4px;overflow:hidden;height:5px;">
            @php
                $colors = [
                    'reception' => '#60a5fa',
                    'triage'    => '#a78bfa',
                    'intake'    => '#34d399',
                    'billing'   => '#FFC105',
                    'payment'   => '#fb923c',
                    'service'   => '#29972E',
                    'completed' => '#6b7280',
                ];
            @endphp
            @foreach($this->getStages() as $stage)
                @if($stage['count'] > 0)
                    <div style="flex:{{ $stage['count'] }};background:{{ $colors[$stage['key']] ?? '#d1d5db' }};min-width:4px;"
                         title="{{ $stage['label'] }}: {{ $stage['count'] }}"></div>
                @endif
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
