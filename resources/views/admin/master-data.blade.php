<!DOCTYPE html>
<html lang="en">

<head>
    
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="alternate icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Master Data | RCC-IAR</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @livewireStyles
</head>

<body>
@include('partials.regatlas-navigation')

    <livewire:admin.master-data-dashboard />

    @livewireScripts
</body>

</html>
