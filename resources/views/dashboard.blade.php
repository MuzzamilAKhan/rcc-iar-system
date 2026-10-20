<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Dashboard | RCC-IAR</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-slate-100 min-h-screen">

    <nav class="bg-white shadow p-5 flex justify-between">
        <h1 class="font-bold text-xl">RCC-IAR System</h1>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="text-red-600 font-semibold">
                Logout
            </button>
        </form>
    </nav>

    <main class="max-w-6xl mx-auto py-12 px-5">

        <h2 class="text-2xl font-bold mb-2">
            Welcome, {{ auth()->user()->name }}
        </h2>

        <p class="text-slate-500 mb-8">
            Regulatory Impact Assessment Dashboard
        </p>


        <div class="grid md:grid-cols-2 gap-6">

            @foreach ([
                    ['Create IAR', 'Create a new impact assessment'],
                    ['Fill IAR', 'Complete assigned assessments'],
                    ['View IARs', 'Search and review assessments'],
                ] as [$title, $description])

                <div class="bg-white p-8 rounded-xl shadow">
                    <h3 class="text-xl font-bold mb-2">
                        {{ $title }}
                    </h3>

                    <p class="text-slate-500">
                        {{ $description }}
                    </p>

                    <p class="text-xs text-amber-600 mt-4">
                        Coming Soon
                    </p>
                </div>

            @endforeach

            @can('manage-master-data')
                <a href="{{ route('admin.master-data') }}"
                    class="block bg-white p-8 rounded-xl shadow hover:shadow-lg transition">

                    <h3 class="text-xl font-bold mb-2">
                        Update Database
                    </h3>

                    <p class="text-slate-500">
                        Manage regulatory master data
                    </p>

                    <p class="text-blue-700 font-semibold mt-4">
                        Open Master Data →
                    </p>
                </a>
            @endcan

        </div>

    </main>

</body>

</html>
