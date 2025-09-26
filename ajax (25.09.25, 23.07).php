<?php
require_once 'vendor/autoload.php';

use MongoDB\Client;
header('Content-Type: application/json');

// Get query parameters
$action = isset($_GET['action']) ? $_GET['action'] : '';
$queryId = isset($_GET['query_id']) ? $_GET['query_id'] : '';
$clusterId = isset($_GET['cluster_id']) ? $_GET['cluster_id'] : 'cluster1';

try {

// Connessione a MongoDB
$client = new Client('mongodb://dario:dario123@iceland.kesnet.it:31007/ctrlNods?authSource=admin&authMechanism=SCRAM-SHA-1');

//inserie switch con le diverse query
switch($action)
{
    case 'all':
        $collection = $client->ctrlNods->upload_5f5c3;
        
        // Aggregation pipeline per join con clusters
        $pipeline = [
            [
                '$lookup' => [
                    'from' => 'clusters',
                    'localField' => 'cluster',
                    'foreignField' => 'IDCLUSTER',
                    'as' => 'cluster_info'
                ]
            ],
            [
                '$addFields' => [
                    'cluster_name' => [
                        '$ifNull' => [
                            ['$arrayElemAt' => ['$cluster_info.nome', 0]],
                            'N/A'
                        ]
                    ]
                ]
            ],
            [
                '$project' => [
                    'id' => 1,
                    'sync' => 1,
                    'cluster' => 1,
                    'cluster_name' => 1,
                    'now' => 1,
                    'service' => 1,
                    'FROM_IP' => 1,
                    'FROM_MC' => 1,
                    'TO_IP' => 1,
                    'TO_MC' => 1,
                    'state' => 1,
                    'message' => 1,
                    'idpadre' => 1,
                    'durata' => 1
                ]
            ]
        ];
        
        $cursor = $collection->aggregate($pipeline);
        $result = array();
        
        foreach ($cursor as $document) {
            array_push($result, array(   
                'Node ID' => $document->id,
                'Sync' => $document->sync,
                'Cluster Name' => $document->cluster_name,  // Usa nome invece di ID
                'Now' => $document->now,
                'Service' => $document->service,
                'FROM_IP' => $document->FROM_IP,
                'FROM_MC' => $document->FROM_MC,
                'TO_IP' => $document->TO_IP,
                'TO_MC' => $document->TO_MC,
                'State' => $document->state,
                'Message' => $document->message,
                'ID Padre' => $document->idpadre,
                'Durata' => $document->durata 
            ));
        }
        
        $header = array('Node ID','Sync','Cluster Name','Now','Service','FROM_IP','FROM_MC','TO_IP','TO_MC','State','Message','ID Padre','Durata');        
        break;
}

// Esegui la query (nessun filtro: restituisce tutti i documenti)



die(json_encode(array('status'=>'success','data'=>array('headers'=>$header,'results'=>$result))));
//die(print_R($result));

} catch (Exception $exc) {
    die(json_encode(array('status'=>'error','message'=>'ERRORE')));
}