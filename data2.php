<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

set_time_limit(1200);

// Datos de autenticación
$user = "Mel";
$pasw = "123";

include "login/conexion.php";

// Obtener hash de autenticación
$consulta = "SELECT hash FROM masgps.hash WHERE user='$user' AND pasw='$pasw'";
$resultado = mysqli_query($mysqli, $consulta);
$data = mysqli_fetch_array($resultado);
$hash = $data['hash'] ?? '';

if (empty($hash)) {
    http_response_code(401);
    echo json_encode(['error' => 'Autenticación fallida']);
    exit;
}

// Función para hacer peticiones a la API de TrackerMasGPS
function callTrackerAPI($url, $postData = null) {
    $curl = curl_init();
    $options = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Content-Type: application/json'
        ]
    ];
    
    if ($postData) {
        $options[CURLOPT_CUSTOMREQUEST] = 'POST';
        $options[CURLOPT_POSTFIELDS] = $postData;
    }
    
    curl_setopt_array($curl, $options);
    $response = curl_exec($curl);
    curl_close($curl);
    
    return $response;
}

// Obtener lista de trackers
$trackerList = json_decode(callTrackerAPI(
    'http://www.trackermasgps.com/api-v2/tracker/list',
    '{"hash":"' . $hash . '"}'
));

if (empty($trackerList->list)) {
    http_response_code(404);
    echo json_encode(['error' => 'No se encontraron trackers']);
    exit;
}

// Función para simular la respuesta de Wisetrack
function getWisetrackResponse($vehicleData) {
    return [
        'status' => 'success',
        'timestamp' => date('Y-m-d H:i:s'),
        'data' => [
            'plate' => $vehicleData['patente'] ?? '',
            'imei' => $vehicleData['imei'] ?? '',
            'location' => [
                'lat' => $vehicleData['lat'] ?? 0,
                'lng' => $vehicleData['lng'] ?? 0
            ],
            'speed' => $vehicleData['speed'] ?? 0,
            'status' => [
                'ignition' => $vehicleData['ignicion'] ?? false,
                'moving' => ($vehicleData['movement_status'] ?? '') === 'moving'
            ],
            'metadata' => [
                'last_update' => $vehicleData['ultima-conexion'] ?? ''
            ]
        ]
    ];
}

$resultados = [];

foreach ($trackerList->list as $item) {
    // Obtener estado del tracker
    $trackerState = json_decode(callTrackerAPI(
        'http://www.trackermasgps.com/api-v2/tracker/get_state',
        '{"hash": "' . $hash . '", "tracker_id": ' . $item->id . '}'
    ));
    
    if (empty($trackerState->state)) continue;
    
    // Procesar datos del tracker
    $state = $trackerState->state;
    $lastUpdate = date("d/m/Y H:i:s", strtotime($state->last_update));
    
    include 'odometro.php';
    include 'driver.php';
    
    $vehicleData = [
        'id' => $item->id,
        'imei' => $item->source->device_id,
        'patente' => substr($item->label, 0, 7),
        'lat' => $state->gps->location->lat ?? 0,
        'lng' => $state->gps->location->lng ?? 0,
        'speed' => $state->gps->speed ?? 0,
        'direccion' => $state->gps->heading ?? 0,
        'connection_status' => $state->connection_status ?? 'unknown',
        'signal_level' => $state->gps->signal_level ?? 0,
        'movement_status' => $state->movement_status ?? 'unknown',
        'ignicion' => $state->inputs[0] ?? false,
        'motor' => ($state->inputs[0] ?? false) ? 1 : 0,
        'odometro' => $odometro ?? 0,
        'driver' => $fullName ?? '',
        'ultima-conexion' => $lastUpdate
    ];
    
    // Simular respuesta de Wisetrack (aquí deberías integrar tu lógica real)
    $wisetrackResponse = getWisetrackResponse($vehicleData);
    
    $resultados[] = [
        'patente' => $vehicleData['patente'],
        'tracker_data' => $vehicleData,
        'wisetrack_response' => $wisetrackResponse
    ];
}

// Enviar respuesta JSON
http_response_code(200);
echo json_encode($resultados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>