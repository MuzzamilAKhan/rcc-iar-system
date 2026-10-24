<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Create IAR | RCC-IAR</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>

<body class="min-h-screen bg-slate-100">
@include('partials.regatlas-navigation')

    <main class="mx-auto max-w-5xl p-6">
        <a href="{{ route('dashboard') }}" class="text-blue-700">&larr; Dashboard</a>
        <h1 class="my-6 text-3xl font-bold">Create IAR</h1>
        <livewire:iar.create-draft />
    </main>
    @livewireScripts
</body>

</html>
