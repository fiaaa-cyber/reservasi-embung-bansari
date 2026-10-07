<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title')</title>
    @vite(['resources/css/app.css'])
</head>
<body class="antialiased text-gray-800 bg-white">

    @include('components.navbar')

    <main class="min-h-screen">
        @yield('content')
    </main>

    @include('components.footer')

</body>
</html>
