<?php
/**
 * Script per creare cluster di test per verificare la pagina di gestione
 */
require 'lib/vendor/autoload.php';
use MongoDB\Client;

// Connessione MongoDB
$mongo = new Client('mongodb://dario:dario123@iceland.kesnet.it:31007/ctrlNods?authSource=admin&authMechanism=SCRAM-SHA-1');
$db = $mongo->selectDatabase("ctrlNods");

echo "=== CREAZIONE CLUSTERS DI TEST ===\n";

// Crea alcuni clusters di esempio per testare l'interfaccia di gestione
$testClusters = [
    [
        'IDCLUSTER' => 'test-cluster-001',
        'idazienda' => '123456789abcdef12345test1',
        'ambiente' => 'test',
        'nome' => 'Test - Web Frontend (test1)',
        'note' => 'Cluster di test per frontend web - creato automaticamente',
        'last_update' => new MongoDB\BSON\UTCDateTime(),
        'upload_collection' => 'upload_test1'
    ],
    [
        'IDCLUSTER' => 'prod-cluster-002',
        'idazienda' => '987654321fedcba09876prod2',
        'ambiente' => 'prod',
        'nome' => 'Produzione - Database Services (prod2)',
        'note' => 'Cluster di produzione per servizi database - creato automaticamente',
        'last_update' => new MongoDB\BSON\UTCDateTime(),
        'upload_collection' => 'upload_prod2'
    ],
    [
        'IDCLUSTER' => 'svil-cluster-003',
        'idazienda' => '456789012bcdef345svil3',
        'ambiente' => 'svil',
        'nome' => 'Sviluppo - API Gateway (svil3)',
        'note' => 'Cluster di sviluppo per gateway API - creato automaticamente',
        'last_update' => new MongoDB\BSON\UTCDateTime(),
        'upload_collection' => 'upload_svil3'
    ],
    [
        'IDCLUSTER' => 'perf-cluster-004',
        'idazienda' => '789012345cdef678perf4',
        'ambiente' => 'perf',
        'nome' => 'Performance - Load Testing (perf4)',
        'note' => 'Cluster per test di performance e carico - creato automaticamente',
        'last_update' => new MongoDB\BSON\UTCDateTime(),
        'upload_collection' => 'upload_perf4'
    ]
];

$clustersCollection = $db->clusters;

foreach ($testClusters as $cluster) {
    // Verifica se cluster esiste già
    $existingCluster = $clustersCollection->findOne([
        'IDCLUSTER' => $cluster['IDCLUSTER'],
        'idazienda' => $cluster['idazienda']
    ]);
    
    if ($existingCluster) {
        echo "Cluster {$cluster['IDCLUSTER']} già esistente - aggiornamento timestamp\n";
        $clustersCollection->updateOne(
            [
                'IDCLUSTER' => $cluster['IDCLUSTER'],
                'idazienda' => $cluster['idazienda']
            ],
            [
                '$set' => [
                    'last_update' => new MongoDB\BSON\UTCDateTime()
                ]
            ]
        );
    } else {
        echo "Creazione cluster {$cluster['IDCLUSTER']} - ambiente: {$cluster['ambiente']}\n";
        $result = $clustersCollection->insertOne($cluster);
        if ($result->getInsertedCount() > 0) {
            echo "  ✓ Cluster creato con successo\n";
        } else {
            echo "  ✗ Errore nella creazione del cluster\n";
        }
    }
}

// Verifica clusters creati
echo "\n=== VERIFICA CLUSTERS CREATI ===\n";
$allClusters = $clustersCollection->find();
$count = 0;

foreach ($allClusters as $cluster) {
    $count++;
    echo "Cluster $count:\n";
    echo "  IDCLUSTER: {$cluster->IDCLUSTER}\n";
    echo "  Ambiente: {$cluster->ambiente}\n";
    echo "  Nome: {$cluster->nome}\n";
    echo "  ID Azienda (ultimi 8): " . substr($cluster->idazienda, -8) . "\n";
    echo "  Collection: {$cluster->upload_collection}\n";
    echo "  ----------------\n";
}

echo "\nTotale clusters: $count\n";
echo "\n=== TEST COMPLETATO ===\n";
echo "Ora puoi accedere a manage_clusters.php per testare l'interfaccia di gestione\n";
?>