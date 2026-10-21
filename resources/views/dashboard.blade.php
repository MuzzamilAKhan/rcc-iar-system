<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard | RCC-IAR</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-slate-100">
    <nav class="flex justify-between bg-white p-5 shadow">
        <h1 class="text-xl font-bold">RCC-IAR System</h1>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="font-semibold text-red-600">Logout</button>
        </form>
    </nav>
    <main class="mx-auto max-w-6xl px-5 py-12">
        <h2 class="mb-2 text-2xl font-bold">Welcome, {{ auth()->user()->name }}</h2>
        <p class="mb-8 text-slate-500">Regulatory Impact Assessment Dashboard</p>
        <div class="grid gap-6 md:grid-cols-2">
            @can('iar.create')
                <a href="{{ route('iar.create') }}" class="block rounded-xl bg-white p-8 shadow transition hover:shadow-lg">
                    <h3 class="mb-2 text-xl font-bold">Create IAR</h3>
                    <p class="text-slate-500">Create a new regulatory impact assessment draft</p>
                    <p class="mt-4 font-semibold text-blue-700">Create Draft &rarr;</p>
                </a>
            @endcan
            @foreach ([['Fill IAR', 'Complete assigned assessments'], ['View IARs', 'Search and review assessments']] as [$title, $description])
                <div class="rounded-xl bg-white p-8 shadow">
                    <h3 class="mb-2 text-xl font-bold">{{ $title }}</h3>
                    <p class="text-slate-500">{{ $description }}</p>
                    <p class="mt-4 text-xs text-amber-600">Coming Soon</p>
                </div>
            @endforeach
            @can('manage-master-data')
                <a href="{{ route('admin.master-data') }}"
                    class="block rounded-xl bg-white p-8 shadow transition hover:shadow-lg">
                    <h3 class="mb-2 text-xl font-bold">Update Database</h3>
                    <p class="text-slate-500">Manage regulatory master data</p>
                    <p class="mt-4 font-semibold text-blue-700">Open Master Data &rarr;</p>
                </a>
            @endcan
        </div>
    </main>
</body>

</html>
