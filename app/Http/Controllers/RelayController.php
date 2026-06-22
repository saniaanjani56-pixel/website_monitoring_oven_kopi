<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RelayController extends Controller
{
    private function defaultRelayStates(): array
    {
        return [
            'r1' => 0,
            'r2' => 0,
            'r3' => 0,
        ];
    }

    private function getRelayStates(): array
    {
        return array_merge(
            $this->defaultRelayStates(),
            Cache::get('relay_states', [])
        );
    }

    private function turnOffAllHeaters(): array
    {
        $relayStates = $this->defaultRelayStates();

        Cache::put('relay_states', $relayStates, now()->addDay());

        DB::table('relay_status')->insert([
            ...$relayStates,
            'r4' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $relayStates;
    }

    private function getHeaterTimerState(): array
    {
        $timer = array_merge([
            'active' => false,
            'ends_at' => null,
        ], Cache::get('heater_timer', []));

        $remainingSeconds = $timer['active'] && $timer['ends_at']
            ? max(0, (int) $timer['ends_at'] - now()->timestamp)
            : 0;

        if ($timer['active'] && $remainingSeconds === 0) {
            $this->turnOffAllHeaters();
            $timer = ['active' => false, 'ends_at' => null];
            Cache::put('heater_timer', $timer, now()->addDay());
        }

        return [
            'active' => (bool) $timer['active'],
            'ends_at' => $timer['ends_at'],
            'remaining_seconds' => $remainingSeconds,
        ];
    }

    /**
     * Get sensor data (ESP32 & Frontend polling this endpoint)
     */
    public function getSensorData()
    {
        // Mengecek deadline juga memastikan relay server menjadi OFF saat timer habis.
        $timer = $this->getHeaterTimerState();

        // Ambil relay states dari cache
        $relayStates = $this->getRelayStates();

        // Fan command state
        $fanState = Cache::get('fan_state', false);
        $fanSpeed = Cache::get('fan_speed', 0);

        // Ambil last sensor data dari cache (yang dikirim ESP32)
        $lastTemp = Cache::get('last_temp', 0);
        $lastHum = Cache::get('last_hum', 0);

        // Return data
        return response()->json([
            'connected' => true,
            'sensorData' => [
                'temp' => $lastTemp,
                'hum' => $lastHum
            ],
            'relayStates' => $relayStates,
            'fan_state' => $fanState,
            'fan_speed' => $fanSpeed,
            'timer_active' => $timer['active'],
            'timer_ends_at' => $timer['ends_at'],
            'timer_remaining' => $timer['remaining_seconds'],
        ]);
    }

    /**
     * Mulai countdown heater. Status relay yang sedang dipilih tetap digunakan.
     */
    public function startHeaterTimer(Request $request)
    {
        $data = $request->validate([
            'duration_seconds' => 'required|integer|min:1|max:604800',
        ]);

        $relayStates = $this->getRelayStates();
        if (!collect($relayStates)->contains(fn ($state) => (int) $state === 1)) {
            return response()->json([
                'success' => false,
                'message' => 'Aktifkan minimal satu heater sebelum memulai timer.',
            ], 422);
        }

        $timer = [
            'active' => true,
            'ends_at' => now()->addSeconds($data['duration_seconds'])->timestamp,
        ];

        Cache::put('heater_timer', $timer, now()->addDays(8));

        return response()->json([
            'success' => true,
            'timer' => $this->getHeaterTimerState(),
            'relayStates' => $relayStates,
        ]);
    }

    /**
     * Batalkan countdown dan matikan semua heater.
     */
    public function stopHeaterTimer()
    {
        Cache::put('heater_timer', [
            'active' => false,
            'ends_at' => null,
        ], now()->addDay());

        return response()->json([
            'success' => true,
            'timer' => $this->getHeaterTimerState(),
            'relayStates' => $this->turnOffAllHeaters(),
        ]);
    }

    /**
     * Set relay states (dari frontend dashboard)
     */
    public function setRelay(Request $request)
    {
        try {
            $data = $request->validate([
                'r1' => 'required|integer|in:0,1',
                'r2' => 'required|integer|in:0,1',
                'r3' => 'required|integer|in:0,1',
            ]);

            // Simpan ke database tabel relay_status
            DB::table('relay_status')->insert([
                'r1' => $data['r1'],
                'r2' => $data['r2'],
                'r3' => $data['r3'],
                'r4' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Simpan ke cache juga (untuk quick access ESP32)
            Cache::put('relay_states', $data, now()->addDay());

            return response()->json([
                'success' => true,
                'relayStates' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Set fan state dari frontend dashboard
     */
    public function setMotor(Request $request)
    {
        try {
            $data = $request->validate([
                'fan_state' => 'required|boolean',
                'fan_speed' => 'required|integer|min:0|max:100',
            ]);

            // Simpan ke cache
            Cache::put('fan_state', $data['fan_state'], now()->addDay());
            Cache::put('fan_speed', (int) $data['fan_speed'], now()->addDay());

            return response()->json([
                'success' => true,
                'fan_state' => $data['fan_state'],
                'fan_speed' => (int) $data['fan_speed']
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Endpoint untuk ESP32 mengirim data sensor
     * ESP32 POST data ke sini, langsung simpan ke cache
     */
    public function receiveSensorData(Request $request)
    {
        try {
            $data = $request->validate([
                'temp' => 'required|numeric',
                'hum' => 'required|numeric',
            ]);

            // Simpan ke database tabel sensor_data
            DB::table('sensor_data')->insert([
                'temperature' => $data['temp'],
                'humidity' => $data['hum'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Simpan last sensor data ke cache juga (untuk quick access)
            Cache::put('last_temp', $data['temp'], now()->addHour());
            Cache::put('last_hum', $data['hum'], now()->addHour());

            return response()->json([
                'success' => true,
                'message' => 'Sensor data received'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Receive heartbeat ping from ESP32
     * ESP32 calls this every 1-5 minutes to keep connection alive
     */
    public function receivePing(Request $request)
    {
        try {
            // Store last ping timestamp (expire after 10 minutes)
            Cache::put('esp32_last_seen', now()->toDateTimeString(), now()->addMinutes(10));

            return response()->json([
                'success' => true,
                'status' => 'pong',
                'timestamp' => now()->toDateTimeString(),
                'message' => 'Ping received'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get ESP32 connection status
     * Check if ESP32 is online based on last ping
     */
    public function getEsp32Status()
    {
        $lastSeen = Cache::get('esp32_last_seen');
        $isOnline = false;

        if ($lastSeen) {
            // ESP32 considered online if ping received within last 1 minute
            $lastSeenTime = now()->parse($lastSeen);
            $isOnline = $lastSeenTime->diffInSeconds(now()) <= 60;
        }

        return response()->json([
            'online' => $isOnline,
            'last_seen' => $lastSeen
        ]);
    }

    /**
     * SSE Stream - Real-time updates ke frontend (non-blocking approach)
     * Mengirim data sekali lalu client auto-reconnect untuk update berikutnya
     */
    public function sseStream()
    {
        // Disable output buffering
        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', 1);
        }
        @ini_set('output_buffering', 0);
        @ini_set('zlib.output_compression', 0);
        @ini_set('implicit_flush', 1);

        return response()->stream(function () {
            $timer = $this->getHeaterTimerState();

            // Ambil data terbaru dari cache
            $currentData = [
                'temp' => Cache::get('last_temp', 0),
                'hum' => Cache::get('last_hum', 0),
                'timestamp' => now()->toDateTimeString()
            ];

            $relayStates = $this->getRelayStates();

            $fanState = Cache::get('fan_state', false);
            $fanSpeed = Cache::get('fan_speed', 0);

            $lastSeen = Cache::get('esp32_last_seen');
            $esp32Online = false;
            if ($lastSeen) {
                $lastSeenTime = now()->parse($lastSeen);
                $esp32Online = $lastSeenTime->diffInSeconds(now()) <= 60;
            }

            // Kirim data SEKALI saja, lalu tutup connection
            $payload = [
                'type' => 'update',
                'sensor' => $currentData,
                'relays' => $relayStates,
                'fan_state' => $fanState,
                'fan_speed' => $fanSpeed,
                'timer' => $timer,
                'esp32' => [
                    'online' => $esp32Online,
                    'last_seen' => $lastSeen
                ]
            ];

            echo "data: " . json_encode($payload) . "\n\n";
            ob_flush();
            flush();

            // Tunggu sebentar lalu tutup (biar client auto-reconnect)
            usleep(100000); // 100ms
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Helper: Trigger SSE update saat data berubah
     * Dipanggil saat ESP32 kirim data atau user toggle relay
     */
    private function triggerSSEUpdate()
    {
        $counter = Cache::get('sse_update_counter', 0);
        Cache::put('sse_update_counter', $counter + 1, now()->addMinute());
    }

    /**
     * Get sensor history from database for table display
     */
    public function getSensorHistory(Request $request)
    {
        try {
            $limit = $request->input('limit', 25);
            $limit = min($limit, 100);

            $data = DB::table('sensor_data')
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
