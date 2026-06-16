#include <Arduino.h>
#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <HTTPClient.h>
#include <DHT.h>
#include <LiquidCrystal_I2C.h>
#include <Wire.h>

// ==================== WIFI CREDENTIALS ====================
const char* ssid = "CYCLONE";
const char* password = "12341234";

// ==================== LARAVEL SERVER HTTPS ====================
// Domain Laravel kamu
const char* laravelBaseUrl = "https://monitoring-oven-kopi.my.id";

// Endpoint Laravel
const char* sensorDataEndpoint = "/api/sensor-data";
const char* sensorsEndpoint    = "/api/sensors";
const char* pingEndpoint       = "/api/ping";

// ==================== FITUR OPTIONAL ====================
#define USE_LCD true
#define USE_DHT true

// ==================== HTTP SETTING ====================
const unsigned long HTTP_TIMEOUT = 8000;
const unsigned long HTTP_COOLDOWN_AFTER_FAIL = 5000;

bool httpBusy = false;
int httpFailCount = 0;
unsigned long lastHttpFailTime = 0;

// ==================== WIFI STATE ====================
unsigned long lastReconnectTry = 0;
const unsigned long WIFI_RECONNECT_INTERVAL = 10000;
bool wifiConnecting = false;

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
const int relay3 = 25; // Ubah sesuai wiring heater 3 jika memakai pin lain.

// RELAY AKTIF HIGH
#define RELAY_ON  HIGH
#define RELAY_OFF LOW

// ==================== BUZZER PIN ====================
const int buzzerPin = 26;

// BUZZER ACTIVE LOW
#define BUZZER_ON  LOW
#define BUZZER_OFF HIGH

// ==================== SAFETY LIMIT ====================
const float TEMP_LIMIT = 65.0;
bool overTempAlarm = false;

// ==================== L298N MOTOR DRIVER PINS ====================
const int mtr2_in3 = 4;
const int mtr2_in4 = 18;
const int fan_enb  = 23;

// ==================== TIMING ====================
unsigned long lastDHT = 0;
unsigned long lastSensorSend = 0;
unsigned long lastRelayCheck = 0;
unsigned long lastPing = 0;
unsigned long lastAlivePrint = 0;

const unsigned long DHT_INTERVAL         = 5000;
const unsigned long SENSOR_SEND_INTERVAL = 15000;
const unsigned long RELAY_CHECK_INTERVAL = 5000;
const unsigned long PING_INTERVAL        = 30000;

const unsigned long RELAY_CHECK_INTERVAL_FAN_ON = 8000;
const unsigned long SENSOR_SEND_INTERVAL_FAN_ON = 20000;

// ==================== RELAY STATES ====================
struct RelayStates {
  bool r1;
  bool r2;
  bool r3;
};

RelayStates relayStates = {false, false, false};

// ==================== MOTOR STATES ====================
struct MotorStates {
  bool fan;
  int fanSpeed;
  bool vibration;
};

MotorStates motorStates = {false, 0, false};

// ==================== SENSOR DATA ====================
struct SensorReadings {
  float temp;
  float hum;
  bool valid;
};

SensorReadings sensorReadings = {0, 0, false};

// ==================== FUNCTION DECLARATION ====================
void connectWiFiNoRestart();
void maintainWiFi();
void readSensors();
void sendSensorData();
void checkRelayCommands();
void sendPing();
void updateLCD();
void setFanMotor(bool on, int speedPercent);
void handleHttpResult(int code);
bool canDoHttp();
int httpGET(String url, String &response);
int httpPOST(String url, String payload, String &response);
int getJsonInt(String response, const char* key, int defaultValue);
bool getJsonBool(String response, const char* key, bool defaultValue);
void handleOverTemperature();
void setAllRelaysOff();

// ==================== SETUP ====================
void setup() {
  Serial.begin(115200);
  delay(1000);

  Serial.println();
  Serial.println("=== ESP32 Monitoring Oven Kopi HTTPS ===");
  Serial.println("Starting...");

  // ==================== RELAY INIT ====================
  pinMode(relay1, OUTPUT);
  pinMode(relay2, OUTPUT);
  pinMode(relay3, OUTPUT);

  digitalWrite(relay1, RELAY_OFF);
  digitalWrite(relay2, RELAY_OFF);
  digitalWrite(relay3, RELAY_OFF);

  relayStates.r1 = false;
  relayStates.r2 = false;
  relayStates.r3 = false;

  Serial.println("Relay initialized, ACTIVE HIGH, all OFF");

  // ==================== BUZZER INIT ====================
  pinMode(buzzerPin, OUTPUT);
  digitalWrite(buzzerPin, BUZZER_OFF);

  Serial.println("Buzzer initialized on pin 26, ACTIVE LOW, OFF");

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

  Serial.println("L298N initialized, fan OFF");

  // ==================== DHT INIT ====================
  if (USE_DHT) {
    dht_sensor.begin();
    Serial.println("DHT22 initialized");
  } else {
    sensorReadings.temp = 0;
    sensorReadings.hum = 0;
    sensorReadings.valid = true;
    Serial.println("DHT disabled, dummy data enabled");
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
  WiFi.mode(WIFI_STA);
  WiFi.setSleep(false);
  WiFi.setAutoReconnect(true);
  WiFi.persistent(false);

  connectWiFiNoRestart();

  Serial.println("=== Setup Complete ===");

  if (WiFi.status() == WL_CONNECTED) {
    sendPing();
  }
}

// ==================== WIFI CONNECTION TANPA RESTART ====================
void connectWiFiNoRestart() {
  Serial.print("Connecting WiFi");

  if (USE_LCD) {
    lcd.clear();
    lcd.setCursor(0, 0);
    lcd.print("Connecting WiFi");
    lcd.setCursor(0, 1);
    lcd.print("Please wait...");
  }

  wifiConnecting = true;

  WiFi.disconnect(true);
  delay(300);
  WiFi.mode(WIFI_STA);
  WiFi.setSleep(false);
  WiFi.begin(ssid, password);

  unsigned long startAttempt = millis();

  while (WiFi.status() != WL_CONNECTED && millis() - startAttempt < 15000) {
    delay(300);
    Serial.print(".");
    yield();
  }

  wifiConnecting = false;

  if (WiFi.status() == WL_CONNECTED) {
    Serial.println();
    Serial.println("WiFi Connected!");
    Serial.print("IP: ");
    Serial.println(WiFi.localIP());

    httpFailCount = 0;

    if (USE_LCD) {
      lcd.clear();
      lcd.setCursor(0, 0);
      lcd.print("WiFi Connected");
      lcd.setCursor(0, 1);
      lcd.print(WiFi.localIP());
      delay(1000);
      currentDisplayMode = LCD_SENSOR_DATA;
    }
  } else {
    Serial.println();
    Serial.println("WiFi not connected yet. Will retry in loop, no restart.");
  }
}

// ==================== WIFI MAINTAIN ====================
void maintainWiFi() {
  if (WiFi.status() == WL_CONNECTED) {
    wifiConnecting = false;
    return;
  }

  unsigned long now = millis();

  if (wifiConnecting) {
    return;
  }

  if (now - lastReconnectTry < WIFI_RECONNECT_INTERVAL) {
    return;
  }

  lastReconnectTry = now;
  wifiConnecting = true;

  Serial.println("WiFi disconnected, safe reconnect...");

  WiFi.disconnect(false);
  delay(200);
  WiFi.mode(WIFI_STA);
  WiFi.setSleep(false);
  WiFi.begin(ssid, password);

  wifiConnecting = false;
}

// ==================== READ SENSORS ====================
void readSensors() {
  if (!USE_DHT) {
    sensorReadings.temp = 0;
    sensorReadings.hum = 0;
    sensorReadings.valid = true;
    return;
  }

  float h = dht_sensor.readHumidity();
  delay(50);
  float t = dht_sensor.readTemperature();

  if (isnan(t) || isnan(h)) {
    Serial.println("DHT read failed, keep last value");
    return;
  }

  sensorReadings.temp = t;
  sensorReadings.hum = h;
  sensorReadings.valid = true;

  Serial.printf("Temp: %.1f C | Hum: %.1f %%\n", t, h);
}

// ==================== MATIKAN SEMUA RELAY ====================
void setAllRelaysOff() {
  digitalWrite(relay1, RELAY_OFF);
  digitalWrite(relay2, RELAY_OFF);
  digitalWrite(relay3, RELAY_OFF);

  relayStates.r1 = false;
  relayStates.r2 = false;
  relayStates.r3 = false;
}

// ==================== OVER TEMPERATURE SAFETY ====================
void handleOverTemperature() {
  if (!sensorReadings.valid) {
    overTempAlarm = false;
    digitalWrite(buzzerPin, BUZZER_OFF);
    return;
  }

  if (sensorReadings.temp > TEMP_LIMIT) {
    if (!overTempAlarm) {
      Serial.println("!!! OVER TEMPERATURE DETECTED !!!");
      Serial.println("Temperature above 65 C");
      Serial.println("Relay 1 OFF, Relay 2 OFF, Relay 3 OFF, Buzzer ON");
    }

    overTempAlarm = true;

    setAllRelaysOff();
    digitalWrite(buzzerPin, BUZZER_ON);

  } else {
    if (overTempAlarm) {
      Serial.println("Temperature normal again");
      Serial.println("Buzzer OFF, relay command allowed again");
    }

    overTempAlarm = false;
    digitalWrite(buzzerPin, BUZZER_OFF);
  }
}

// ==================== HTTP CONTROL ====================
bool canDoHttp() {
  if (WiFi.status() != WL_CONNECTED) return false;
  if (httpBusy) return false;

  unsigned long now = millis();

  if (httpFailCount >= 2 && now - lastHttpFailTime < HTTP_COOLDOWN_AFTER_FAIL) {
    return false;
  }

  return true;
}

void handleHttpResult(int code) {
  if (code > 0) {
    if (httpFailCount > 0) {
      Serial.println("HTTP recovered");
    }

    httpFailCount = 0;
    return;
  }

  httpFailCount++;
  lastHttpFailTime = millis();

  Serial.printf("HTTP fail count: %d | code: %d\n", httpFailCount, code);

  if (httpFailCount >= 5) {
    Serial.println("Too many HTTP fails, reset WiFi connection safely");

    WiFi.disconnect(false);
    lastReconnectTry = 0;
    httpFailCount = 0;
  }
}

// ==================== HTTPS GET ====================
int httpGET(String url, String &response) {
  WiFiClientSecure client;

  // Untuk project skripsi / testing:
  // ini membuat ESP32 tidak perlu memasukkan sertifikat SSL manual
  client.setInsecure();

  HTTPClient http;

  http.setReuse(false);
  http.setTimeout(HTTP_TIMEOUT);
  http.setFollowRedirects(HTTPC_STRICT_FOLLOW_REDIRECTS);

  Serial.print("GET URL: ");
  Serial.println(url);

  if (!http.begin(client, url)) {
    Serial.println("HTTPS GET begin failed");
    return -99;
  }

  http.addHeader("Accept", "application/json");
  http.addHeader("Connection", "close");

  int code = http.GET();

  if (code > 0) {
    response = http.getString();

    Serial.print("GET Status: ");
    Serial.println(code);

    Serial.print("GET Response: ");
    Serial.println(response);
  } else {
    Serial.print("GET Error: ");
    Serial.println(code);
  }

  http.end();
  delay(30);
  yield();

  return code;
}

// ==================== HTTPS POST ====================
int httpPOST(String url, String payload, String &response) {
  WiFiClientSecure client;

  // Untuk HTTPS tanpa sertifikat manual
  client.setInsecure();

  HTTPClient http;

  http.setReuse(false);
  http.setTimeout(HTTP_TIMEOUT);
  http.setFollowRedirects(HTTPC_STRICT_FOLLOW_REDIRECTS);

  Serial.print("POST URL: ");
  Serial.println(url);

  Serial.print("POST Payload: ");
  Serial.println(payload);

  if (!http.begin(client, url)) {
    Serial.println("HTTPS POST begin failed");
    return -99;
  }

  http.addHeader("Content-Type", "application/json");
  http.addHeader("Accept", "application/json");
  http.addHeader("Connection", "close");

  int code = http.POST(payload);

  if (code > 0) {
    response = http.getString();

    Serial.print("POST Status: ");
    Serial.println(code);

    Serial.print("POST Response: ");
    Serial.println(response);
  } else {
    Serial.print("POST Error: ");
    Serial.println(code);
  }

  http.end();
  delay(30);
  yield();

  return code;
}

// ==================== PARSE JSON MANUAL ====================
int getJsonInt(String response, const char* key, int defaultValue) {
  String pattern = String("\"") + key + String("\":");
  int idx = response.indexOf(pattern);

  if (idx < 0) return defaultValue;

  int start = idx + pattern.length();

  while (start < response.length() && response.charAt(start) == ' ') {
    start++;
  }

  int endComma = response.indexOf(",", start);
  int endBrace = response.indexOf("}", start);

  int endPos = -1;

  if (endComma < 0) {
    endPos = endBrace;
  } else if (endBrace < 0) {
    endPos = endComma;
  } else {
    endPos = min(endComma, endBrace);
  }

  if (endPos < 0) return defaultValue;

  String value = response.substring(start, endPos);
  value.trim();

  return value.toInt();
}

bool getJsonBool(String response, const char* key, bool defaultValue) {
  String pattern = String("\"") + key + String("\":");
  int idx = response.indexOf(pattern);

  if (idx < 0) return defaultValue;

  int start = idx + pattern.length();

  while (start < response.length() && response.charAt(start) == ' ') {
    start++;
  }

  String value = response.substring(start, start + 6);
  value.trim();
  value.toLowerCase();

  if (value.startsWith("true")) return true;
  if (value.startsWith("false")) return false;
  if (value.startsWith("1")) return true;
  if (value.startsWith("0")) return false;

  return defaultValue;
}

// ==================== SEND DATA TO LARAVEL ====================
void sendSensorData() {
  if (!canDoHttp()) {
    Serial.println("Skip send data, HTTP/WiFi not ready");
    return;
  }

  if (!sensorReadings.valid) {
    Serial.println("Skip send data, no valid DHT data yet");
    return;
  }

  httpBusy = true;

  String url = String(laravelBaseUrl) + String(sensorDataEndpoint);

  String jsonPayload = "{";
  jsonPayload += "\"temp\":" + String(sensorReadings.temp, 1) + ",";
  jsonPayload += "\"hum\":" + String(sensorReadings.hum, 1) + ",";
  jsonPayload += "\"fan_state\":" + String(motorStates.fan ? "true" : "false") + ",";
  jsonPayload += "\"fan_speed\":" + String(motorStates.fanSpeed) + ",";
  jsonPayload += "\"over_temp_alarm\":" + String(overTempAlarm ? "true" : "false");
  jsonPayload += "}";

  String response = "";
  int code = httpPOST(url, jsonPayload, response);

  handleHttpResult(code);

  if (code > 0) {
    Serial.printf("Sensor data sent, status: %d\n", code);
  } else {
    Serial.printf("Send data HTTPS error: %d\n", code);
  }

  httpBusy = false;
}

// ==================== CHECK RELAY COMMANDS FROM SERVER ====================
void checkRelayCommands() {
  if (!canDoHttp()) {
    Serial.println("Skip command check, HTTP/WiFi not ready");
    return;
  }

  if (overTempAlarm) {
    Serial.println("Over temperature active, server relay command ignored");
    setAllRelaysOff();
    digitalWrite(buzzerPin, BUZZER_ON);
    return;
  }

  httpBusy = true;

  String url = String(laravelBaseUrl) + String(sensorsEndpoint);
  String response = "";

  int code = httpGET(url, response);

  handleHttpResult(code);

  if (code == 200) {
    Serial.println("Server command received");

    int newState1 = getJsonInt(response, "r1", relayStates.r1 ? 1 : 0);
    int newState2 = getJsonInt(response, "r2", relayStates.r2 ? 1 : 0);
    int newState3 = getJsonInt(response, "r3", relayStates.r3 ? 1 : 0);

    newState1 = newState1 ? 1 : 0;
    newState2 = newState2 ? 1 : 0;
    newState3 = newState3 ? 1 : 0;

    if ((bool)newState1 != relayStates.r1) {
      relayStates.r1 = newState1;
      digitalWrite(relay1, relayStates.r1 ? RELAY_ON : RELAY_OFF);
      Serial.printf("Relay 1: %s\n", relayStates.r1 ? "ON" : "OFF");
    }

    if ((bool)newState2 != relayStates.r2) {
      relayStates.r2 = newState2;
      digitalWrite(relay2, relayStates.r2 ? RELAY_ON : RELAY_OFF);
      Serial.printf("Relay 2: %s\n", relayStates.r2 ? "ON" : "OFF");
    }

    if ((bool)newState3 != relayStates.r3) {
      relayStates.r3 = newState3;
      digitalWrite(relay3, relayStates.r3 ? RELAY_ON : RELAY_OFF);
      Serial.printf("Relay 3: %s\n", relayStates.r3 ? "ON" : "OFF");
    }

    bool newFanState = getJsonBool(response, "fan_state", motorStates.fan);
    int newFanSpeed = getJsonInt(response, "fan_speed", motorStates.fanSpeed);

    if (newFanSpeed < 0) newFanSpeed = 0;
    if (newFanSpeed > 100) newFanSpeed = 100;

    if (newFanState != motorStates.fan || newFanSpeed != motorStates.fanSpeed) {
      setFanMotor(newFanState, newFanSpeed);
    }

  } else {
    Serial.printf("Check command HTTPS error/status: %d\n", code);
  }

  httpBusy = false;
}

// ==================== SEND HEARTBEAT PING ====================
void sendPing() {
  if (!canDoHttp()) {
    Serial.println("Skip ping, HTTP/WiFi not ready");
    return;
  }

  httpBusy = true;

  String url = String(laravelBaseUrl) + String(pingEndpoint);
  String response = "";

  int code = httpPOST(url, "{}", response);

  handleHttpResult(code);

  if (code > 0) {
    Serial.printf("Ping sent, status: %d\n", code);
  } else {
    Serial.printf("Ping HTTPS error: %d\n", code);
  }

  httpBusy = false;
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

      if (overTempAlarm) {
        lcd.print("TEMP HIGH BUZZER");
      } else {
        lcd.print("F:");
        lcd.print(motorStates.fan ? "ON " : "OFF");
        lcd.print(" ");
        lcd.print(motorStates.fanSpeed);
        lcd.print("%        ");
      }
      break;

    case LCD_RELAY_STATES:
      lcd.setCursor(0, 0);
      lcd.print("R1:");
      lcd.print(relayStates.r1 ? "ON " : "OFF");

      lcd.setCursor(8, 0);
      lcd.print("R2:");
      lcd.print(relayStates.r2 ? "ON " : "OFF");

      lcd.setCursor(0, 1);
      lcd.print("R3:");
      lcd.print(relayStates.r3 ? "ON " : "OFF");

      lcd.setCursor(8, 1);
      lcd.print("B:");
      lcd.print(overTempAlarm ? "ON " : "OFF");
      break;
  }
}

// ==================== MOTOR FAN ====================
void setFanMotor(bool on, int speedPercent) {
  if (speedPercent < 0) speedPercent = 0;
  if (speedPercent > 100) speedPercent = 100;

  if (on && speedPercent > 0) {
    digitalWrite(mtr2_in3, HIGH);
    digitalWrite(mtr2_in4, LOW);

    int pwmValue = map(speedPercent, 0, 100, 0, 255);
    analogWrite(fan_enb, pwmValue);

    motorStates.fan = true;
    motorStates.fanSpeed = speedPercent;

    Serial.printf("Fan: ON %d%%, PWM: %d\n", speedPercent, pwmValue);
  } else {
    digitalWrite(mtr2_in3, LOW);
    digitalWrite(mtr2_in4, LOW);
    analogWrite(fan_enb, 0);

    motorStates.fan = false;
    motorStates.fanSpeed = 0;

    Serial.println("Fan: OFF");
  }
}

// ==================== MAIN LOOP ====================
void loop() {
  unsigned long now = millis();

  maintainWiFi();

  // ==================== DEBUG ALIVE ====================
  if (now - lastAlivePrint >= 10000) {
    lastAlivePrint = now;

    Serial.print("Alive | WiFi: ");
    Serial.print(WiFi.status() == WL_CONNECTED ? "OK" : "DISCONNECTED");

    if (WiFi.status() == WL_CONNECTED) {
      Serial.print(" | IP: ");
      Serial.print(WiFi.localIP());
    }

    Serial.print(" | HTTP fail: ");
    Serial.print(httpFailCount);

    Serial.print(" | Fan: ");
    Serial.print(motorStates.fan ? "ON" : "OFF");

    Serial.print(" ");
    Serial.print(motorStates.fanSpeed);
    Serial.print("%");

    Serial.print(" | Alarm: ");
    Serial.println(overTempAlarm ? "ON" : "OFF");
  }

  // ==================== BACA SENSOR + CEK SUHU ====================
  if (now - lastDHT >= DHT_INTERVAL) {
    lastDHT = now;
    readSensors();
    handleOverTemperature();
  }

  unsigned long relayInterval = motorStates.fan ? RELAY_CHECK_INTERVAL_FAN_ON : RELAY_CHECK_INTERVAL;
  unsigned long sensorInterval = motorStates.fan ? SENSOR_SEND_INTERVAL_FAN_ON : SENSOR_SEND_INTERVAL;

  // ==================== CEK RELAY DAN MOTOR ====================
  if (now - lastRelayCheck >= relayInterval) {
    lastRelayCheck = now;
    checkRelayCommands();
  }

  // ==================== KIRIM DATA SENSOR ====================
  if (now - lastSensorSend >= sensorInterval) {
    lastSensorSend = now;
    sendSensorData();
  }

  // ==================== PING WEBSITE ====================
  if (now - lastPing >= PING_INTERVAL) {
    lastPing = now;
    sendPing();
  }

  // ==================== LCD UPDATE ====================
  if (USE_LCD && now - lastLCDUpdate >= LCD_UPDATE_INTERVAL) {
    lastLCDUpdate = now;
    updateLCD();
  }

  delay(20);
  yield();
}
