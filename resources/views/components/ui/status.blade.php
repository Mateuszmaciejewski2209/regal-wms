@props(['status'])

<x-ui.badge :tone="$status->tone()" {{ $attributes }}>{{ $status->label() }}</x-ui.badge>
