<?php
/**
 * Script per aggiornare la chiave unica aggiungendo FROM_IP
 * Nuova chiave: id + now + service + FROM_IP
 */
require 'lib/vendor/autoload.php';
use MongoDB\Client;

// Connessione MongoDB
$mongo = new Client('mongodb://dario:dario123@iceland.kesnet.it:31007/ctrlNods?authSource=admin&authMechanism=SCRAM-SHA-1');
$db = $mongo->selectDatabase("ctrlNods");

echo "=== AGGIORNAMENTO CHIAVE UNICA CON FROM_IP ===\n";

/**
 * Aggiorna indice unico per una collection
 */
function updateUniqueIndex($collection, $collectionName) {
    echo "\nProcessando collection: $collectionName\n";
    
    try {
        // 1. Rimuovi indice vecchio se esiste
        $indexes = $collection->listIndexes();
        
        foreach ($indexes as $index) {
            if ($index['name'] === 'unique_node_time_service') {
                echo "  🗑 Rimuovendo indice vecchio: unique_node_time_service\n";
                $collection->dropIndex('unique_node_time_service');
                break;
            }
        }
        
        // 2. Crea nuovo indice con FROM_IP
        $result = $collection->createIndex(
            [
                'id' => 1,
                'now' => 1,
                'service' => 1,
                'FROM_IP' => 1  // NUOVA AGGIUNTA
            ],
            [
                'unique' => true,
                'name' => 'unique_node_time_service_ip',
                'background' => true,
                'sparse' => true
            ]
        );
        
        echo "  ✓ Nuovo indice creato: unique_node_time_service_ip\n";
        echo "  ✓ Chiave: id + now + service + FROM_IP\n";
        return true;
        
    } catch (Exception $e) {
        echo "  ✗ Errore aggiornamento indice: " . $e->getMessage() . "\n";
        
        // Se errore per duplicati esistenti con nuova chiave
        if (strpos($e->getMessage(), 'duplicate key') !== false) {
            echo "  ⚠ Duplicati esistenti con nuova chiave - analisi necessaria\n";
            analyzeDuplicatesWithFromIP($collection, $collectionName);
        }
        
        return false;
    }
}

/**
 * Analizza duplicati con la nuova chiave che include FROM_IP
 */
function analyzeDuplicatesWithFromIP($collection, $collectionName) {
    echo "  📊 Analisi duplicati con chiave id+now+service+FROM_IP...\n";
    
    $pipeline = [
        [
            '$group' => [
                '_id' => [
                    'id' => '$id',
                    'now' => '$now',
                    'service' => '$service',
                    'FROM_IP' => '$FROM_IP'
                ],
                'count' => ['$sum' => 1],
                'docs' => ['$push' => '$_id']
            ]
        ],
        [
            '$match' => [
                'count' => ['$gt' => 1]
            ]
        ],
        [
            '$limit' => 3
        ]
    ];
    
    $duplicates = $collection->aggregate($pipeline);
    $duplicateCount = 0;
    
    foreach ($duplicates as $duplicate) {
        $duplicateCount++;
        echo "    Duplicato $duplicateCount:\n";
        echo "      id: {$duplicate->_id->id}\n";
        echo "      now: {$duplicate->_id->now}\n";
        echo "      service: {$duplicate->_id->service}\n";
        echo "      FROM_IP: {$duplicate->_id->FROM_IP}\n";
        echo "      Occorrenze: {$duplicate->count}\n";
        echo "      --------\n";
    }
    
    if ($duplicateCount === 0) {
        echo "    ✓ Nessun duplicato con nuova chiave\n";
    } else {
        echo "    ⚠ Trovati $duplicateCount gruppi duplicati\n";
        echo "    💡 Suggerimento: Pulire duplicati manualmente prima di applicare indice\n";
    }
}

// Trova collection upload esistenti
echo "Ricerca collection upload esistenti...\n";
$collections = $db->listCollections();
$uploadCollections = [];

foreach ($collections as $collectionInfo) {
    $name = $collectionInfo->getName();
    if (strpos($name, 'upload_') === 0) {
        $uploadCollections[] = $name;
    }
}

echo "Collection upload trovate: " . count($uploadCollections) . "\n";
foreach ($uploadCollections as $collName) {
    echo "  - $collName\n";
}

if (empty($uploadCollections)) {
    echo "⚠ Nessuna collection upload trovata\n";
} else {
    // Aggiorna indici per tutte le collection esistenti
    foreach ($uploadCollections as $collName) {
        $collection = $db->selectCollection($collName);
        updateUniqueIndex($collection, $collName);
    }
}

echo "\n=== NUOVA FUNZIONE ensureUniqueIndex ===\n";
echo "Aggiornare upload_FILE.php e upload_CURL.php con:\n\n";

echo "function ensureUniqueIndex(\$collection, \$collectionName) {\n";
echo "    try {\n";
echo "        \$collection->createIndex(\n";
echo "            ['id' => 1, 'now' => 1, 'service' => 1, 'FROM_IP' => 1],\n";
echo "            ['unique' => true, 'name' => 'unique_node_time_service_ip', 'background' => true, 'sparse' => true]\n";
echo "        );\n";
echo "        return true;\n";
echo "    } catch (Exception \$e) {\n";
echo "        return false;\n";
echo "    }\n";
echo "}\n";

echo "\n=== VANTAGGI NUOVA CHIAVE ===\n";
echo "✅ id + now + service + FROM_IP\n";
echo "✅ Supporta filtro cluster per FROM_IP\n";  
echo "✅ Più granulare: stesso nodo può avere IP diversi\n";
echo "✅ Allineato con logica filtro interfaccia utente\n";
echo "✅ Compatibile con query esistenti\n";

echo "\n=== PROSSIMI PASSI ===\n";
echo "1. Aggiornare funzioni helper nei file upload\n";
echo "2. Testare con dati che hanno FROM_IP diversi\n";
echo "3. Verificare filtro cluster nell'interfaccia\n";

echo "\n=== AGGIORNAMENTO COMPLETATO ===\n";
?>