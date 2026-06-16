#include <Arduino.h>
#include <WiFi.h>
#include <HTTPClient.h>
#include <DHT.h>
#include <LiquidCrystal_I2C.h>
#include <Wire.h>

// ==================== WIFI CREDENTIALS ====================
const char* ssid = "sania";
const char* password = "12341234";

// ==================== LARAVEL SERVER ====================
const char* laravelBaseUrl = "http://192.168.137.1:8000";

// Endpoint URLs
const char* sensorDataEndpoint = "/api/sensor-data";
const char* sensorsEndpoint    = "/api/sensors";
const char* pingEndpoint       = "/api/ping";

// ==================== FITUR OPTIONAL ====================
#define USE_LCD false   // LCD dicabut dulu
#define USE_DHT true    // DHT belum dipasang

// ==================== HTTP SETTING ====================
const unsigned long HTTP_TIMEOUT = 1200;
bool httpBusy = false;
int httpFailCount = 0;

// ==================== DHT SENSOR ====================
#define DHT_SENSOR_TYPE DHT22
static const int DHT_SENSOR_PIN = 5;
DHT dht_sensor(DHT_SENSOR_PIN, DHT_SENSOR_TYPE);

// ==================== LCD I2C 16x2 ====================
LiquidCrystal_I2C lcd(0x27, 16, 2);

enum DisplayMode {
  LCD_WIFI_CONNECTING,
  LCD_SENSOR_DATA,
  LCD_RELAY_STATES
};

DisplayMode currentDisplayMode = LCD_WIFI_CONNECTING;
unsigned long lastDisplayChange = 0;
unsigned long lastLCDUpdate = 0;

const unsigned long DISPLAY_CHANGE_INTERVAL = 3000;
const unsigned long LCD_UPDATE_INTERVAL = 1000;

// ==================== RELAY PINS ====================
const int relay1 = 32;
const int relay2 = 33;

// ==================== RELAY MODE ====================
// RELAY AKTIF HIGH:
// HIGH = ON
// LOW  = OFF
#define RELAY_ON  HIGH
#define RELAY_OFF LOW

// ==================== L298N MOTOR DRIVER PINS ====================
// MOTOR 1 - Fan
const int mtr2_in3 = 4;
const int mtr2_in4 = 18;
const int fan_enb  = 23;

// ==================== MOTOR QUIET MODE ====================
unsigned long motorQuietUntil = 0;
const unsigned long MOTOR_QUIET_TIME = 8000;

unsigned long lastAlivePrint = 0;
bool wasMotorQuiet = false;

// ==================== TIMING ====================
unsigned long lastDHT = 0;
unsigned long lastSensorSend = 0;
unsigned long lastRelayCheck = 0;
unsigned long lastPing = 0;

const unsigned long DHT_INTERVAL         = 5000;
const unsigned long SENSOR_SEND_INTERVAL = 10000;
const unsigned long RELAY_CHECK_INTERVAL = 3000;
const unsigned long PING_INTERVAL        = 15000;

// ==================== RELAY STATES ====================
struct RelayStates {
  bool r1;
  bool r2;
};

RelayStates relayStates = {false, false};

// ==================== MOTOR STATES ====================
struct MotorStates {
  bool fan;
  int fanSpeed;
  bool vibration;  // FIX: tambah field vibration agar LCD tidak error
};

MotorStates motorStates = {false, 0, false};

// ==================== SENSOR DATA ====================
struct SensorReadings {
  float temp;
  float hum;
  bool valid;
};

SensorReadings sensorReadings = {0, 0, true};

// ==================== FUNCTION DECLARATION ====================
void connectWiFi();
void reconnectWiFiLight();
void readSensors();
void sendSensorData();
void checkRelayCommands();
void sendPing();
void updateLCD();
void setFanMotor(bool on, int speedPercent);
void handleHttpResult(int code);

// ==================== SETUP ====================
void setup() {
  Serial.begin(115200);
  delay(1000);

  Serial.println();
  Serial.println("=== ESP32 Monitoring System ===");
  Serial.println("Starting...");

  // ==================== RELAY INIT ====================
  pinMode(relay1, OUTPUT);
  pinMode(relay2, OUTPUT);

  // Relay aktif HIGH: LOW = OFF
  digitalWrite(relay1, RELAY_OFF);
  digitalWrite(relay2, RELAY_OFF);

  relayStates.r1 = false;
  relayStates.r2 = false;

  Serial.println("Relay initialized, ACTIVE HIGH, all OFF");

  // ==================== MOTOR INIT ====================
  pinMode(mtr2_in3, OUTPUT);
  pinMode(mtr2_in4, OUTPUT);
  pinMode(fan_enb, OUTPUT);

  digitalWrite(mtr2_in3, LOW);
  digitalWrite(mtr2_in4, LOW);
  analogWrite(fan_enb, 0);

  motorStates.fan = false;
  motorStates.fanSpeed = 0;
  motorStates.vibration = false;

  Serial.println("L298N initialized, motors OFF");

  // ==================== DHT INIT ====================
  if (USE_DHT) {
    dht_sensor.begin();
    Serial.println("DHT22 initialized");
  } else {
    sensorReadings.temp = 0;
    sensorReadings.hum = 0;
    sensorReadings.valid = true;

    Serial.println("DHT disabled, using dummy temp=0 hum=0");
  }

  // ==================== LCD INIT ====================
  if (USE_LCD) {
    Wire.begin(21, 22);
    Wire.setClock(10000);

    lcd.init();
    lcd.backlight();
    lcd.clear();
    lcd.setCursor(0, 0);
    lcd.print("ESP32 Starting");
    lcd.setCursor(0, 1);
    lcd.print("Please wait...");

    Serial.println("LCD initialized");
  } else {
    Serial.println("LCD disabled");
  }

  // ==================== WIFI INIT ====================
  connectWiFi();

  Serial.println("=== Setup Complete ===");

  sendPing();
}

// ==================== WIFI CONNECTION ====================
void connectWiFi() {
  Serial.print("Connecting WiFi");

  if (USE_LCD) {
    lcd.clear();
    lcd.setCursor(0, 0);
    lcd.print("Connecting WiFi");
    lcd.setCursor(0, 1);
    lcd.print("Please wait...");
  }

  WiFi.mode(WIFI_STA);
  WiFi.setSleep(false);
  WiFi.begin(ssid, password);

  int attempts = 0;

  while (WiFi.status() != WL_CONNECTED && attempts < 15) {
    delay(300);
    Serial.print(".");
    attempts++;
    yield();
  }

  if (WiFi.status() == WL_CONNECTED) {
    Serial.println();
    Serial.println("WiFi Connected!");
    Serial.print("IP: ");
    Serial.println(WiFi.localIP());

    if (USE_LCD) {
      lcd.clear();
      lcd.setCursor(0, 0);
      lcd.print("WiFi Connected");
      lcd.setCursor(0, 1);
      lcd.print(WiFi.localIP());
      delay(1500);
      currentDisplayMode = LCD_SENSOR_DATA;
    }
  } else {
    Serial.println();
    Serial.println("WiFi Failed, restart ESP32");

    if (USE_LCD) {
      lcd.clear();
      lcd.setCursor(0, 0);
      lcd.print("WiFi Failed");
      lcd.setCursor(0, 1);
      lcd.print("Restarting...");
    }

    delay(1000);
    ESP.restart();
  }
}

// ==================== LIGHT WIFI RECONNECT ====================
void reconnectWiFiLight() {
  static unsigned long lastReconnectTry = 0;
  unsigned long now = millis();

  if (now - lastReconnectTry < 5000) {
    return;
  }

  lastReconnectTry = now;

  Serial.println("WiFi disconnected, reconnecting light...");

  WiFi.disconnect();
  delay(100);
  WiFi.begin(ssid, password);
}

// ==================== READ SENSORS ====================
void readSensors() {
  if (!USE_DHT) {
    sensorReadings.temp = 0;
    sensorReadings.hum = 0;
    sensorReadings.valid = true;
    return;
  }

  float t = dht_sensor.readTemperature();
  float h = dht_sensor.readHumidity();

  if (isnan(t) || isnan(h)) {
    Serial.println("DHT read failed, ignored");
    return;
  }

  sensorReadings.temp = t;
  sensorReadings.hum = h;
  sensorReadings.valid = true;

  Serial.printf("Temp: %.1f C | Hum: %.1f %%\n", t, h);
}

// ==================== HTTP FAIL HANDLER ====================
void handleHttpResult(int code) {
  if (code > 0) {
    httpFailCount = 0;
    return;
  }

  httpFailCount++;

  Serial.printf("HTTP fail count: %d\n", httpFailCount);

  if (httpFailCount >= 3) {
    Serial.println("Too many HTTP fails, reconnect WiFi...");

    WiFi.disconnect();
    delay(300);
    WiFi.begin(ssid, password);

    httpFailCount = 0;
  }
}

// ==================== SEND DATA TO LARAVEL ====================
void sendSensorData() {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("Skip send data, WiFi disconnected");
    return;
  }

  if (!sensorReadings.valid) {
    Serial.println("Skip send data, sensor invalid");
    return;
  }

  HTTPClient http;

  String url = String(laravelBaseUrl) + String(sensorDataEndpoint);

  String jsonPayload = "{\"temp\":" + String(sensorReadings.temp, 1) + "," +
                       "\"hum\":" + String(sensorReadings.hum, 1) + "," +
                       "\"fan_state\":" + String(motorStates.fan ? "true" : "false") + "," +
                       "\"fan_speed\":" + String(motorStates.fanSpeed) + "}";

  http.begin(url);
  http.addHeader("Content-Type", "application/json");
  http.addHeader("Accept", "application/json");
  http.setTimeout(HTTP_TIMEOUT);

  int httpResponseCode = http.POST(jsonPayload);
  handleHttpResult(httpResponseCode);

  if (httpResponseCode > 0) {
    Serial.printf("Sensor data sent, status: %d\n", httpResponseCode);
  } else {
    Serial.printf("Send data HTTP error: %d\n", httpResponseCode);
  }

  http.end();
  yield();
}

// ==================== CHECK RELAY COMMANDS FROM SERVER ====================
void checkRelayCommands() {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("Skip check command, WiFi disconnected");
    return;
  }

  HTTPClient http;

  String url = String(laravelBaseUrl) + String(sensorsEndpoint);

  http.begin(url);
  http.addHeader("Accept", "application/json");
  http.setTimeout(HTTP_TIMEOUT);

  int httpResponseCode = http.GET();
  handleHttpResult(httpResponseCode);

  if (httpResponseCode == 200) {
    String response = http.getString();

    Serial.println("Server command received");

    // ==================== PARSE RELAY STATES ====================
    int r1 = response.indexOf("\"r1\":");
    int r2 = response.indexOf("\"r2\":");

    if (r1 > 0 && r2 > 0) {
      int newState1 = response.charAt(r1 + 5) - '0';
      int newState2 = response.charAt(r2 + 5) - '0';

      // Relay aktif HIGH: HIGH = ON, LOW = OFF
      if (newState1 != relayStates.r1) {
        relayStates.r1 = newState1;
        digitalWrite(relay1, newState1 ? RELAY_ON : RELAY_OFF);
        Serial.printf("Relay 1: %s\n", newState1 ? "ON" : "OFF");
      }

      if (newState2 != relayStates.r2) {
        relayStates.r2 = newState2;
        digitalWrite(relay2, newState2 ? RELAY_ON : RELAY_OFF);
        Serial.printf("Relay 2: %s\n", newState2 ? "ON" : "OFF");
      }
    }

    // ==================== PARSE MOTOR STATES ====================
    int fanStateIdx = response.indexOf("\"fan_state\":");
    int fanSpeedIdx = response.indexOf("\"fan_speed\":");

    bool newFanState = false;

    if (fanStateIdx > 0) {
      int fanStateStart = response.indexOf(":", fanStateIdx) + 1;
      String fanStateStr = response.substring(fanStateStart, fanStateStart + 6);
      fanStateStr.trim();

      if (fanStateStr.indexOf("true") >= 0 || fanStateStr.indexOf("1") >= 0) {
        newFanState = true;
      }
    }

    int newFanSpeed = 0;

    if (fanSpeedIdx > 0) {
      int fanSpeedStart = response.indexOf(":", fanSpeedIdx) + 1;
      int fanSpeedEnd = response.indexOf(",", fanSpeedStart);

      if (fanSpeedEnd < 0) {
        fanSpeedEnd = response.indexOf("}", fanSpeedStart);
      }

      newFanSpeed = response.substring(fanSpeedStart, fanSpeedEnd).toInt();

      if (newFanSpeed < 0) newFanSpeed = 0;
      if (newFanSpeed > 100) newFanSpeed = 100;
    }

    if (newFanState != motorStates.fan || newFanSpeed != motorStates.fanSpeed) {
      setFanMotor(newFanState, newFanSpeed);
    }

  } else {
    // FIX: else sekarang benar-benar berada di dalam fungsi
    Serial.printf("Check command HTTP error/status: %d\n", httpResponseCode);
  }

  http.end();
  yield();
}

// ==================== SEND HEARTBEAT PING ====================
void sendPing() {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("Skip ping, WiFi disconnected");
    return;
  }

  HTTPClient http;

  String url = String(laravelBaseUrl) + String(pingEndpoint);

  http.begin(url);
  http.addHeader("Content-Type", "application/json");
  http.addHeader("Accept", "application/json");
  http.setTimeout(HTTP_TIMEOUT);

  int httpResponseCode = http.POST("{}");
  handleHttpResult(httpResponseCode);

  if (httpResponseCode > 0) {
    Serial.printf("Ping sent, status: %d\n", httpResponseCode);
  } else {
    Serial.printf("Ping HTTP error: %d\n", httpResponseCode);
  }

  http.end();
  yield();
}

// ==================== LCD DISPLAY FUNCTIONS ====================
void updateLCD() {
  if (!USE_LCD) {
    return;
  }

  unsigned long now = millis();

  if (now - lastDisplayChange >= DISPLAY_CHANGE_INTERVAL) {
    lastDisplayChange = now;

    if (currentDisplayMode == LCD_WIFI_CONNECTING) {
      currentDisplayMode = LCD_SENSOR_DATA;
    } else if (currentDisplayMode == LCD_SENSOR_DATA) {
      currentDisplayMode = LCD_RELAY_STATES;
    } else {
      currentDisplayMode = LCD_SENSOR_DATA;
    }

    lcd.clear();
  }

  switch (currentDisplayMode) {
    case LCD_WIFI_CONNECTING:
      lcd.setCursor(0, 0);
      lcd.print("WiFi Connecting ");
      lcd.setCursor(0, 1);
      lcd.print("Please wait...  ");
      break;

    case LCD_SENSOR_DATA:
      lcd.setCursor(0, 0);

      if (sensorReadings.valid) {
        lcd.print("T:");
        lcd.print(sensorReadings.temp, 1);
        lcd.print("C H:");
        lcd.print(sensorReadings.hum, 1);
        lcd.print("% ");
      } else {
        lcd.print("Sensor: Error   ");
      }

      lcd.setCursor(0, 1);
      lcd.print("V:");
      // FIX: motorStates.vibration sekarang ada di struct
      lcd.print(motorStates.vibration ? "ON " : "OFF");

      lcd.print(" F:");
      lcd.print(motorStates.fan ? "ON " : "OFF");

      lcd.print(" ");
      lcd.print(motorStates.fanSpeed);
      lcd.print("%   ");
      break;

    case LCD_RELAY_STATES:
      lcd.setCursor(0, 0);
      lcd.print("R1:");
      lcd.print(relayStates.r1 ? "ON " : "OFF");

      lcd.setCursor(8, 0);
      lcd.print("R2:");
      lcd.print(relayStates.r2 ? "ON " : "OFF");
      break;
  }
}

// ==================== MOTOR 1 - FAN ====================
void setFanMotor(bool on, int speedPercent) {
  if (speedPercent < 0) speedPercent = 0;
  if (speedPercent > 100) speedPercent = 100;

  if (on && speedPercent > 0) {
    digitalWrite(mtr2_in3, HIGH);
    digitalWrite(mtr2_in4, LOW);

    int pwmValue = map(speedPercent, 0, 100, 0, 255);
    analogWrite(fan_enb, pwmValue);

    Serial.printf("Fan: ON %d%%, PWM: %d\n", speedPercent, pwmValue);
  } else {
    digitalWrite(mtr2_in3, LOW);
    digitalWrite(mtr2_in4, LOW);
    analogWrite(fan_enb, 0);

    Serial.println("Fan: OFF");
    speedPercent = 0;
  }

  motorStates.fan = on;
  motorStates.fanSpeed = speedPercent;
}

// ==================== MAIN LOOP ====================
void loop() {
  unsigned long now = millis();
  bool motorQuiet = now < motorQuietUntil;

  // ==================== DEBUG: CEK APA ESP32 MASIH HIDUP ====================
  if (motorQuiet) {
    wasMotorQuiet = true;

    if (now - lastAlivePrint >= 1000) {
      lastAlivePrint = now;
      Serial.println("Motor quiet mode, ESP32 still alive...");
    }
  }

  if (!motorQuiet && wasMotorQuiet) {
    wasMotorQuiet = false;
    Serial.println("Motor quiet finished, HTTP resumed");
  }

  // ==================== WIFI CHECK RINGAN ====================
  if (WiFi.status() != WL_CONNECTED) {
    reconnectWiFiLight();
    delay(10);
    yield();
    return;
  }

  // ==================== BACA SENSOR ====================
  if (!httpBusy && now - lastDHT >= DHT_INTERVAL) {
    lastDHT = now;
    readSensors();
    yield();
    return;
  }

  // ==================== CEK RELAY DAN MOTOR ====================
  if (!motorQuiet && !httpBusy && now - lastRelayCheck >= RELAY_CHECK_INTERVAL) {
    lastRelayCheck = now;

    httpBusy = true;
    checkRelayCommands();
    httpBusy = false;

    yield();
    return;
  }

  // ==================== KIRIM DATA SENSOR ====================
  if (!motorQuiet && !httpBusy && now - lastSensorSend >= SENSOR_SEND_INTERVAL) {
    lastSensorSend = now;

    if (sensorReadings.valid) {
      httpBusy = true;
      sendSensorData();
      httpBusy = false;
    }

    yield();
    return;
  }

  // ==================== PING WEBSITE ====================
  if (!motorQuiet && !httpBusy && now - lastPing >= PING_INTERVAL) {
    lastPing = now;

    httpBusy = true;
    sendPing();
    httpBusy = false;

    yield();
    return;
  }

  // ==================== LCD UPDATE DIBATASI ====================
  if (USE_LCD && now - lastLCDUpdate >= LCD_UPDATE_INTERVAL) {
    lastLCDUpdate = now;

    updateLCD();

    yield();
    return;
  }

  delay(10);
  yield();
}