const WebSocket = require('ws');
const express = require('express');
const cors = require('cors');
const http = require('http');

const app = express();
app.use(cors());
app.use(express.json());

const server = http.createServer(app);
const wss = new WebSocket.Server({ server });

let esp32Connected = false;
let relayStates = { r1: 0, r2: 0, r3: 0, r4: 0 };
let sensorData = { temp: 0, hum: 0 };
let esp32Client = null;

function sendRelayState() {
  const message = JSON.stringify({
    type: 'relay_status',
    r1: relayStates.r1,
    r2: relayStates.r2,
    r3: relayStates.r3,
    r4: relayStates.r4
  });
  
  if (esp32Client && esp32Client.readyState === WebSocket.OPEN) {
    esp32Client.send(message);
    console.log('Sent relay state to ESP32:', message);
  }
}

app.post('/api/relay', (req, res) => {
  const { r1, r2, r3, r4 } = req.body;
  if (r1 !== undefined) relayStates.r1 = r1 ? 1 : 0;
  if (r2 !== undefined) relayStates.r2 = r2 ? 1 : 0;
  if (r3 !== undefined) relayStates.r3 = r3 ? 1 : 0;
  if (r4 !== undefined) relayStates.r4 = r4 ? 1 : 0;
  
  console.log('Relay states updated:', relayStates);
  
  sendRelayState();
  
  res.json({ success: true, relayStates });
});

app.get('/api/sensors', (req, res) => {
  res.json({ connected: esp32Connected, sensorData, relayStates });
});

wss.on('connection', (ws, req) => {
  const clientIP = req.socket.remoteAddress;
  console.log('New client connection from:', clientIP);
  
  const isESP32 = clientIP.startsWith('192.168.');
  console.log('Is ESP32:', isESP32);
  
  if (isESP32) {
    esp32Client = ws;
    esp32Connected = true;
    console.log('ESP32 connected, sending current relay state');
    sendRelayState();
  }

  ws.on('message', (message) => {
    try {
      const data = JSON.parse(message.toString());
      console.log('Received from', clientIP + ':', message.toString());
      
      if (data.type === 'sensor_data' && isESP32) {
        sensorData = { temp: data.temp, hum: data.hum };
        console.log(`Sensor: Temp ${data.temp}C, Hum ${data.hum}%`);
        ws.send(JSON.stringify({ type: 'ack', received: true }));
      }
    } catch (e) {
      console.log('Received:', message.toString());
    }
  });
  
  ws.on('close', () => {
    console.log('Client disconnected:', clientIP);
    if (isESP32) {
      esp32Connected = false;
      esp32Client = null;
    }
  });

  ws.on('error', (err) => {
    console.error('WebSocket error from', clientIP + ':', err.message);
  });
});

const PORT = 6001;
server.listen(PORT, '0.0.0.0', () => {
  console.log(`WebSocket server running on port ${PORT}`);
});

process.on('uncaughtException', (err) => {
  console.error('Error:', err.message);
  process.exit(1);
});