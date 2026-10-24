<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>Sign in | RegAtlas</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#f5f7fb] text-slate-900 antialiased ra-login">
<main class="min-h-screen lg:grid lg:grid-cols-[minmax(0,1.05fr)_minmax(440px,.95fr)]">
    <section class="relative hidden overflow-hidden bg-[#102b46] p-10 text-white lg:flex lg:flex-col lg:justify-between xl:p-16" aria-label="RegAtlas platform introduction">
        <div class="pointer-events-none absolute -right-36 -top-36 h-[530px] w-[530px] rounded-full border border-white/10"></div>
        <div class="pointer-events-none absolute -right-20 -top-16 h-[360px] w-[360px] rounded-full border border-white/10"></div>
        <div class="pointer-events-none absolute bottom-[-160px] left-[-120px] h-[480px] w-[480px] rounded-full bg-[#1c546a]/40 blur-3xl"></div>
        <div class="relative flex items-center gap-3">
            <div class="flex h-11 w-11 items-center justify-center rounded-xl border border-white/20 bg-white/10 text-xl font-bold tracking-tight">R</div>
            <div><div class="text-xl font-semibold tracking-tight">RegAtlas</div><div class="text-xs tracking-[.14em] text-slate-300 uppercase">Regulatory Intelligence Workspace</div></div>
        </div>
        <div class="relative max-w-xl py-20">
            <div class="mb-7 inline-flex items-center gap-2 rounded-full border border-teal-300/25 bg-teal-200/10 px-4 py-2 text-xs font-semibold tracking-wide text-teal-100"><span class="h-2 w-2 rounded-full bg-teal-300"></span> Regulatory operations, connected</div>
            <h1 class="text-5xl font-semibold leading-[1.12] tracking-tight xl:text-6xl">Clarity in every <span class="text-[#83dbd0]">regulatory decision.</span></h1>
            <p class="mt-7 max-w-lg text-lg leading-8 text-slate-300">One secure workspace for impact assessments, country registrations, controlled approvals and traceable change history.</p>
            <div class="mt-12 grid max-w-lg grid-cols-2 gap-4">
                <div class="rounded-2xl border border-white/15 bg-white/5 p-5"><div class="mb-3 text-2xl text-teal-200">◎</div><div class="font-semibold">Global visibility</div><p class="mt-2 text-sm leading-6 text-slate-300">Country-level regulatory data in context.</p></div>
                <div class="rounded-2xl border border-white/15 bg-white/5 p-5"><div class="mb-3 text-2xl text-teal-200">✓</div><div class="font-semibold">Controlled workflows</div><p class="mt-2 text-sm leading-6 text-slate-300">Traceable reviews and approval decisions.</p></div>
            </div>
        </div>
        <div class="relative border-t border-white/15 pt-5 text-xs text-slate-400">RegAtlas · Regulatory Impact Assessment &amp; Product Registry</div>
    </section>
    <section class="flex min-h-screen items-center justify-center px-5 py-12 sm:px-10 lg:px-14">
        <div class="w-full max-w-[430px]">
            <div class="mb-10 flex items-center gap-3 lg:hidden"><div class="flex h-11 w-11 items-center justify-center rounded-xl bg-[#102b46] text-lg font-bold text-white">R</div><div><div class="text-xl font-semibold">RegAtlas</div><div class="text-xs text-slate-500">Regulatory Intelligence Workspace</div></div></div>
            <div class="mb-9"><p class="mb-3 text-xs font-bold uppercase tracking-[.18em] text-teal-700">Welcome back</p><h2 class="text-3xl font-semibold tracking-tight sm:text-4xl">Sign in to RegAtlas</h2><p class="mt-3 text-sm leading-6 text-slate-500">Enter your authorized work account to continue.</p></div>
            @if (session('status'))
                <div role="status" class="mb-5 rounded-xl border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-900">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div role="alert" class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
            @endif
            <form method="POST" action="{{ route('login.store') }}" class="space-y-5">
                @csrf
                <div><label for="email" class="mb-2 block text-sm font-semibold text-slate-700">Work email address</label><input id="email" name="email" type="email" autocomplete="username" inputmode="email" value="{{ old('email') }}" required autofocus placeholder="name@organization.com" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3.5 text-sm text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-teal-700 focus:ring-4 focus:ring-teal-700/10" aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"></div>
                <div><label for="password" class="mb-2 block text-sm font-semibold text-slate-700">Password</label><input id="password" name="password" type="password" autocomplete="current-password" required placeholder="Enter your password" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3.5 text-sm text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-teal-700 focus:ring-4 focus:ring-teal-700/10"></div>
                <label class="inline-flex cursor-pointer items-center gap-2.5 text-sm text-slate-600"><input type="checkbox" name="remember" value="1" class="h-4 w-4 rounded border-slate-300 accent-teal-800">Remember me</label>
                <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-xl bg-[#102b46] px-5 py-3.5 text-sm font-semibold text-white shadow-lg shadow-slate-900/10 transition hover:bg-[#174a63] focus-visible:outline focus-visible:outline-4 focus-visible:outline-offset-2 focus-visible:outline-teal-600">Sign in securely <span aria-hidden="true">→</span></button>
            </form>
            <div class="mt-9 border-t border-slate-200 pt-6"><p class="text-xs leading-6 text-slate-500">Access is restricted to authorized users. Activity within this workspace may be recorded for audit and security purposes.</p><p class="mt-6 text-xs text-slate-400">© {{ date('Y') }} RegAtlas. All rights reserved.</p></div>
        </div>
    </section>
</main>
</body>
</html>
