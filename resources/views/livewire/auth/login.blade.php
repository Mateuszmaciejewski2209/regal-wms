<div class="w-full max-w-sm">
    <div class="mb-8 flex items-center gap-3">
        <x-logo class="size-10 text-ink [--logo-ink:var(--canvas)]" />
        <div class="leading-tight">
            <div class="text-lg font-semibold tracking-tight">Regał WMS</div>
            <div class="text-[0.8125rem] text-ink-3">Gospodarka magazynowa</div>
        </div>
    </div>

    <form wire:submit="login" class="card space-y-4 p-6" novalidate>
        <div>
            <h1 class="text-base font-semibold">Zaloguj się</h1>
            <p class="mt-0.5 text-[0.8125rem] text-ink-3">Użyj konta nadanego przez administratora.</p>
        </div>

        <x-ui.field label="E-mail" for="email" error="email">
            <input id="email" type="email" wire:model="email" class="control" autocomplete="username" autofocus required
                   spellcheck="false" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
        </x-ui.field>

        <x-ui.field label="Hasło" for="password" error="password">
            <input id="password" type="password" wire:model="password" class="control" autocomplete="current-password" required
                   @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
        </x-ui.field>

        <label class="flex items-center gap-2 text-[0.8125rem] text-ink-2">
            <input type="checkbox" wire:model="remember">
            Zapamiętaj mnie
        </label>

        <x-ui.button type="submit" variant="primary" class="w-full">
            <span wire:loading.remove wire:target="login">Zaloguj</span>
            <span wire:loading wire:target="login">Logowanie…</span>
        </x-ui.button>
    </form>

    @if (app()->environment('local') || config('regal.seed_demo'))
        <div class="mt-4 rounded-xl border border-dashed border-line-strong p-4 text-[0.8125rem] text-ink-3">
            <p class="mb-1 font-medium text-ink-2">Konta demonstracyjne (hasło: <span class="code">haslo1234</span>)</p>
            <ul class="space-y-0.5">
                <li><span class="code">admin@regal.test</span> – administrator</li>
                <li><span class="code">kierownik@regal.test</span> – kierownik</li>
                <li><span class="code">magazynier@regal.test</span> – magazynier</li>
                <li><span class="code">ksiegowosc@regal.test</span> – podgląd (księgowość)</li>
            </ul>
        </div>
    @endif
</div>
