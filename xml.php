<?php 

$curl = curl_init();

$datos='<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:wsp="http://wspos.tracktec.cl/wspos">
<soapenv:Header/>
<soapenv:Body>
   <wsp:Post_XMLRequest>
      <!--Optional:-->
      <wsp:xmldoc><![CDATA[<?xml version="1.0" encoding="ISO-8859-1"?>  
<datos>
<movil>
 <pgps>wit</pgps>
 <tercero>Tandem</tercero>
 <empresa>controlsellos</empresa>
 <pat>'.str_replace("-", "", $plate).'</pat>
 <fn>'.$ultima_Conexion.'</fn>
 <lat>'. number_format($lat, 5).'</lat>
 <lon>'.number_format($lng, 5).'</lon>
 <ori>'.$direccion.'</ori>
 <vel>'.$speed.'</vel>
 <mot>'.$motor.'</mot>
 <hdop>'.$signal_level.'</hdop>
 <odo>'.$odometro.'</odo>
 <eve>47</eve>
 <conductor>'.$fullName.'</conductor>
 <numSAT>14</numSAT>
 <sens1>0</sens1>
 <sens2>0</sens2>
</movil>
<usuario xmlns="user">
   <login>wit</login>
   <clave>wit@gps-45ba</clave>
</usuario>
</datos>]]> 
     </wsp:xmldoc>
     </wsp:Post_XMLRequest>
 </soapenv:Body>
</soapenv:Envelope>';
$array2=array(
  CURLOPT_URL => 'https://wshub.tracktec.cl/ws/wspos.wsdl',
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => '',
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 0,
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => 'POST',
  CURLOPT_POSTFIELDS =>$datos,
  CURLOPT_HTTPHEADER => array(
    'Content-Type: text/xml'
  ),
);

curl_setopt_array($curl,$array2);
//echo 
$response = curl_exec($curl);

curl_close($curl);

$respuesta_wisetrack = $response;

//echo $datos;
//echo "<br>";
 