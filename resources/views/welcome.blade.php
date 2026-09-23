<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-950">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'MochyFami Content Studio') }}</title>
        @vite(['resources/css/app.css', 'resources/js/main.tsx'])
    </head>
    <body class="h-full font-sans antialiased bg-slate-950 text-slate-100">
        <div id="app"></div>
    </body>
</html>
