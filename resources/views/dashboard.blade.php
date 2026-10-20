
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1">

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
                ['Update Database', 'Manage regulatory master data']
            ] as [$title, $description])

                <div class="bg-white p-8 rounded-xl shadow">
                    <h3 class="text-xl font-bold mb-2">
                        {{ $title }}
                    </h3>
                    <p class="text-slate-500">
                        {{ $description }}
                    </p>
                </div>

            @endforeach

        </div>
    </main>

</body>
</html>
