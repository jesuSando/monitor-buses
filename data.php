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
    $plate = strtoupper(substr($tracker->label, 0, 7));
    
    // Obtener datos completos del tracker (como en tu primer código)
    $trackerDetails = getTrackerDetails($hash, $tracker->id);
    
    // Armar payload XML dinámico con patente real
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

    $resultadoFinal[] = [
        'id' => $tracker->id,
        'imei' => $tracker->source->device_id,
        'patente' => $plate,
        'lat' => $trackerDetails['lat'] ?? null,
        'lng' => $trackerDetails['lng'] ?? null,
        'speed' => $trackerDetails['speed'] ?? null,
        'direccion' => $trackerDetails['heading'] ?? null,
        'connection_status' => $trackerDetails['connection_status'] ?? null,
        'movement_status' => $trackerDetails['movement_status'] ?? null,
        'ignicion' => $trackerDetails['ignicion'] ?? null,
        'payload_xml' => $payload,
        'json_preview' => [
            'id' => $tracker->id,
            'imei' => $tracker->source->device_id,
            'patente' => $plate,
            'ubicacion' => [
                'lat' => $trackerDetails['lat'] ?? null,
                'lng' => $trackerDetails['lng'] ?? null
            ],
            'estado' => [
                'velocidad' => $trackerDetails['speed'] ?? null,
                'direccion' => $trackerDetails['heading'] ?? null,
                'conexion' => $trackerDetails['connection_status'] ?? null,
                'movimiento' => $trackerDetails['movement_status'] ?? null,
                'ignicion' => $trackerDetails['ignicion'] ?? null
            ]
        ]
    ];
}

echo json_encode($resultadoFinal, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

// Función para obtener detalles del tracker (similar a tu primer código)
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
    
    $data = json_decode($response, true);
    
    return [
        'lat' => $data['state']['gps']['location']['lat'] ?? null,
        'lng' => $data['state']['gps']['location']['lng'] ?? null,
        'speed' => $data['state']['gps']['speed'] ?? null,
        'heading' => $data['state']['gps']['heading'] ?? null,
        'connection_status' => $data['state']['connection_status'] ?? null,
        'movement_status' => $data['state']['movement_status'] ?? null,
        'ignicion' => $data['state']['inputs'][0] ?? null
    ];
}