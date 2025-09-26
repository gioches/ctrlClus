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

// Auto-popola collection clusters
$clusterInfo = extractClusterInfo($data, $shortIdAzienda, $idazienda);
upsertClusterInfo($db, $clusterInfo);

// 6. INSERISCI DATI SU upload_<ULTIME5>
$uploadCollectionName = 'upload_' . $shortIdAzienda;
$uploadCollection = $db->$uploadCollectionName;

// 1. Assicura indice unico esista
ensureUniqueIndex($uploadCollection, $uploadCollectionName);

foreach ($data as &$record) {
    $record['idupload'] = $idupload;
}

// 2. Insert con gestione duplicati MongoDB
$insertResult = insertWithDuplicateHandling($uploadCollection, $data, 'CURL_upload');

echo $insertResult['inserted'] . " nuovi documenti inseriti su '$uploadCollectionName'.\n";
if ($insertResult['duplicates'] > 0) {
    echo $insertResult['duplicates'] . " duplicati ignorati.\n";
}
if ($insertResult['errors'] > 0) {
    echo "ATTENZIONE: " . $insertResult['errors'] . " errori durante inserimento.\n";
}

// Include le stesse funzioni helper di upload_FILE.php
function extractClusterInfo($data, $shortIdAzienda, $fullIdAzienda) {
    $ambiente = determineEnvironment($data);
    $clusterId = extractClusterId($data, $shortIdAzienda);
    $nomeCluster = generateClusterName($data, $ambiente, $shortIdAzienda);
    
    return [
        'IDCLUSTER' => $clusterId,
        'idazienda' => $fullIdAzienda,
        'ambiente' => $ambiente,
        'nome' => $nomeCluster,
        'note' => 'Auto-generato durante import CURL ' . date('Y-m-d H:i:s'),
        'last_update' => new MongoDB\BSON\UTCDateTime(),
        'upload_collection' => 'upload_' . $shortIdAzienda
    ];
}

function determineEnvironment($data) {
    $sampleData = array_slice($data, 0, 5);
    
    foreach ($sampleData as $record) {
        if (isset($record['FROM_IP'])) {
            $ip = $record['FROM_IP'];
            if (strpos($ip, '192.168.1.') === 0) return 'prod';
            if (strpos($ip, '192.168.2.') === 0) return 'svil';
            if (strpos($ip, '192.168.3.') === 0) return 'test';
            if (strpos($ip, '192.168.4.') === 0) return 'perf';
        }
        
        if (isset($record['service'])) {
            $service = strtolower($record['service']);
            if (strpos($service, 'prod') !== false) return 'prod';
            if (strpos($service, 'dev') !== false || strpos($service, 'svil') !== false) return 'svil';
            if (strpos($service, 'test') !== false) return 'test';
            if (strpos($service, 'perf') !== false) return 'perf';
        }
        
        if (isset($record['cluster'])) {
            $cluster = strtolower($record['cluster']);
            if (strpos($cluster, 'prod') !== false) return 'prod';
            if (strpos($cluster, 'dev') !== false || strpos($cluster, 'svil') !== false) return 'svil';
            if (strpos($cluster, 'test') !== false) return 'test';
            if (strpos($cluster, 'perf') !== false) return 'perf';
        }
    }
    
    return 'prod';
}

function extractClusterId($data, $shortIdAzienda) {
    foreach ($data as $record) {
        if (isset($record['cluster']) && !empty($record['cluster'])) {
            return $record['cluster'];
        }
    }
    return 'cluster_' . $shortIdAzienda;
}

function generateClusterName($data, $ambiente, $shortIdAzienda) {
    $services = [];
    foreach (array_slice($data, 0, 10) as $record) {
        if (isset($record['service']) && !in_array($record['service'], $services)) {
            $services[] = $record['service'];
        }
    }
    
    $serviceName = !empty($services) ? $services[0] : 'Generic';
    return ucfirst($ambiente) . ' - ' . $serviceName . ' (' . $shortIdAzienda . ')';
}

function upsertClusterInfo($db, $clusterInfo) {
    $clustersCollection = $db->clusters;
    
    $existingCluster = $clustersCollection->findOne([
        'IDCLUSTER' => $clusterInfo['IDCLUSTER'],
        'idazienda' => $clusterInfo['idazienda']
    ]);
    
    if ($existingCluster) {
        $clustersCollection->updateOne(
            [
                'IDCLUSTER' => $clusterInfo['IDCLUSTER'],
                'idazienda' => $clusterInfo['idazienda']
            ],
            [
                '$set' => [
                    'last_update' => $clusterInfo['last_update'],
                    'note' => $clusterInfo['note'] . ' (aggiornato)',
                    'upload_collection' => $clusterInfo['upload_collection']
                ]
            ]
        );
    } else {
        $clustersCollection->insertOne($clusterInfo);
    }
}

/**
 * Assicura che l'indice unico esista per la collection
 */
function ensureUniqueIndex($collection, $collectionName) {
    try {
        $collection->createIndex(
            ['id' => 1, 'now' => 1, 'service' => 1, 'FROM_IP' => 1],
            ['unique' => true, 'name' => 'unique_node_time_service_ip', 'background' => true, 'sparse' => true]
        );
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Inserisce documenti gestendo duplicati tramite MongoDB
 */
function insertWithDuplicateHandling($collection, $data, $filename = '') {
    $result = ['inserted' => 0, 'duplicates' => 0, 'errors' => 0];
    
    try {
        $insertResult = $collection->insertMany($data, ['ordered' => false]);
        $result['inserted'] = $insertResult->getInsertedCount();
        
    } catch (MongoDB\Driver\Exception\BulkWriteException $e) {
        $writeResult = $e->getWriteResult();
        $result['inserted'] = $writeResult->getInsertedCount();
        
        foreach ($writeResult->getWriteErrors() as $error) {
            if ($error->getCode() === 11000) {
                $result['duplicates']++;
            } else {
                $result['errors']++;
                error_log("Upload error in $filename: Code {$error->getCode()} - {$error->getMessage()}");
            }
        }
        
    } catch (Exception $e) {
        $result['errors'] = count($data);
        error_log("Fatal upload error in $filename: {$e->getMessage()}");
    }
    
    return $result;
}
