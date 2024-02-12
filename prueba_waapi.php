<?php

$ChatId = "5493404540440@c.us";
$Url = "https://waapi.app/android-chrome-192x192.png";
$Caption =  "Texto de Imagen";
$key = "xdbALXm6ryHDhhTfdvj89LktscpEz0GiB4O7SPKV1cf11ac1";
$instance = "5473";

$body_json = json_encode([
  "chatId" => $ChatId,
  "mediaUrl" => $Url,
  "mediaCaption" => $Caption
]);

require_once('../../vendor/autoload.php');

$client = new \GuzzleHttp\Client();

$response = $client->request(
  'POST',
  'https://waapi.app/api/v1/instances/' . $instance . '/client/action/send-media',
  [
    'body' => $body_json,
    'headers' => [
      'accept' => 'application/json',
      'content-type' => 'application/json',
      'authorization' => 'Bearer ' .$key,
    ],
  ]
);

/*
require_once('../../vendor/autoload.php');
$ChatId = "5493404540440@c.us";
$key = "xdbALXm6ryHDhhTfdvj89LktscpEz0GiB4O7SPKV1cf11ac1";
$instance = "5213";
$Url = "https://hcd.origen.ar/modules/sms_email_reminder/logo_wsp.png";
$Capion = "Prueba nueva";
// Enviar Imagen
$client = new \GuzzleHttp\Client();

$data=array(
'body'=>json_encode(array('chatId'=>$ChatId,
'mediaUrl'=>$Url,
'mediaCaption'=>$Caption)),
'headers'=>array(
'accept'=>'application/json',
'authorization' => 'Bearer ' .$key,
'content-type' => 'application/json')); 
$response = $client->request('POST',  'https://waapi.app/api/v1/instances/' . $instance . '/client/action/send-media',$data);
*/

/*
$response = $client->request('POST', 'https://waapi.app/api/v1/instances/' . $instance . '/client/action/send-media', [
  'body' => '{
	  "chatId":"5493404540440@c.us",
	  "mediaUrl":"https://hcd.origen.ar/modules/sms_email_reminder/logo_wsp.png",
	  "mediaCaption":"Esto es una imagen 3"
	  }',
  'headers' => [
    'accept' => 'application/json',
    'authorization' => 'Bearer ' .$key,
    'content-type' => 'application/json',
  ],
]);
*/

echo $response->getBody();

/*
// Enviar Mensajes
$client = new \GuzzleHttp\Client();

$response = $client->request('POST', 'https://waapi.app/api/v1/instances/5213/client/action/send-message', [
  'body' => '{"chatId":"5493404540440@c.us","message":"Mensaje 3"}',
  'headers' => [
    'accept' => 'application/json',
    'authorization' => 'Bearer xdbALXm6ryHDhhTfdvj89LktscpEz0GiB4O7SPKV1cf11ac1',
    'content-type' => 'application/json',
  ],
]);

echo $response->getBody();

// Enviar archivo

$client = new \GuzzleHttp\Client();

$response = $client->request('POST', 'https://waapi.app/api/v1/instances/5213/client/action/send-media', [
  'body' => '{"mediaUrl":"https://hcd.origen.ar/modules/sms_email_reminder/ical.ics","mediaCaption":": Presione en el adjunto para verificar su turno. Gracias.","chatId":"5493404540440@c.us"}',
  'headers' => [
    'accept' => 'application/json',
    'authorization' => 'Bearer xdbALXm6ryHDhhTfdvj89LktscpEz0GiB4O7SPKV1cf11ac1',
    'content-type' => 'application/json',
  ],
]);

echo $response->getBody();
*/