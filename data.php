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

    $details = getTrackerDetails($hash, $id);
    extract($details); // extrae lat, lng, speed, direccion, etc.

    // Construir XML SOAP para enviar a TrackTec
    $xmlInterno = '<?xml version="1.0" encoding="ISO-8859-1"?>  
<datos>
<movil>
 <pgps>wit</pgps>
 <tercero>Tandem</tercero>
 <empresa>controlsellos</empresa>
 <pat>' . str_replace("-", "", $plate) . '</pat>
 <fn>' . $ultima_conexion . '</fn>
 <lat>' . number_format($lat, 5) . '</lat>
 <lon>' . number_format($lng, 5) . '</lon>
 <ori>' . $direccion . '</ori>
 <vel>' . $speed . '</vel>
 <mot>' . $motor . '</mot>
 <hdop>' . $signal_level . '</hdop>
 <odo>' . $odometro . '</odo>
 <eve>47</eve>
 <conductor>' . $driver . '</conductor>
 <numSAT>14</numSAT>
 <sens1>0</sens1>
 <sens2>0</sens2>
</movil>
<usuario xmlns="user">
   <login>wit</login>
   <clave>wit@gps-45ba</clave>
</usuario>
</datos>';

    $xmlSoap = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:wsp="http://wspos.tracktec.cl/wspos">
<soapenv:Header/>
<soapenv:Body>
   <wsp:Post_XMLRequest>
      <wsp:xmldoc><![CDATA[' . $xmlInterno . ']]></wsp:xmldoc>
   </wsp:Post_XMLRequest>
</soapenv:Body>
</soapenv:Envelope>';

    // Enviar XML SOAP a TrackTec
    $soapCurl = curl_init();
    curl_setopt_array($soapCurl, [
        CURLOPT_URL => 'https://wshub.tracktec.cl/ws/wspos.wsdl',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $xmlSoap,
        CURLOPT_HTTPHEADER => ['Content-Type: text/xml']
    ]);
    $soapResponse = curl_exec($soapCurl);
    curl_close($soapCurl);

    // JSON con todos los datos
    $jsonData = [
        'id' => $id,
        'imei' => $imei,
        'patente' => $plate,
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
        'driver' => $driver,
        'ultima-conexion' => $ultima_conexion
    ];

    $resultadoFinal[] = [
        'patente' => $plate,
        'json_enviado' => $jsonData,
        'xml_enviado' => $xmlSoap,
        'respuesta_soap' => $soapResponse
    ];
}

echo json_encode($resultadoFinal, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

// --- FUNCIONES ---
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
    $lat = $json->state->gps->location->lat ?? 0;
    $lng = $json->state->gps->location->lng ?? 0;
    $last_u = $json->state->last_update ?? '';
    $ultima_conexion = date("d/m/Y H:i:s", strtotime($last_u));
    $speed = $json->state->gps->speed ?? 0;
    $direccion = $json->state->gps->heading ?? 0;
    $connection_status = $json->state->connection_status ?? '';
    $movement_status = $json->state->movement_status ?? '';
    $signal_level = $json->state->gps->signal_level ?? 0;
    $ignicion = $json->state->inputs[0] ?? 0;
    $motor = $ignicion ? 1 : 0;

    ob_start();
    include 'odometro.php';
    $odometro = ob_get_clean();

    ob_start();
    include 'driver.php';
    $driver = ob_get_clean();

    return compact(
        'lat', 'lng', 'speed', 'direccion', 'connection_status',
        'movement_status', 'signal_level', 'ignicion', 'motor',
        'odometro', 'driver', 'ultima_conexion'
    );
}
