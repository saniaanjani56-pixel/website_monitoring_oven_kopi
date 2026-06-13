<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Events\SensorDataUpdated;

// Test broadcast dengan data dummy
$temp = 25 + rand(-5, 5);
$hum = 60 + rand(-10, 10);

echo "Broadcasting sensor data...\n";
echo "Temperature: {$temp}°C\n";
echo "Humidity: {$hum}%\n";

SensorDataUpdated::dispatch(
    temperature: $temp,
    humidity: $hum,
    fanState: rand(0, 1) === 1,
    fanSpeed: rand(0, 100),
    relayStates: [
        'r1' => rand(0, 1),
        'r2' => rand(0, 1),
        'r3' => rand(0, 1),
        'r4' => rand(0, 1),
    ]
);

echo "Broadcast sent! Check your browser console.\n";
