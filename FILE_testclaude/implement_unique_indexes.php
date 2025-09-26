<?php
/**
 * Script per implementare indici unici nelle collection upload
 * per prevenire duplicati durante import
 */
require 'lib/vendor/autoload.php';
use MongoDB\Client;

// Connessione MongoDB
$mongo = new Client('mongodb://dario:dario123@iceland.kesnet.it:31007/ctrlNods?authSource=admin&authMechanism=SCRAM-SHA-1');
$db = $mongo->selectDatabase("ctrlNods");

echo "=== IMPLEMENTAZIONE INDICI UNICI ===\n";

/**
 * Crea indice unico per una collection
 */
function createUniqueIndex($collection, $collectionName) {
    echo "\nProcessando collection: $collectionName\n";
    
    try {
        // Verifica se indice esiste già
        $indexes = $collection->listIndexes();
        $indexExists = false;
        
        foreach ($indexes as $index) {
            if ($index['name'] === 'unique_node_time_service') {
                $indexExists = true;
                break;
            }
        }
        
        if ($indexExists) {
            echo "  ✓ Indice unico già esistente\n";
            return true;
        }
        
        // Crea indice unico: id + now + service
        $result = $collection->createIndex(
            [
                'id' => 1,
                'now' => 1, 
                'service' => 1
            ],
            [
                'unique' => true,
                'name' => 'unique_node_time_service',
                'background' => true,  // Creazione non-bloccante
                'sparse' => true       // Ignora documenti senza questi campi
            ]
        );
        
        echo "  ✓ Indice unico creato: $result\n";
        return true;
        
    } catch (Exception $e) {
        echo "  ✗ Errore creazione indice: " . $e->getMessage() . "\n";
        
        // Se errore per duplicati esistenti, mostra dettagli
        if (strpos($e->getMessage(), 'duplicate key') !== false) {
            echo "  ⚠ Duplicati esistenti trovati - richiede pulizia manuale\n";
            findDuplicates($collection, $collectionName);
        }
        
        return false;
    }
}

/**
 * Trova e mostra duplicati esistenti
 */
function findDuplicates($collection, $collectionName) {
    echo "  📊 Ricerca duplicati esistenti...\n";
    
    $pipeline = [
        [
            '$group' => [
                '_id' => [
                    'id' => '$id',
                    'now' => '$now',
                    'service' => '$service'
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
            '$limit' => 5  // Solo primi 5 per esempio
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
        echo "      Occorrenze: {$duplicate->count}\n";
        echo "      Document IDs: " . implode(', ', array_map('strval', $duplicate->docs)) . "\n";
        echo "      --------\n";
    }
    
    if ($duplicateCount === 0) {
        echo "    ✓ Nessun duplicato trovato\n";
    }
}

// Trova tutte le collection upload esistenti
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
    echo "Gli indici verranno creati automaticamente al primo import\n";
} else {
    // Applica indici a tutte le collection esistenti
    foreach ($uploadCollections as $collName) {
        $collection = $db->selectCollection($collName);
        createUniqueIndex($collection, $collName);
    }
}

echo "\n=== FUNCTION HELPER PER UPLOAD_FILE.php ===\n";
echo "Aggiungere questa funzione per gestire collection future:\n\n";

echo "function ensureUniqueIndex(\$collection, \$collectionName) {\n";
echo "    try {\n";
echo "        \$collection->createIndex(\n";
echo "            ['id' => 1, 'now' => 1, 'service' => 1],\n";
echo "            ['unique' => true, 'name' => 'unique_node_time_service', 'background' => true, 'sparse' => true]\n";
echo "        );\n";
echo "        return true;\n";
echo "    } catch (Exception \$e) {\n";
echo "        // Indice già esistente o altri errori\n";
echo "        return false;\n";
echo "    }\n";
echo "}\n";

echo "\n=== MODIFICA upload_FILE.php ===\n";
echo "Chiamare ensureUniqueIndex prima di insertMany:\n";
echo "ensureUniqueIndex(\$uploadCollection, \$uploadCollectionName);\n";

echo "\n=== TEST DUPLICATE PREVENTION ===\n";
echo "Per testare, ri-importa lo stesso file JSON - dovrebbe essere respinto\n";

echo "\n=== IMPLEMENTAZIONE COMPLETATA ===\n";
?>