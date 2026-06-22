<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class HeaterTimerTest extends TestCase
{
    use RefreshDatabase;

    public function test_timer_can_be_started(): void
    {
        Cache::put('relay_states', ['r1' => 1, 'r2' => 0, 'r3' => 0]);

        $response = $this->postJson('/api/heater-timer', [
            'duration_seconds' => 90,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('timer.active', true)
            ->assertJsonPath('timer.remaining_seconds', 90);
    }

    public function test_zero_duration_is_rejected(): void
    {
        $this->postJson('/api/heater-timer', ['duration_seconds' => 0])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('duration_seconds');
    }

    public function test_timer_requires_at_least_one_active_heater(): void
    {
        $this->postJson('/api/heater-timer', ['duration_seconds' => 60])
            ->assertUnprocessable()
            ->assertJsonPath('success', false);
    }

    public function test_stopping_timer_turns_every_heater_off(): void
    {
        Cache::put('heater_timer', ['active' => true, 'ends_at' => now()->addMinute()->timestamp]);
        Cache::put('relay_states', ['r1' => 1, 'r2' => 1, 'r3' => 1]);

        $this->deleteJson('/api/heater-timer')
            ->assertOk()
            ->assertJsonPath('timer.active', false)
            ->assertJsonPath('relayStates.r1', 0)
            ->assertJsonPath('relayStates.r2', 0)
            ->assertJsonPath('relayStates.r3', 0);

        $this->assertDatabaseHas('relay_status', [
            'r1' => 0,
            'r2' => 0,
            'r3' => 0,
        ]);
    }

    public function test_expired_timer_is_normalized_and_turns_heaters_off(): void
    {
        Cache::put('heater_timer', ['active' => true, 'ends_at' => now()->subSecond()->timestamp]);
        Cache::put('relay_states', ['r1' => 1, 'r2' => 1, 'r3' => 0]);

        $this->getJson('/api/sensors')
            ->assertOk()
            ->assertJsonPath('timer_active', false)
            ->assertJsonPath('timer_remaining', 0)
            ->assertJsonPath('relayStates.r1', 0)
            ->assertJsonPath('relayStates.r2', 0)
            ->assertJsonPath('relayStates.r3', 0);
    }

    public function test_latest_sensor_response_contains_stable_sensor_identity(): void
    {
        $this->postJson('/api/sensor-data', [
            'temp' => 31.5,
            'hum' => 62.4,
        ])->assertOk();

        $response = $this->getJson('/api/sensors')
            ->assertOk()
            ->assertJsonPath('sensorData.temp', 31.5)
            ->assertJsonPath('sensorData.hum', 62.4)
            ->assertJsonStructure([
                'sensorData' => ['id', 'timestamp'],
            ]);

        $this->assertIsInt($response->json('sensorData.id'));
        $this->assertNotNull($response->json('sensorData.timestamp'));
    }
}
