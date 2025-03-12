<!doctype html>
<html lang="nl">
<head>
    <!--------------
       Google page
    ---------------->

    <link rel="icon" href="{{ asset('img/profile_picture_icon.png') }}" type="image/png">
    <title>{{ config('app.name') }} - @yield('title')</title>

    <!--------------
          Meta
    ---------------->

    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta name="language" content="{{ app()->getLocale() }}">

    <!--------------
          Font
    ---------------->

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet"/>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Slab:wght@100..900&display=swap" rel="stylesheet">

    <!--------------
         Styles
    ---------------->
    @yield('css')

    @vite(['resources/sass/app.scss', 'resources/js/app.js'])
</head>
<body>

    <!--------------
          Main
    ---------------->

<div class="container">
    @yield('content')
    @if (isset($slot))
        {{ $slot }}
    @endif
    <livewire:partials.footer />
</div>

    <livewire:partials.back-to-web />
    <livewire:partials.scroll-to-top />

    <!--------------
        Scripts
    ---------------->

@yield('scripts')
</body>
</html>
