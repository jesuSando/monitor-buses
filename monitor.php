<?php

set_time_limit(1200);
header('Content-Type: application/json');

$user = "Mel";
$pasw = "123";
include "conexion.php";
$tabla = [];
$total = [];

$consulta = "SELECT hash FROM masgps.hash where user='$user' and pasw='$pasw'";
$resutaldo = mysqli_query($mysqli, $consulta);

$data = mysqli_fetch_array($resutaldo);
$hash = $data['hash'];
$cap = $hash;

$curl = curl_init();

curl_setopt_array($curl, array(
    CURLOPT_URL => 'http://www.trackermasgps.com/api-v2/tracker/list',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => '{"hash":"' . $cap . '"}',
    CURLOPT_HTTPHEADER => array(
        'Accept: application/json, text/plain, */*',
        'Accept-Language: es-419,es;q=0.9,en;q=0.8',
        'Connection: keep-alive',
        'Content-Type: application/json',
        'Cookie: _ga=GA1.2.728367267.1665672802; locale=es; _gid=GA1.2.967319985.1673009696; _gat=1; session_key=5d7875e2bf96b5966225688ddea8f098',
        'Origin: http://www.trackermasgps.com',
        'Referer: http://www.trackermasgps.com/',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/108.0.0.0 Safari/537.36'
    ),
));

$response = curl_exec($curl);
curl_close($curl);

$response_obj = json_decode($response);
$array = $response_obj->list ?? [];


//echo '[';
foreach ($array as $item) {

    $id = $item->id;
    $imei = $item->source->device_id;
    $plate = substr($item->label, 0, 7);

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'http://www.trackermasgps.com/api-v2/tracker/get_state',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => '{"hash": "' . $cap . '", "tracker_id": ' . $id . '}',
        CURLOPT_HTTPHEADER => array(
        'Content-Type: application/json'
        ),
    ));


    $response2 = curl_exec($curl);
    curl_close($curl);

    $json2 = json_decode($response2);
    //$.state.gps.location.lat

    $lat = $json2->state->gps->location->lat;
    $lng = $json2->state->gps->location->lng;
    $last_u = $json2->state->last_update;
    $ultima_Conexion = date("d/m/Y H:i:s", strtotime($last_u));
    $speed = $json2->state->gps->speed;
    $direccion = $json2->state->gps->heading;
    $connection_status = $json2->state->connection_status;
    $movement_status = $json2->state->movement_status;
    $signal_level = $json2->state->gps->signal_level;
    $ignicion = $json2->state->inputs[0];

    if ($ignicion) {
        $motor = 1;
    } else {
        $motor = 0;
    }

    ob_start();
    include 'odometro.php';
    include 'driver.php';
    ob_end_clean();

    $json = array(
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
        'driver' => $fullName,
        'ultima-conexion' => $ultima_Conexion,
    );

    $datos_json = json_encode($json, JSON_PRETTY_PRINT);
    $i++;

    ob_start();
    include "xml.php";
    ob_end_clean();

    $tabla[] = [
        'patente' => $plate,
        'xml' => htmlspecialchars($datos), // Conserva el formato XML
        'respuesta' => htmlspecialchars($respuesta_wisetrack) 
    ];

    $total[] = $json;
}
// Respuesta final
echo json_encode($tabla);
//Fin:
