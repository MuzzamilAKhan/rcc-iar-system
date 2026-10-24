<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Products | RCC-IAR</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>

<body>
@include('partials.regatlas-navigation')

    <livewire:admin.products-index />
    @livewireScripts
</body>

</html>
