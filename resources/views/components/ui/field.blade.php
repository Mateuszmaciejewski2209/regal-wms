@props(['label' => null, 'for' => null, 'error' => null, 'hint' => null, 'required' => false])

{{-- Pole formularza: etykieta (gwiazdka = wymagane), treść, błąd pod polem, podpowiedź. --}}
<div {{ $attributes }}>
    @if ($label)
        <label @if ($for) for="{{ $for }}" @endif class="label truncate">
            {{ $label }}@if ($required)<span class="text-danger" aria-hidden="true"> *</span><span class="sr-only"> (wymagane)</span>@endif
        </label>
    @endif

    {{ $slot }}

    @if ($error)
        @error($error)
            <p class="mt-1.5 text-[0.8125rem] text-danger" @if ($for) id="{{ $for }}-error" @endif role="alert">{{ $message }}</p>
        @enderror
    @endif

    @if ($hint)
        <p class="mt-1.5 text-[0.8125rem] text-ink-4">{{ $hint }}</p>
    @endif
</div>
