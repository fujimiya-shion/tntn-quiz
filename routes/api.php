<?php

use App\Http\Controllers\Api\QuizPlayController;
use App\Http\Controllers\Api\QuizRoomController;
use Illuminate\Support\Facades\Route;

Route::prefix('quiz')->group(function (): void {
    Route::post('/host/login', [QuizRoomController::class, 'login']);
    Route::get('/host/quizzes', [QuizRoomController::class, 'quizzes']);
    Route::post('/host/rooms', [QuizRoomController::class, 'createForHost']);

    Route::post('/rooms/{roomCode}/join', [QuizRoomController::class, 'join']);
    Route::get('/rooms/{roomCode}/players', [QuizRoomController::class, 'players']);
    Route::post('/rooms/{roomCode}/host/next', [QuizRoomController::class, 'next']);
    Route::post('/rooms/{roomCode}/host/finish', [QuizRoomController::class, 'finish']);

    Route::post('/rooms/{roomCode}/answers', [QuizPlayController::class, 'submitAnswer']);
    Route::get('/rooms/{roomCode}/state', [QuizPlayController::class, 'state']);
    Route::get('/rooms/{roomCode}/results', [QuizPlayController::class, 'results']);
});
