<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="color-scheme" content="light dark">
<title>{{ isset($title) ? $title.' · Regał WMS' : 'Regał WMS' }}</title>
<link rel="icon" href="{{ asset('icons/favicon.svg') }}" type="image/svg+xml">

{{-- Motyw ustawiany przed pierwszym malowaniem – brak „mrugnięcia” jasnego tła. --}}
<script>
    (() => {
        const stored = localStorage.getItem('theme') || 'system';
        const dark = stored === 'dark' || (stored === 'system' && matchMedia('(prefers-color-scheme: dark)').matches);
        document.documentElement.dataset.theme = dark ? 'dark' : 'light';
    })();
</script>

@fonts
@vite(['resources/css/app.css', 'resources/js/app.js'])
