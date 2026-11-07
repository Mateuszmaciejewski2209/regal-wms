{{-- Znacznik magazynu w jego kolorze: kropka + kod, opcjonalnie nazwa i ilość. --}}
@props(['warehouse', 'qty' => null, 'name' => false])

<span {{ $attributes->class(['wh-badge', 'wh-'.$warehouse->colorKey()]) }} title="{{ $warehouse->code }} · {{ $warehouse->name }}">
    <span class="wh-dot" aria-hidden="true"></span>
    <span class="wh-code">{{ $warehouse->code }}</span>
    @if ($name)<span class="wh-name">{{ $warehouse->name }}</span>@endif
    @if ($qty !== null)<span class="tabular-nums text-ink-2">{{ $qty }}</span>@endif
</span>
