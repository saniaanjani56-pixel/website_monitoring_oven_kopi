<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RelayController;
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// ESP32 Routes
Route::post('/sensor-data', [RelayController::class, 'receiveSensorData']);
Route::get('/sensors', [RelayController::class, 'getSensorData']);
Route::post('/relay', [RelayController::class, 'setRelay']);
Route::post('/motor', [RelayController::class, 'setMotor']);
Route::post('/heater-timer', [RelayController::class, 'startHeaterTimer']);
Route::delete('/heater-timer', [RelayController::class, 'stopHeaterTimer']);

// Endpoint untuk history data dari database
Route::get('/sensor-history', [RelayController::class, 'getSensorHistory']);

// Health check untuk ESP32
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toISOString()
    ]);
});

// ESP32 Heartbeat/Ping System
Route::post('/ping', [RelayController::class, 'receivePing']);
Route::get('/esp32-status', [RelayController::class, 'getEsp32Status']);

// SSE - Real-time updates (non-blocking, auto-reconnect)
Route::get('/events', [RelayController::class, 'sseStream']);
