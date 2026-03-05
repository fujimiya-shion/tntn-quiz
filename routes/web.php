<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome', [
        'page' => 'landing',
        'roomCode' => null,
    ]);
});

Route::get('/host', function () {
    return view('welcome', [
        'page' => 'host',
        'roomCode' => null,
    ]);
});

Route::get('/host/{roomCode}', function (string $roomCode) {
    return view('welcome', [
        'page' => 'host',
        'roomCode' => strtoupper($roomCode),
    ]);
});

Route::get('/player/{roomCode}', function (string $roomCode) {
    return view('welcome', [
        'page' => 'player',
        'roomCode' => strtoupper($roomCode),
    ]);
});

Route::get('/player', function () {
    return view('welcome', [
        'page' => 'player',
        'roomCode' => null,
    ]);
});

Route::get('/room/join/{roomCode}', function (string $roomCode) {
    return view('welcome', [
        'page' => 'player',
        'roomCode' => strtoupper($roomCode),
    ]);
});

Route::get('/room/join', function () {
    return view('welcome', [
        'page' => 'player',
        'roomCode' => null,
    ]);
});
