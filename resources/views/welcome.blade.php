<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Quiz') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.jsx'])
</head>
<body data-page="{{ $page ?? 'landing' }}" data-room-code="{{ $roomCode ?? '' }}">
    <div id="app"
         data-page="{{ $page ?? 'landing' }}"
         data-room-code="{{ $roomCode ?? '' }}">
    </div>
</body>
</html>
