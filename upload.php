<?php
require 'lib/vendor/autoload.php'; // Libreria MongoDB

use MongoDB\Client;


//6818d72fbf54b7cc5b05f5c3
//5f5c3
//TOKEN123
// curl -X POST -H "Authorization: TOKEN TOKEN123"   -F "file=@file.json"   https://iceland.kesnet.it/ctrlClus/upload.php


$mongo = new Client("mongodb://127.0.0.1:31007");
$db = $mongo->selectDatabase("ctrlNods");

// 1. AUTHORIZATION da header
$headers = getallheaders();
//if (!isset($headers['Authorization']) || !preg_match('/Bearer (.+)/', $headers['Authorization'], $matches)) {
if (!isset($headers['Authorization']) || !preg_match('/TOKEN (.+)/', $headers['Authorization'], $matches)) {
    http_response_code(401);
    echo "Token mancante o non valido.";
    exit;
}
$token = $matches[1];



// 2. VERIFICA TOKEN IN DB
$tokenDoc = $db->token->findOne(['token' => $token]);

if (!$tokenDoc || !isset($tokenDoc['idazienda'])) {
    http_response_code(403);
    echo "Accesso negato: token non riconosciuto.";
    exit;
}

$idaziendaObj = $tokenDoc['idazienda'];
$idaziendaStr = (string) $idaziendaObj;



// 3. OTTIENI ULTIME 5 CIFRE DELL'IDAZIENDA
$shortIdAzienda = substr($idaziendaStr, -5);

// 4. CONTROLLO FILE UPLOAD
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo "Errore nel file.";
    exit;
}

$jsonStr = file_get_contents($_FILES['file']['tmp_name']);
$data = json_decode($jsonStr, true);

if (!is_array($data)) {
    http_response_code(400);
    echo "JSON non valido o non è un array.";
    exit;
}

// Normalizza per array di oggetti
if (!isset($data[0]) || !is_array($data[0])) {
    $data = [$data];
}

$numCampi = count($data[0]) + 2; // +2 per eventuali campi extra

// 5. INSERISCI DOCUMENTO EVENTO
$event = [
    'sender_ip'   => $_SERVER['REMOTE_ADDR'],
    'timestamp'   => new MongoDB\BSON\UTCDateTime(),
    'version'     => $numCampi,
    'id_azienda'  => $shortIdAzienda
];

$insertedEvent = $db->events->insertOne($event);
$idupload = $insertedEvent->getInsertedId();

// 6. INSERISCI DATI SU upload_<ULTIME5>
$uploadCollectionName = 'upload_' . $shortIdAzienda;
$uploadCollection = $db->$uploadCollectionName;

foreach ($data as &$record) {
    $record['idupload'] = $idupload;
}

$result = $uploadCollection->insertMany($data);

echo $result->getInsertedCount() . " documenti su '$uploadCollectionName'.\n";
