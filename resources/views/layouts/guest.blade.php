<!DOCTYPE html>
<html lang="pl" data-theme="light">
<head>
    @include('partials.head')
</head>
<body class="min-h-dvh">
    <main class="grid min-h-dvh place-items-center px-4 py-10">
        {{ $slot }}
    </main>
    <x-ui.toasts />
</body>
</html>
