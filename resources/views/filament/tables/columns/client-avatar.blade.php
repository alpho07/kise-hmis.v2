@php
$initial = strtoupper(substr($getRecord()->client->full_name ?? '?', 0, 1));
@endphp

<div class="flex items-center gap-2">
    <div class="w-10 h-10 rounded-full bg-gradient-to-tr from-primary-600 to-primary-800 text-white font-bold flex items-center justify-center shadow">
        {{ $initial }}
    </div>
</div>
