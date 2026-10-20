
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1">

    <title>Login | RCC-IAR</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-slate-100 flex items-center justify-center">

    <div class="w-full max-w-md bg-white rounded-xl shadow-lg p-8">

        <h1 class="text-2xl font-bold text-center">
            RCC-IAR System
        </h1>

        <p class="text-slate-500 text-center mt-2 mb-8">
            Regulatory Change Control
        </p>

        @if ($errors->any())
            <div class="mb-4 text-red-600">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('login.store') }}">
            @csrf

            <label class="block mb-2">Email Address</label>
            <input
                type="email"
                name="email"
                required
                autofocus
                class="w-full border rounded-lg p-3 mb-4"
            >

            <label class="block mb-2">Password</label>
            <input
                type="password"
                name="password"
                required
                class="w-full border rounded-lg p-3 mb-4"
            >

            <label class="flex items-center gap-2 mb-5">
                <input type="checkbox" name="remember">
                <span>Remember me</span>
            </label>

            <button type="submit"
                class="w-full bg-blue-700 text-white p-3 rounded-lg hover:bg-blue-800">
                Sign In
            </button>
        </form>
    </div>

</body>
</html>
