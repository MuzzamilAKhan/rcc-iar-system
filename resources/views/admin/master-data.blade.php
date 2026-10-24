<!DOCTYPE html>
<html lang="en">

<head>
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
