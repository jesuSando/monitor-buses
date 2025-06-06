<?php
header('Content-Type: application/json');

// --- CONFIG ---
$user = "Mel";
$pasw = "123";

include "conexion.php";

// Obtener hash para TrackerMasGPS
$consulta = "SELECT hash FROM masgps.hash WHERE user='$user' AND pasw='$pasw'";
$resultado = mysqli_query($mysqli, $consulta);
$data = mysqli_fetch_array($resultado);
$hash = $data['hash'] ?? '';

if (!$hash) {
    echo json_encode(['error' => 'No se pudo obtener hash']);
    exit;
}

// Obtener lista de trackers
$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => 'http://www.trackermasgps.com/api-v2/tracker/list',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode(['hash' => $hash]),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json']
]);
$response = curl_exec($curl);
curl_close($curl);

$trackerList = json_decode($response)->list ?? [];

$resultadoFinal = [];

foreach ($trackerList as $tracker) {
    $id = $tracker->id;
    $imei = $tracker->source->device_id;
    $plate = substr($tracker->label, 0, 7);
    
    // Obtener detalles del tracker (igual que en tu primer código)
    $trackerDetails = getTrackerDetails($hash, $id);
    
    // Armar payload XML dinámico (igual que en tu segundo código)
    $payload = '<?xml version="1.0" encoding="ISO-8859-1"?>  
<datos>
<movil>
 <pgps>wit</pgps>
 <tercero>Tandem</tercero>
 <empresa>controlsellos</empresa>
 <pat>' . htmlspecialchars($plate) . '</pat>
</movil>
<usuario xmlns="user">
 <login>wit</login>
 <clave>wit@gps-45ba</clave>
</usuario>
</datos>';

    // Construir el objeto JSON exactamente como en tu primer código
    $jsonData = [
        'id' => $id,
        'imei' => $imei,
        'patente' => $plate,
        'lat' => $trackerDetails['lat'],
        'lng' => $trackerDetails['lng'],
        'speed' => $trackerDetails['speed'],
        'direccion' => $trackerDetails['direccion'],
        'connection_status' => $trackerDetails['connection_status'],
        'signal_level' => $trackerDetails['signal_level'],
        'movement_status' => $trackerDetails['movement_status'],
        'ignicion' => $trackerDetails['ignicion'],
        'motor' => $trackerDetails['motor'],
        'odometro' => $trackerDetails['odometro'],
        'driver' => $trackerDetails['driver'],
        'ultima-conexion' => $trackerDetails['ultima-conexion']
    ];

    $resultadoFinal[] = [
        'json_data' => $jsonData, // Exactamente igual que tu primer código
        'payload_xml' => $payload, // El XML que generas en el segundo código
        'json_preview' => json_encode($jsonData, JSON_PRETTY_PRINT) // Preview del JSON real
    ];
}

echo json_encode($resultadoFinal, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

// Función para obtener detalles del tracker (idéntica a tu primer código)
function getTrackerDetails($hash, $trackerId) {
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => 'http://www.trackermasgps.com/api-v2/tracker/get_state',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['hash' => $hash, 'tracker_id' => $trackerId]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json']
    ]);
    $response = curl_exec($curl);
    curl_close($curl);
    
    $json = json_decode($response);
    
    $lat = $json->state->gps->location->lat;
    $lng = $json->state->gps->location->lng;
    $last_u = $json->state->last_update;
    $ultima_Conexion = date("d/m/Y H:i:s", strtotime($last_u));
    $speed = $json->state->gps->speed;
    $direccion = $json->state->gps->heading;
    $connection_status = $json->state->connection_status;
    $movement_status = $json->state->movement_status;
    $signal_level = $json->state->gps->signal_level;
    $ignicion = $json->state->inputs[0];
    $motor = $ignicion ? 1 : 0;
    
    // Incluir los archivos auxiliares como en tu código original
    ob_start();
    include 'odometro.php';
    $odometro = ob_get_clean();
    
    ob_start();
    include 'driver.php';
    $fullName = ob_get_clean();
    
    return [
        'lat' => $lat,
        'lng' => $lng,
        'speed' => $speed,
        'direccion' => $direccion,
        'connection_status' => $connection_status,
        'signal_level' => $signal_level,
        'movement_status' => $movement_status,
        'ignicion' => $ignicion,
        'motor' => $motor,
        'odometro' => $odometro,
        'driver' => $fullName,
        'ultima-conexion' => $ultima_Conexion
    ];
}