<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - ESP32 Monitoring System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f1f5f9;
            min-height: 100vh;
            padding: 30px 20px;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 25px 30px;
            border-radius: 20px;
            margin-bottom: 25px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .header-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .header-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }
        .header h1 {
            font-size: 28px;
            font-weight: 700;
            color: #1e293b;
        }
        .header-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .status-badge {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: white;
            border-radius: 50px;
            font-size: 13px;
            font-weight: 600;
            color: #64748b;
            border: 1px solid #e2e8f0;
        }
        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #ccc;
            animation: pulse 2s infinite;
        }
        .status-dot.active {
            background: #4ade80;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        .grid-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 25px;
            margin-bottom: 25px;
        }
        .grid-row-2 {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
            margin-bottom: 25px;
        }
        .card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }
        .card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 48px rgba(0, 0, 0, 0.15);
        }
        .card-header {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white;
            padding: 18px 25px;
            font-weight: 600;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            letter-spacing: 0.5px;
        }
        .card-body {
            padding: 25px;
        }

        /* Metric Cards */
        .metric-card {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .metric-icon-wrapper {
            width: 70px;
            height: 70px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
        }
        .metric-icon-wrapper.temp {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
        }
        .metric-icon-wrapper.hum {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        .metric-icon-wrapper.fan {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
        }
        .metric-info h3 {
            font-size: 13px;
            color: #64748b;
            margin-bottom: 8px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .metric-value {
            font-size: 32px;
            font-weight: 700;
            color: #1e293b;
        }
        .metric-unit {
            font-size: 18px;
            color: #64748b;
            font-weight: 500;
        }

        /* Fan Control */
        .fan-control-section {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }
        .fan-power-section {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px;
            background: #f8fafc;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
        }
        .fan-power-btn {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 24px;
            border: 2px solid #e2e8f0;
            border-radius: 50px;
            background: white;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
            font-weight: 700;
            color: #64748b;
        }
        .fan-power-btn:hover {
            border-color: #3b82f6;
            transform: scale(1.02);
        }
        .fan-power-btn.active {
            border-color: #4ade80;
            background: linear-gradient(135deg, #4ade80 0%, #22c55e 100%);
            color: white;
            box-shadow: 0 4px 20px rgba(74, 222, 128, 0.4);
        }
        .power-icon {
            font-size: 18px;
        }
        .fan-power-btn.active .power-icon {
            animation: vibrate 0.3s infinite;
        }
        @keyframes vibrate {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-3px); }
            75% { transform: translateX(3px); }
        }
        .fan-indicator {
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: #cbd5e1;
            box-shadow: 0 0 0 4px rgba(203, 213, 225, 0.2);
            transition: all 0.3s ease;
        }
        .fan-indicator.active {
            background: #4ade80;
            box-shadow: 0 0 0 4px rgba(74, 222, 128, 0.2), 0 0 20px rgba(74, 222, 128, 0.6);
        }
        .fan-speed-section {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .speed-label {
            font-size: 14px;
            color: #64748b;
            font-weight: 600;
            display: flex;
            justify-content: space-between;
        }
        .speed-value {
            color: #3b82f6;
            font-weight: 700;
        }
        .speed-slider {
            width: 100%;
            height: 10px;
            border-radius: 50px;
            background: linear-gradient(90deg, #3b82f6 0%, #1d4ed8 100%);
            outline: none;
            -webkit-appearance: none;
            appearance: none;
            cursor: pointer;
        }
        .speed-slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: white;
            cursor: pointer;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            border: 4px solid #3b82f6;
            transition: all 0.3s ease;
        }
        .speed-slider::-webkit-slider-thumb:hover {
            transform: scale(1.1);
            box-shadow: 0 4px 20px rgba(59, 130, 246, 0.4);
        }
        .speed-slider::-moz-range-thumb {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: white;
            cursor: pointer;
            border: 4px solid #3b82f6;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
        }
        .speed-slider:disabled {
            opacity: 0.3;
            cursor: not-allowed;
        }
        .speed-marks {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            color: #94a3b8;
            font-weight: 600;
        }

        /* Relay Grid */
        .relay-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            padding: 10px;
        }
        .relay-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 18px 20px;
            background: #f8fafc;
            border-radius: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        .relay-item:hover {
            background: #f1f5f9;
            transform: translateY(-2px);
        }
        .relay-item.active {
            background: linear-gradient(135deg, rgba(74, 222, 128, 0.1) 0%, rgba(34, 197, 94, 0.1) 100%);
            border-color: #4ade80;
            box-shadow: 0 4px 20px rgba(74, 222, 128, 0.2);
        }
        .relay-name {
            font-weight: 600;
            color: #475569;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .relay-icon {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 16px;
        }
        .relay-toggle {
            width: 52px;
            height: 28px;
            background: #cbd5e1;
            border-radius: 50px;
            position: relative;
            transition: all 0.3s ease;
        }
        .relay-item.active .relay-toggle {
            background: linear-gradient(135deg, #4ade80 0%, #22c55e 100%);
            box-shadow: 0 2px 10px rgba(74, 222, 128, 0.4);
        }
        .relay-toggle::after {
            content: '';
            position: absolute;
            width: 22px;
            height: 22px;
            background: white;
            border-radius: 50%;
            top: 3px;
            left: 3px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }
        .relay-item.active .relay-toggle::after {
            left: 27px;
        }

        /* Timer */
        .timer-display {
            font-size: 48px;
            font-weight: 700;
            text-align: center;
            color: #1e293b;
            font-family: 'Courier New', monospace;
            letter-spacing: 4px;
        }
        .timer-inputs {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin: 18px 0 8px;
        }
        .timer-input-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .timer-input-group label {
            color: #64748b;
            font-size: 12px;
            font-weight: 600;
            text-align: center;
        }
        .timer-input {
            width: 100%;
            padding: 10px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            color: #1e293b;
            font-size: 18px;
            font-weight: 700;
            text-align: center;
        }
        .timer-input:disabled {
            background: #f1f5f9;
            color: #94a3b8;
        }
        .timer-status {
            min-height: 20px;
            color: #64748b;
            font-size: 12px;
            text-align: center;
        }
        .timer-controls {
            display: flex;
            justify-content: center;
            padding-top: 10px;
        }
        .play-btn {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            box-shadow: 0 4px 20px rgba(59, 130, 246, 0.4);
        }
        .play-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 8px 30px rgba(59, 130, 246, 0.5);
        }
        .play-btn::before {
            content: '';
            width: 0;
            height: 0;
            border-left: 24px solid white;
            border-top: 14px solid transparent;
            border-bottom: 14px solid transparent;
            margin-left: 6px;
        }
        .play-btn.playing::before {
            border-left: 8px solid white;
            border-right: 8px solid white;
        }
        .play-btn:disabled {
            cursor: wait;
            opacity: 0.6;
        }

        /* Monitoring */
        .monitoring-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 25px;
            border-bottom: 1px solid #e2e8f0;
        }
        .monitoring-title {
            font-size: 16px;
            font-weight: 700;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .monitoring-title i {
            color: #3b82f6;
        }
        .monitoring-tabs {
            display: flex;
            gap: 8px;
            background: #f1f5f9;
            padding: 4px;
            border-radius: 12px;
        }
        .tab {
            padding: 8px 20px;
            border-radius: 8px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: transparent;
            color: #64748b;
            font-weight: 600;
        }
        .tab:hover {
            color: #3b82f6;
        }
        .tab.active {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white;
            box-shadow: 0 2px 10px rgba(59, 130, 246, 0.3);
        }
        .chart-container {
            padding: 20px 25px;
            height: 280px;
        }

        /* Table */
        .table-container {
            padding: 0;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table th {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            padding: 16px 20px;
            text-align: left;
            font-size: 12px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e2e8f0;
        }
        .data-table td {
            padding: 16px 20px;
            font-size: 14px;
            color: #334155;
            border-bottom: 1px solid #f1f5f9;
            font-weight: 500;
        }
        .data-table tr:hover {
            background: rgba(102, 126, 234, 0.05);
        }
        .table-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 50px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .table-status-badge.active {
            background: linear-gradient(135deg, rgba(74, 222, 128, 0.2) 0%, rgba(34, 197, 94, 0.2) 100%);
            color: #16a34a;
        }
        .table-status-badge.inactive {
            background: rgba(203, 213, 225, 0.3);
            color: #64748b;
        }
        .uptime-icon {
            margin-right: 8px;
            color: #3b82f6;
        }

        /* Pagination */
        .pagination {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px 25px;
            border-top: 1px solid #e2e8f0;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        }
        .pagination-info {
            font-size: 13px;
            color: #64748b;
            font-weight: 600;
        }
        .pagination-controls {
            display: flex;
            gap: 6px;
        }
        .pagination-btn {
            padding: 8px 14px;
            border: 2px solid #e2e8f0;
            background: white;
            border-radius: 10px;
            cursor: pointer;
            font-size: 13px;
            color: #64748b;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .pagination-btn:hover:not(:disabled) {
            border-color: #3b82f6;
            color: #3b82f6;
            transform: translateY(-1px);
        }
        .pagination-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .pagination-btn.active {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white;
            border-color: transparent;
            box-shadow: 0 2px 10px rgba(59, 130, 246, 0.3);
        }
        .header-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        #esp32Badge {
            display: none;
        }
        #esp32Badge.visible {
            display: flex;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .grid-row {
                grid-template-columns: repeat(2, 1fr);
            }
            .relay-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        @media (max-width: 768px) {
            .grid-row, .grid-row-2 {
                grid-template-columns: 1fr;
            }
            .relay-grid {
                grid-template-columns: 1fr;
            }
            .header {
                flex-direction: column;
                gap: 15px;
            }
            .header-info {
                width: 100%;
                justify-content: space-between;
            }
            .pagination {
                align-items: stretch;
                flex-direction: column;
                gap: 12px;
                padding: 16px;
            }
            .pagination-info {
                text-align: center;
            }
            .pagination-controls {
                width: 100%;
                overflow-x: auto;
                padding-bottom: 4px;
                scroll-behavior: smooth;
                -webkit-overflow-scrolling: touch;
            }
            .pagination-btn {
                flex: 0 0 auto;
                touch-action: manipulation;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-left">
                <div class="header-icon">
                    <i class="fas fa-microchip"></i>
                </div>
                <h1>ESP32 Monitoring System</h1>
            </div>
            <div class="header-info">
                <div class="status-badge">
                    <div class="status-dot" id="statusDot"></div>
                    <span id="connectionStatus">Checking ESP32...</span>
                </div>
                <div class="status-badge" id="esp32Badge">
                    <i class="fas fa-wifi"></i>
                    <span id="esp32Status">--</span>
                </div>
            </div>
        </div>

        <!-- Row 1: Temperature, Humidity, Fan Control -->
        <div class="grid-row">
            <div class="card">
                <div class="card-body metric-card">
                    <div class="metric-icon-wrapper temp">
                        <i class="fas fa-temperature-high" style="color: white;"></i>
                    </div>
                    <div class="metric-info">
                        <h3>Temperature</h3>
                        <div class="metric-value" id="tempValue">--<span class="metric-unit">°C</span></div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body metric-card">
                    <div class="metric-icon-wrapper hum">
                        <i class="fas fa-tint" style="color: white;"></i>
                    </div>
                    <div class="metric-info">
                        <h3>Humidity</h3>
                        <div class="metric-value" id="humValue">--<span class="metric-unit">%</span></div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <i class="fas fa-cog"></i>
                    Motor Control
                </div>
                <div class="card-body">
                    <div class="fan-control-section">
                        <!-- Fan Control -->
                        <div class="fan-power-section">
                            <button class="fan-power-btn" id="fanPowerBtn" onclick="toggleFanPower()">
                                <i class="fas fa-fan power-icon"></i>
                                <span class="power-text">OFF</span>
                            </button>
                            <div class="fan-indicator" id="fanIndicator"></div>
                        </div>

                        <!-- Fan Speed -->
                        <div class="fan-speed-section">
                            <div class="speed-label">
                                <span>Fan Speed</span>
                                <span class="speed-value"><span id="speedValue">0</span>%</span>
                            </div>
                            <input type="range" class="speed-slider" id="speedSlider" min="0" max="100" value="0" step="1" oninput="updateFanSpeed(this.value)" disabled>
                            <div class="speed-marks">
                                <span>0%</span>
                                <span>25%</span>
                                <span>50%</span>
                                <span>75%</span>
                                <span>100%</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Row 2: Relay heater, Timer -->
        <div class="grid-row-2">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-fire"></i>
                    Relay Heater
                </div>
                <div class="relay-grid">
                    <div class="relay-item" data-relay="r1" onclick="toggleRelay('r1')">
                        <span class="relay-name">
                            <span class="relay-icon"><i class="fas fa-fire"></i></span>
                            heater 1
                        </span>
                        <div class="relay-toggle"></div>
                    </div>
                    <div class="relay-item" data-relay="r2" onclick="toggleRelay('r2')">
                        <span class="relay-name">
                            <span class="relay-icon"><i class="fas fa-fire"></i></span>
                            heater 2
                        </span>
                        <div class="relay-toggle"></div>
                    </div>
                    <div class="relay-item" data-relay="r3" onclick="toggleRelay('r3')">
                        <span class="relay-name">
                            <span class="relay-icon"><i class="fas fa-fire"></i></span>
                            heater 3
                        </span>
                        <div class="relay-toggle"></div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <i class="fas fa-stopwatch"></i>
                    Timer
                </div>
                <div class="card-body">
                    <div class="timer-display" id="timerDisplay">00:00:00</div>
                    <div class="timer-inputs">
                        <div class="timer-input-group">
                            <label for="timerHours">Jam</label>
                            <input class="timer-input" id="timerHours" type="number" min="0" max="168" value="0">
                        </div>
                        <div class="timer-input-group">
                            <label for="timerMinutes">Menit</label>
                            <input class="timer-input" id="timerMinutes" type="number" min="0" max="59" value="0">
                        </div>
                        <div class="timer-input-group">
                            <label for="timerSeconds">Detik</label>
                            <input class="timer-input" id="timerSeconds" type="number" min="0" max="59" value="0">
                        </div>
                    </div>
                    <div class="timer-status" id="timerStatus">Pilih heater, masukkan durasi, lalu mulai.</div>
                    <div class="timer-controls">
                        <button class="play-btn" id="playBtn" type="button" onclick="toggleTimer()" aria-label="Mulai timer"></button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Row 3: Live Monitoring -->
        <div class="card">
            <div class="monitoring-header">
                <span class="monitoring-title">
                    <i class="fas fa-chart-line"></i>
                    Live Monitoring
                </span>
                <div class="monitoring-tabs">
                    <div class="tab active" data-period="1hour" onclick="switchPeriod('1hour')">
                        <i class="fas fa-clock"></i> 1 HOUR
                    </div>
                    <div class="tab" data-period="24hours" onclick="switchPeriod('24hours')">
                        <i class="fas fa-calendar-day"></i> 24 HOURS
                    </div>
                </div>
            </div>
            <div class="chart-container">
                <canvas id="monitoringChart"></canvas>
            </div>
        </div>

        <!-- Row 4: Recent Data -->
        <div class="card">
            <div class="monitoring-header">
                <span class="monitoring-title">
                    <i class="fas fa-history"></i>
                    Recent Data
                </span>
            </div>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th><i class="fas fa-clock"></i> Time</th>
                            <th><i class="fas fa-temperature-high"></i> Temp</th>
                            <th><i class="fas fa-tint"></i> Humidity</th>
                            <th><i class="fas fa-fan"></i> Fan Status</th>
                            <th><i class="fas fa-hourglass-half"></i> Uptime</th>
                        </tr>
                    </thead>
                    <tbody id="dataTableBody">
                        <!-- Data will be loaded here -->
                    </tbody>
                </table>
                <div class="pagination">
                    <span class="pagination-info" id="paginationInfo">Showing 0-0 of 0 entries</span>
                    <div class="pagination-controls" id="paginationControls">
                        <!-- Pagination buttons will be generated here -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let relayStates = { r1: 0, r2: 0, r3: 0 };  // 0 = OFF, 1 = ON
        let fanState = false;
        let fanSpeed = 0;
        let timerRunning = false;
        let timerDeadlineMs = null;
        let timerInterval = null;

        let dataHistory = [];
        let currentPage = Math.max(1, parseInt(sessionStorage.getItem('sensorTablePage') || '1', 10));
        let itemsPerPage = 10;
        let lastTableSensorId = 0;
        let lastPaginationMarkup = '';
        let eventSource = null;
        let esp32Online = false;
        let esp32LastSeen = null;

        // Chart setup
        const ctx = document.getElementById('monitoringChart').getContext('2d');
        const monitoringChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: [],
                datasets: [
                    {
                        label: 'Temperature (°C)',
                        data: [],
                        borderColor: '#ff6b6b',
                        backgroundColor: 'rgba(255, 107, 107, 0.1)',
                        borderWidth: 3,
                        tension: 0.4,
                        fill: true,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        pointBackgroundColor: '#ff6b6b',
                        pointBorderColor: 'white',
                        pointBorderWidth: 2,
                        pointHoverBorderWidth: 3
                    },
                    {
                        label: 'Humidity (%)',
                        data: [],
                        borderColor: '#4facfe',
                        backgroundColor: 'rgba(79, 172, 254, 0.1)',
                        borderWidth: 3,
                        tension: 0.4,
                        fill: true,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        pointBackgroundColor: '#4facfe',
                        pointBorderColor: 'white',
                        pointBorderWidth: 2,
                        pointHoverBorderWidth: 3
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            padding: 20,
                            font: {
                                size: 13,
                                weight: '600',
                                family: 'Inter'
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12,
                        titleFont: {
                            size: 13,
                            weight: '600'
                        },
                        bodyFont: {
                            size: 12
                        },
                        cornerRadius: 8,
                        displayColors: true
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: {
                                size: 11
                            }
                        }
                    },
                    y: {
                        beginAtZero: false,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        },
                        ticks: {
                            font: {
                                size: 11
                            }
                        }
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index'
                }
            }
        });

        function updateSensorDisplay(temp, hum) {
            const tempEl = document.getElementById('tempValue');
            const humEl = document.getElementById('humValue');

            if (tempEl) {
                tempEl.innerHTML = `${parseFloat(temp).toFixed(1)}<span class="metric-unit">°C</span>`;
            }
            if (humEl) {
                humEl.innerHTML = `${parseFloat(hum).toFixed(1)}<span class="metric-unit">%</span>`;
            }
        }

        function updateChart(temp, hum) {
            const now = new Date();
            const timeStr = now.toLocaleTimeString('id-ID', { hour12: false });

            monitoringChart.data.labels.push(timeStr);
            monitoringChart.data.datasets[0].data.push(temp);
            monitoringChart.data.datasets[1].data.push(hum);

            if (monitoringChart.data.labels.length > 20) {
                monitoringChart.data.labels.shift();
                monitoringChart.data.datasets[0].data.shift();
                monitoringChart.data.datasets[1].data.shift();
            }

            monitoringChart.update('none');
        }

        // Initialize chart with sample data
        function initializeChartData() {
            const now = new Date();
            for (let i = 10; i >= 0; i--) {
                const time = new Date(now - i * 5000);
                const timeStr = time.toLocaleTimeString('id-ID', { hour12: false });
                const baseTemp = 25 + Math.sin(i / 2) * 3;
                const baseHum = 60 + Math.cos(i / 2) * 8;

                monitoringChart.data.labels.push(timeStr);
                monitoringChart.data.datasets[0].data.push(baseTemp);
                monitoringChart.data.datasets[1].data.push(baseHum);
            }
            monitoringChart.update();
        }

        function updateTable(temp, hum, sensorId, timestamp) {
            const numericSensorId = Number(sensorId);

            // SSE terkoneksi ulang setiap 500ms. Abaikan pembacaan yang sudah ada.
            if (!Number.isFinite(numericSensorId) || numericSensorId <= lastTableSensorId) {
                return false;
            }

            const sensorTime = timestamp ? new Date(timestamp) : new Date();
            const timeStr = sensorTime.toLocaleTimeString('id-ID', { hour12: false });
            const timerRemaining = timerRunning && timerDeadlineMs
                ? Math.max(0, Math.ceil((timerDeadlineMs - Date.now()) / 1000))
                : null;
            const uptimeStr = timerRemaining === null ? '-' : formatUptime(timerRemaining);

            const newData = {
                sensorId: numericSensorId,
                time: timeStr,
                temp: temp.toFixed(1),
                hum: hum.toFixed(1),
                fanState: fanState,
                uptime: uptimeStr
            };

            dataHistory.unshift(newData);
            lastTableSensorId = numericSensorId;

            if (dataHistory.length > 100) {
                dataHistory.pop();
            }

            const totalPages = Math.max(1, Math.ceil(dataHistory.length / itemsPerPage));
            setCurrentPage(Math.min(currentPage, totalPages));
            // Saat pengguna membaca halaman lama, jangan ganti baris yang sedang dilihat.
            if (currentPage === 1) {
                renderTable();
            }
            updatePaginationControls();
            return true;
        }

        function renderTable() {
            const tbody = document.getElementById('dataTableBody');
            if (!tbody) return;

            tbody.innerHTML = '';

            const startIndex = (currentPage - 1) * itemsPerPage;
            const endIndex = startIndex + itemsPerPage;
            const pageData = dataHistory.slice(startIndex, endIndex);

            if (pageData.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 30px; color: #94a3b8;"><i class="fas fa-inbox" style="font-size: 32px; margin-bottom: 10px;"></i><br>No data available</td></tr>';
                return;
            }

            pageData.forEach(data => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td><i class="far fa-clock" style="margin-right: 8px; color: #667eea;"></i>${data.time}</td>
                    <td><span style="color: #ff6b6b; font-weight: 600;">${data.temp}°C</span></td>
                    <td><span style="color: #4facfe; font-weight: 600;">${data.hum}%</span></td>
                    <td><span class="table-status-badge ${data.fanState ? 'active' : 'inactive'}"><i class="fas fa-${data.fanState ? 'check' : 'times'}"></i> ${data.fanState ? 'ON' : 'OFF'}</span></td>
                    <td><span class="uptime-icon"><i class="fas fa-hourglass-half"></i></span>${data.uptime}</td>
                `;
                tbody.appendChild(row);
            });
        }

        function updatePaginationControls() {
            const totalPages = Math.ceil(dataHistory.length / itemsPerPage);
            const paginationInfo = document.getElementById('paginationInfo');
            const paginationControls = document.getElementById('paginationControls');

            const startItem = dataHistory.length === 0 ? 0 : (currentPage - 1) * itemsPerPage + 1;
            const endItem = Math.min(currentPage * itemsPerPage, dataHistory.length);
            paginationInfo.innerHTML = `<i class="fas fa-list"></i> Showing <strong>${startItem}-${endItem}</strong> of <strong>${dataHistory.length}</strong> entries`;

            let buttonsHTML = '';
            buttonsHTML += `<button type="button" class="pagination-btn" onclick="previousPage()" ${currentPage === 1 ? 'disabled' : ''}><i class="fas fa-chevron-left"></i> Previous</button>`;

            for (let i = 1; i <= totalPages; i++) {
                if (i === 1 || i === totalPages || (i >= currentPage - 1 && i <= currentPage + 1)) {
                    buttonsHTML += `<button type="button" class="pagination-btn ${i === currentPage ? 'active' : ''}" onclick="goToPage(${i})">${i}</button>`;
                } else if (i === currentPage - 2 || i === currentPage + 2) {
                    buttonsHTML += '<span style="padding: 8px; color: #94a3b8;">...</span>';
                }
            }

            buttonsHTML += `<button type="button" class="pagination-btn" onclick="nextPage()" ${currentPage === totalPages || totalPages === 0 ? 'disabled' : ''}>Next <i class="fas fa-chevron-right"></i></button>`;

            // Jangan buat ulang tombol jika state-nya tidak berubah. Ini mencegah
            // horizontal scroll mobile kembali ke tombol halaman 1 setiap SSE masuk.
            if (lastPaginationMarkup !== buttonsHTML) {
                const previousScrollLeft = paginationControls.scrollLeft;
                paginationControls.innerHTML = buttonsHTML;
                lastPaginationMarkup = buttonsHTML;
                requestAnimationFrame(() => {
                    paginationControls.scrollLeft = previousScrollLeft;
                });
            }
        }

        function setCurrentPage(page) {
            currentPage = Math.max(1, Number(page) || 1);
            sessionStorage.setItem('sensorTablePage', currentPage.toString());
        }

        function previousPage() {
            if (currentPage > 1) {
                setCurrentPage(currentPage - 1);
                renderTable();
                updatePaginationControls();
            }
        }

        function nextPage() {
            const totalPages = Math.ceil(dataHistory.length / itemsPerPage);
            if (currentPage < totalPages) {
                setCurrentPage(currentPage + 1);
                renderTable();
                updatePaginationControls();
            }
        }

        function goToPage(page) {
            setCurrentPage(page);
            renderTable();
            updatePaginationControls();
        }

        function toggleFanPower() {
            fanState = !fanState;

            const btn = document.getElementById('fanPowerBtn');
            const indicator = document.getElementById('fanIndicator');
            const slider = document.getElementById('speedSlider');

            btn.classList.toggle('active', fanState);
            indicator.classList.toggle('active', fanState);
            btn.querySelector('.power-text').textContent = fanState ? 'ON' : 'OFF';
            slider.disabled = !fanState;

            if (!fanState) {
                fanSpeed = 0;
                slider.value = 0;
                document.getElementById('speedValue').textContent = '0';
            } else {
                fanSpeed = slider.value > 0 ? parseInt(slider.value) : 50;
                slider.value = fanSpeed;
                document.getElementById('speedValue').textContent = fanSpeed;
            }

            sendFanCommand();
        }

        function updateFanSpeed(value) {
            fanSpeed = parseInt(value);
            document.getElementById('speedValue').textContent = value;
            sendFanCommand();
        }

        function sendFanCommand() {
            fetch('/api/motor', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    fan_state: fanState,
                    fan_speed: fanSpeed
                })
            })
            .then(response => response.json())
            .then(data => {
                console.log('Motor command sent:', data);
            })
            .catch(e => console.error('Failed to control motors:', e));
        }

        async function toggleRelay(relay) {
            console.log(`🔄 Toggling relay ${relay}...`);
            console.log(`  Current state before: ${relayStates[relay]}`);

            // Toggle state local dulu untuk immediate feedback
            const newState = relayStates[relay] ? 0 : 1;
            relayStates[relay] = newState;

            console.log(`  New state after toggle: ${newState}`);
            console.log(`  Sending to server:`, relayStates);

            // Update UI immediately
            const el = document.querySelector(`[data-relay="${relay}"]`);
            el.classList.remove('active');
            if (newState === 1) {
                el.classList.add('active');
            }

            try {
                const response = await fetch('/api/relay', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(relayStates)
                });

                console.log(`  Response status: ${response.status}`);

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }

                const result = await response.json();
                console.log('  Server response:', result);

                if (result.success) {
                    // Update relay states dari server
                    relayStates = result.relayStates || relayStates;

                    console.log('  Updated states from server:', relayStates);

                    // Sync UI dengan states dari server
                    document.querySelectorAll('.relay-item').forEach(item => {
                        const r = item.dataset.relay;
                        item.classList.remove('active');
                        if (relayStates[r] === 1) {
                            item.classList.add('active');
                        }
                    });
                } else {
                    throw new Error(result.error || 'Failed to update relay');
                }
            } catch (e) {
                console.error('❌ Failed to update relay:', e);
                // Revert state jika gagal
                relayStates[relay] = newState ? 0 : 1;
                const el = document.querySelector(`[data-relay="${relay}"]`);
                el.classList.remove('active');
                if (relayStates[relay] === 1) {
                    el.classList.add('active');
                }
            }
        }

        function renderTimer() {
            const remainingSeconds = timerRunning && timerDeadlineMs
                ? Math.max(0, Math.ceil((timerDeadlineMs - Date.now()) / 1000))
                : 0;

            document.getElementById('timerDisplay').textContent = formatUptime(remainingSeconds);

            if (timerRunning && remainingSeconds === 0) {
                timerRunning = false;
                timerDeadlineMs = null;
                updateTimerControls();
                document.getElementById('timerStatus').textContent = 'Waktu habis. Semua heater dimatikan.';
                refreshData();
            }
        }

        function updateTimerControls() {
            const playBtn = document.getElementById('playBtn');
            const inputs = document.querySelectorAll('.timer-input');

            playBtn.classList.toggle('playing', timerRunning);
            playBtn.setAttribute('aria-label', timerRunning ? 'Hentikan timer' : 'Mulai timer');
            inputs.forEach(input => input.disabled = timerRunning);
        }

        function updateTimerState(timer) {
            if (!timer) return;

            timerRunning = timer.active === true || timer.active === 1;
            const remaining = Math.max(0, parseInt(timer.remaining_seconds || 0, 10));
            timerDeadlineMs = timerRunning ? Date.now() + (remaining * 1000) : null;

            updateTimerControls();
            renderTimer();

            if (timerRunning) {
                document.getElementById('timerStatus').textContent = 'Timer aktif. Tekan tombol untuk menghentikan dan mematikan heater.';
            }

            if (!timerInterval) {
                timerInterval = setInterval(renderTimer, 250);
            }
        }

        async function toggleTimer() {
            const playBtn = document.getElementById('playBtn');
            playBtn.disabled = true;

            try {
                if (timerRunning) {
                    const response = await fetch('/api/heater-timer', { method: 'DELETE' });
                    const result = await response.json();

                    if (!response.ok || !result.success) {
                        throw new Error(result.message || result.error || 'Gagal menghentikan timer');
                    }

                    relayStates = result.relayStates || relayStates;
                    updateTimerState(result.timer);
                    document.getElementById('timerStatus').textContent = 'Timer dihentikan. Semua heater dimatikan.';
                    await refreshData();
                    return;
                }

                const hours = parseInt(document.getElementById('timerHours').value || 0, 10);
                const minutes = parseInt(document.getElementById('timerMinutes').value || 0, 10);
                const seconds = parseInt(document.getElementById('timerSeconds').value || 0, 10);
                const durationSeconds = (hours * 3600) + (minutes * 60) + seconds;

                if (hours < 0 || hours > 168 || minutes < 0 || minutes > 59 || seconds < 0 || seconds > 59 || durationSeconds < 1 || durationSeconds > 604800) {
                    throw new Error('Durasi harus antara 1 detik dan 7 hari.');
                }

                if (!Object.values(relayStates).some(state => state === 1)) {
                    throw new Error('Aktifkan minimal satu heater sebelum memulai timer.');
                }

                const response = await fetch('/api/heater-timer', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ duration_seconds: durationSeconds })
                });
                const result = await response.json();

                if (!response.ok || !result.success) {
                    throw new Error(result.message || result.error || 'Gagal memulai timer');
                }

                updateTimerState(result.timer);
            } catch (error) {
                document.getElementById('timerStatus').textContent = error.message;
            } finally {
                playBtn.disabled = false;
            }
        }

        function formatUptime(seconds) {
            const h = Math.floor(seconds / 3600);
            const m = Math.floor((seconds % 3600) / 60);
            const s = seconds % 60;
            return `${h.toString().padStart(2, '0')}:${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`;
        }

        function switchPeriod(period) {
            document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
            document.querySelector(`[data-period="${period}"]`).classList.add('active');
            console.log('Switched to:', period);
        }

        function initializeTableData() {
            fetchSensorHistory();
        }

        async function fetchSensorHistory() {
            try {
                const response = await fetch('/api/sensor-history?limit=25');
                const result = await response.json();

                if (result.success && result.data) {
                    dataHistory = result.data.map(item => {
                        const time = new Date(item.created_at);
                        return {
                            sensorId: Number(item.id),
                            time: time.toLocaleTimeString('id-ID', { hour12: false }),
                            temp: item.temperature,
                            hum: item.humidity,
                            fanState: false,
                            uptime: '-'
                        };
                    });

                    lastTableSensorId = dataHistory.reduce(
                        (latestId, item) => Math.max(latestId, item.sensorId || 0),
                        0
                    );
                    const totalPages = Math.max(1, Math.ceil(dataHistory.length / itemsPerPage));
                    setCurrentPage(Math.min(currentPage, totalPages));
                    renderTable();
                    updatePaginationControls();
                }
            } catch (error) {
                console.error('Error fetching sensor history:', error);
            }
        }

        async function refreshData() {
            try {
                const response = await fetch('/api/sensors');
                const data = await response.json();

                if (data.connected) {
                    const temp = data.sensorData.temp;
                    const hum = data.sensorData.hum;
                    updateSensorDisplay(temp, hum);
                    if (updateTable(temp, hum, data.sensorData.id, data.sensorData.timestamp)) {
                        updateChart(temp, hum);
                    }
                }

                // Update relay states
                relayStates = data.relayStates || relayStates;
                Object.keys(relayStates).forEach(relay => {
                    const el = document.querySelector(`[data-relay="${relay}"]`);
                    if (el) {
                        el.classList.remove('active');
                        if (relayStates[relay] === 1) {
                            el.classList.add('active');
                        }
                    }
                });

                // Update fan state
                if (data.fan_state !== undefined) {
                    fanState = data.fan_state === true || data.fan_state === 1;
                    const btn = document.getElementById('fanPowerBtn');
                    const indicator = document.getElementById('fanIndicator');
                    const slider = document.getElementById('speedSlider');

                    btn.classList.toggle('active', fanState);
                    indicator.classList.toggle('active', fanState);
                    btn.querySelector('.power-text').textContent = fanState ? 'ON' : 'OFF';
                    slider.disabled = !fanState;

                    if (data.fan_speed !== undefined && data.fan_speed > 0) {
                        fanSpeed = data.fan_speed;
                        slider.value = fanSpeed;
                        document.getElementById('speedValue').textContent = fanSpeed;
                    }
                }

                updateTimerState({
                    active: data.timer_active,
                    ends_at: data.timer_ends_at,
                    remaining_seconds: data.timer_remaining
                });
            } catch (e) {
                console.error('Failed to fetch sensor data:', e);
            }
        }

        // Update ESP32 status display
        function updateEsp32Status(online, lastSeen) {
            esp32Online = online;
            esp32LastSeen = lastSeen ? new Date(lastSeen) : null;

            const esp32Badge = document.getElementById('esp32Badge');
            const esp32Status = document.getElementById('esp32Status');
            const statusDot = document.getElementById('statusDot');
            const connectionStatus = document.getElementById('connectionStatus');

            if (online) {
                esp32Badge.classList.add('visible');
                esp32Status.textContent = 'ESP32 Connected';
                statusDot.classList.add('active');
                connectionStatus.textContent = 'Connected to ESP32';
            } else {
                esp32Badge.classList.remove('visible');
                esp32Status.textContent = 'ESP32 Offline';
                statusDot.classList.remove('active');
                connectionStatus.textContent = 'ESP32 Disconnected';
            }
        }

        // Handle SSE messages (auto-reconnect approach)
        function handleSSEMessage(data) {
            if (data.sensor) {
                updateSensorDisplay(data.sensor.temp, data.sensor.hum);
                if (updateTable(data.sensor.temp, data.sensor.hum, data.sensor.id, data.sensor.timestamp)) {
                    updateChart(data.sensor.temp, data.sensor.hum);
                }
            }

            if (data.relays) {
                relayStates = data.relays;
                Object.keys(relayStates).forEach(relay => {
                    const el = document.querySelector(`[data-relay="${relay}"]`);
                    if (el) {
                        el.classList.remove('active');
                        if (relayStates[relay] === 1) {
                            el.classList.add('active');
                        }
                    }
                });
            }

            if (data.fan_state !== undefined) {
                fanState = data.fan_state === true || data.fan_state === 1;
                const btn = document.getElementById('fanPowerBtn');
                const indicator = document.getElementById('fanIndicator');
                const slider = document.getElementById('speedSlider');

                btn.classList.toggle('active', fanState);
                indicator.classList.toggle('active', fanState);
                btn.querySelector('.power-text').textContent = fanState ? 'ON' : 'OFF';
                slider.disabled = !fanState;

                if (data.fan_speed !== undefined && data.fan_speed > 0) {
                    fanSpeed = data.fan_speed;
                    slider.value = fanSpeed;
                    document.getElementById('speedValue').textContent = fanSpeed;
                }
            }

            if (data.esp32) {
                updateEsp32Status(data.esp32.online, data.esp32.last_seen);
            }

            if (data.timer) {
                updateTimerState(data.timer);
            }
        }

        // Connect SSE with auto-reconnect
        function connectSSE() {
            eventSource = new EventSource('/api/events');

            eventSource.onmessage = function(event) {
                try {
                    const data = JSON.parse(event.data);
                    if (data.type === 'update') {
                        handleSSEMessage(data);
                    }
                } catch (e) {
                    console.error('SSE parse error:', e);
                }
            };

            eventSource.onopen = function() {
                console.log('✓ SSE Connected');
            };

            eventSource.onerror = function(err) {
                // Expected: server closes connection after sending data
                // EventSource will auto-reconnect
                eventSource.close();
                setTimeout(connectSSE, 500);
            };
        }

        // Initialize on page load
        initializeChartData();
        initializeTableData();

        // Connect SSE untuk real-time updates (auto-reconnect approach)
        connectSSE();
    </script>
</body>
</html>
