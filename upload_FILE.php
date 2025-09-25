<?php
require 'lib/vendor/autoload.php';

use MongoDB\Client;

// Load configuration
if (file_exists('config.php')) {
    $config = include 'config.php';
} else {
    die('Configuration file not found. Please copy config.template.php to config.php and configure your settings.');
}

// Build MongoDB connection string
$mongodb_config = $config['mongodb'];
$connection_string = sprintf(
    'mongodb://%s:%s@%s:%d/%s?authSource=%s&authMechanism=%s',
    $mongodb_config['username'],
    $mongodb_config['password'],
    $mongodb_config['host'],
    $mongodb_config['port'],
    $mongodb_config['database'],
    $mongodb_config['auth_source'],
    $mongodb_config['auth_mechanism']
);

$mongo = new Client($connection_string);
$db = $mongo->selectDatabase("ctrlNods");

$message = '';
$messageType = '';

// Elaborazione POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verifica token
    if (!isset($_POST['token']) || empty($_POST['token'])) {
        $message = "Missing token.";
        $messageType = 'error';
    } else {
        $token = $_POST['token'];
        
        // Verifica token nel database
        $tokenDoc = $db->token->findOne(['token' => $token]);
        
        if (!$tokenDoc || !isset($tokenDoc['idazienda'])) {
            $message = "Access denied: unrecognized token.";
            $messageType = 'error';
        } else {
            $idaziendaObj = $tokenDoc['idazienda'];
            $idaziendaStr = (string) $idaziendaObj;
            $shortIdAzienda = substr($idaziendaStr, -5);
            
            // Verifica presenza file
            if (!isset($_FILES['files']) || empty($_FILES['files']['name'][0])) {
                $message = "No files selected.";
                $messageType = 'error';
            } else {
                $successCount = 0;
                $errorMessages = [];
                $totalDocuments = 0;
                
                // Elabora ogni file
                for ($i = 0; $i < count($_FILES['files']['name']); $i++) {
                    if ($_FILES['files']['error'][$i] !== UPLOAD_ERR_OK) {
                        $errorMessages[] = "Errore nel file " . $_FILES['files']['name'][$i];
                        continue;
                    }
                    
                    $jsonStr = file_get_contents($_FILES['files']['tmp_name'][$i]);
                    $data = json_decode($jsonStr, true);
                    
                    if (!is_array($data)) {
                        $errorMessages[] = "JSON non valido nel file " . $_FILES['files']['name'][$i];
                        continue;
                    }
                    
                    // Normalizza per array di oggetti
                    if (!isset($data[0]) || !is_array($data[0])) {
                        $data = [$data];
                    }
                    
                    $numCampi = count($data[0]) + 2;
                    
                    // Inserisci documento evento
                    $event = [
                        'sender_ip'   => $_SERVER['REMOTE_ADDR'],
                        'timestamp'   => new MongoDB\BSON\UTCDateTime(),
                        'version'     => $numCampi,
                        'id_azienda'  => $shortIdAzienda,
                        'filename'    => $_FILES['files']['name'][$i]
                    ];
                    
                    $insertedEvent = $db->events->insertOne($event);
                    $idupload = $insertedEvent->getInsertedId();
                    
                    // Estrai informazioni cluster dai dati per auto-popolamento
                    $clusterInfo = extractClusterInfo($data, $shortIdAzienda, $idaziendaStr);
                    
                    // Auto-popola collection clusters
                    upsertClusterInfo($db, $clusterInfo);
                    
                    // Inserisci dati nella collection upload_<ULTIME5>
                    $uploadCollectionName = 'upload_' . $shortIdAzienda;
                    $uploadCollection = $db->$uploadCollectionName;
                    
                    // 1. Assicura indice unico esista
                    ensureUniqueIndex($uploadCollection, $uploadCollectionName);
                    
                    foreach ($data as &$record) {
                        $record['idupload'] = $idupload;
                    }
                    
                    // 2. Insert con ordered:false - continua anche con duplicati
                    $insertResult = insertWithDuplicateHandling($uploadCollection, $data, $_FILES['files']['name'][$i]);
                    
                    $totalDocuments += $insertResult['inserted'];
                    
                    // Messaging dettagliato per chiarezza
                    if ($insertResult['duplicates'] > 0 || $insertResult['errors'] > 0) {
                        $msg = "File '{$_FILES['files']['name'][$i]}': ";
                        if ($insertResult['inserted'] > 0) {
                            $msg .= "{$insertResult['inserted']} nuovi record inseriti";
                        } else {
                            $msg .= "0 nuovi record inseriti";
                        }
                        if ($insertResult['duplicates'] > 0) {
                            $msg .= ", {$insertResult['duplicates']} duplicati ignorati";
                        }
                        if ($insertResult['errors'] > 0) {
                            $msg .= ", {$insertResult['errors']} errors";
                        }
                        $errorMessages[] = $msg;
                    } elseif ($insertResult['inserted'] == 0) {
                        // Specific case: zero inserted without duplicates/errors
                        $errorMessages[] = "File '{$_FILES['files']['name'][$i]}': 0 records inserted (possible duplicates or undetected errors)";
                    }
                    
                    $successCount++;
                }
                
                // Prepara messaggio di risposta
                if ($successCount > 0) {
                    $message = "Successfully processed $successCount files. ";
                    $message .= "Inserted $totalDocuments documents into 'upload_$shortIdAzienda'.";
                    $messageType = 'success';
                }
                
                if (!empty($errorMessages)) {
                    $message .= "<br>Errors: " . implode("<br>", $errorMessages);
                    $messageType = ($successCount > 0) ? 'warning' : 'error';
                }
            }
        }
    }
}

/**
 * Estrae informazioni del cluster dai dati importati
 * 
 * @param array $data Dati JSON importati
 * @param string $shortIdAzienda ID azienda (ultime 5 cifre)
 * @param string $fullIdAzienda ID azienda completo
 * @return array Informazioni cluster
 */
function extractClusterInfo($data, $shortIdAzienda, $fullIdAzienda) {
    // Determina ambiente basandosi su pattern comuni nei dati
    $ambiente = determineEnvironment($data);
    
    // Estrai cluster ID se presente nei dati, altrimenti usa shortIdAzienda
    $clusterId = extractClusterId($data, $shortIdAzienda);
    
    // Genera nome cluster
    $nomeCluster = generateClusterName($data, $ambiente, $shortIdAzienda);
    
    return [
        'IDCLUSTER' => $clusterId,
        'idazienda' => $fullIdAzienda,
        'ambiente' => $ambiente,
        'nome' => $nomeCluster,
        'note' => 'Auto-generato durante import ' . date('Y-m-d H:i:s'),
        'last_update' => new MongoDB\BSON\UTCDateTime(),
        'upload_collection' => 'upload_' . $shortIdAzienda
    ];
}

/**
 * Determina l'ambiente basandosi sui dati
 * 
 * @param array $data Dati JSON
 * @return string Ambiente (prod, svil, perf, test)
 */
function determineEnvironment($data) {
    // Controlla primi record per pattern ambiente
    $sampleData = array_slice($data, 0, 5);
    
    foreach ($sampleData as $record) {
        // Cerca pattern negli IP, nomi servizi, o campi specifici
        if (isset($record['FROM_IP'])) {
            $ip = $record['FROM_IP'];
            // Pattern IP per ambienti
            if (strpos($ip, '192.168.1.') === 0) return 'prod';
            if (strpos($ip, '192.168.2.') === 0) return 'svil';
            if (strpos($ip, '192.168.3.') === 0) return 'test';
            if (strpos($ip, '192.168.4.') === 0) return 'perf';
        }
        
        // Cerca pattern nei nomi servizi
        if (isset($record['service'])) {
            $service = strtolower($record['service']);
            if (strpos($service, 'prod') !== false) return 'prod';
            if (strpos($service, 'dev') !== false || strpos($service, 'svil') !== false) return 'svil';
            if (strpos($service, 'test') !== false) return 'test';
            if (strpos($service, 'perf') !== false) return 'perf';
        }
        
        // Cerca pattern nel cluster field se presente
        if (isset($record['cluster'])) {
            $cluster = strtolower($record['cluster']);
            if (strpos($cluster, 'prod') !== false) return 'prod';
            if (strpos($cluster, 'dev') !== false || strpos($cluster, 'svil') !== false) return 'svil';
            if (strpos($cluster, 'test') !== false) return 'test';
            if (strpos($cluster, 'perf') !== false) return 'perf';
        }
    }
    
    // Default se non si riesce a determinare
    return 'prod';
}

/**
 * Estrae o genera cluster ID
 * 
 * @param array $data Dati JSON
 * @param string $shortIdAzienda ID azienda
 * @return string Cluster ID
 */
function extractClusterId($data, $shortIdAzienda) {
    // Cerca cluster ID nei dati
    foreach ($data as $record) {
        if (isset($record['cluster']) && !empty($record['cluster'])) {
            return $record['cluster'];
        }
    }
    
    // Se non trovato, genera basandosi su shortIdAzienda
    return 'cluster_' . $shortIdAzienda;
}

/**
 * Genera nome cluster
 * 
 * @param array $data Dati JSON
 * @param string $ambiente Ambiente
 * @param string $shortIdAzienda ID azienda
 * @return string Nome cluster
 */
function generateClusterName($data, $ambiente, $shortIdAzienda) {
    // Cerca nomi servizi per generare nome descrittivo
    $services = [];
    foreach (array_slice($data, 0, 10) as $record) {
        if (isset($record['service']) && !in_array($record['service'], $services)) {
            $services[] = $record['service'];
        }
    }
    
    $serviceName = !empty($services) ? $services[0] : 'Generic';
    return ucfirst($ambiente) . ' - ' . $serviceName . ' (' . $shortIdAzienda . ')';
}

/**
 * Inserisce o aggiorna informazioni cluster
 * 
 * @param object $db Database MongoDB
 * @param array $clusterInfo Informazioni cluster
 */
function upsertClusterInfo($db, $clusterInfo) {
    $clustersCollection = $db->clusters;
    
    // Cerca cluster esistente per IDCLUSTER e idazienda
    $existingCluster = $clustersCollection->findOne([
        'IDCLUSTER' => $clusterInfo['IDCLUSTER'],
        'idazienda' => $clusterInfo['idazienda']
    ]);
    
    if ($existingCluster) {
        // Aggiorna solo timestamp e note se cluster esiste
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
        // Inserisci nuovo cluster
        $clustersCollection->insertOne($clusterInfo);
    }
}

/**
 * Assicura che l'indice unico esista per la collection
 * 
 * @param object $collection Collection MongoDB
 * @param string $collectionName Nome collection
 * @return bool True if index exists or was created successfully
 */
function ensureUniqueIndex($collection, $collectionName) {
    try {
        // Tenta di creare indice unico: id + now + service + FROM_IP
        $collection->createIndex(
            [
                'id' => 1,
                'now' => 1,
                'service' => 1,
                'FROM_IP' => 1  // Aggiunto per supportare filtro cluster
            ],
            [
                'unique' => true,
                'name' => 'unique_node_time_service_ip',
                'background' => true,  // Non-bloccante
                'sparse' => true       // Ignora documenti con campi mancanti
            ]
        );
        return true;
    } catch (Exception $e) {
        // Indice già esistente o altro errore - continua comunque
        return false;
    }
}

/**
 * Inserisce documenti gestendo duplicati tramite MongoDB
 * 
 * @param object $collection Collection MongoDB
 * @param array $data Documenti da inserire
 * @param string $filename Nome file per log
 * @return array ['inserted' => int, 'duplicates' => int, 'errors' => int]
 */
function insertWithDuplicateHandling($collection, $data, $filename = '') {
    $result = [
        'inserted' => 0,
        'duplicates' => 0,
        'errors' => 0
    ];
    
    $totalRecords = count($data);
    
    try {
        // 2. Insert con ordered:false - continua anche se alcuni falliscono
        $insertResult = $collection->insertMany($data, [
            'ordered' => false  // Continue insertion even with errors
        ]);
        
        $result['inserted'] = $insertResult->getInsertedCount();
        
        // Log complete success
        if ($result['inserted'] == $totalRecords) {
            error_log("Upload success in $filename: All {$totalRecords} records inserted");
        }
        
    } catch (MongoDB\Driver\Exception\BulkWriteException $e) {
        // 3. Error handling: distinguish duplicates from real errors
        $writeResult = $e->getWriteResult();
        $result['inserted'] = $writeResult->getInsertedCount();
        
        // Analyze errors to classify duplicates vs real errors
        $writeErrors = $writeResult->getWriteErrors();
        
        foreach ($writeErrors as $error) {
            $errorCode = $error->getCode();
            
            // Code 11000 = duplicate key error
            if ($errorCode === 11000) {
                $result['duplicates']++;
            } else {
                $result['errors']++;
                // Log real error for debugging
                error_log("Upload error in $filename: Code $errorCode - " . $error->getMessage());
            }
        }
        
        // Detailed log for diagnostics
        error_log("Upload partial result in $filename: {$result['inserted']} inserted, {$result['duplicates']} duplicates, {$result['errors']} errors out of $totalRecords total");
        
    } catch (Exception $e) {
        // General unhandled error
        $result['errors'] = count($data);
        error_log("Fatal upload error in $filename: " . $e->getMessage());
    }
    
    return $result;
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ctrlClus Upload Interface</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            grid-template-columns: none;
            grid-template-rows: none;
            grid-template-areas: none;
        }
        
        .upload-container {
            background-color: var(--terminal-bg-color);
            border: 1px solid var(--border-color);
            border-radius: 5px;
            box-shadow: 0 0 20px rgba(0, 255, 0, 0.2);
            max-width: 700px;
            width: 100%;
            padding: 30px;
        }
        
        .upload-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .upload-title {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 30px;
            margin-bottom: 10px;
        }
        
        .title-part {
            color: var(--header-color);
            font-size: 2rem;
            letter-spacing: 3px;
            text-shadow: 0 0 10px rgba(0, 255, 0, 0.5);
        }
        
        .data-flow {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 1.5rem;
        }
        
        .data-packet {
            display: inline-block;
            width: 8px;
            height: 8px;
            background: var(--header-color);
            border-radius: 2px;
            opacity: 0;
            animation: dataTransfer 2s infinite;
            box-shadow: 0 0 10px var(--header-color);
        }
        
        .data-packet:nth-child(1) { animation-delay: 0s; }
        .data-packet:nth-child(2) { animation-delay: 0.2s; }
        .data-packet:nth-child(3) { animation-delay: 0.4s; }
        .data-packet:nth-child(4) { animation-delay: 0.6s; }
        .data-packet:nth-child(5) { animation-delay: 0.8s; }
        
        @keyframes dataTransfer {
            0% {
                opacity: 0;
                transform: translateX(-20px) scale(0.5);
            }
            25% {
                opacity: 1;
                transform: translateX(0) scale(1);
            }
            75% {
                opacity: 1;
                transform: translateX(0) scale(1);
            }
            100% {
                opacity: 0;
                transform: translateX(20px) scale(0.5);
            }
        }
        
        
        .upload-subtitle {
            color: var(--accent-color);
            font-size: 0.9rem;
            margin-top: 15px;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
        }
        
        .status-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            background: var(--header-color);
            border-radius: 50%;
            animation: statusBlink 2s infinite;
        }
        
        @keyframes statusBlink {
            0%, 100% { opacity: 0.3; box-shadow: 0 0 5px var(--header-color); }
            50% { opacity: 1; box-shadow: 0 0 20px var(--header-color); }
        }
        
        .terminal-prompt {
            color: var(--accent-color);
            margin-right: 5px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-label {
            display: block;
            color: var(--terminal-text-color);
            margin-bottom: 10px;
            font-size: 0.95rem;
        }
        
        .form-label::before {
            content: "> ";
            color: var(--accent-color);
        }
        
        .token-input {
            width: 100%;
            padding: 12px 15px;
            background-color: #0a0a0a;
            color: var(--terminal-text-color);
            border: 1px solid var(--border-color);
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .token-input:focus {
            outline: none;
            box-shadow: 0 0 8px rgba(0, 255, 0, 0.3);
            border-color: var(--accent-color);
        }
        
        .file-upload-area {
            border: 2px dashed var(--border-color);
            border-radius: 5px;
            padding: 40px 20px;
            text-align: center;
            background-color: #0a0a0a;
            transition: all 0.3s;
            cursor: pointer;
            position: relative;
        }
        
        .file-upload-area.dragover {
            border-color: var(--header-color);
            background-color: rgba(0, 255, 0, 0.05);
            box-shadow: inset 0 0 20px rgba(0, 255, 0, 0.1);
        }
        
        .file-upload-area:hover {
            border-color: var(--accent-color);
        }
        
        .upload-icon {
            font-size: 48px;
            color: var(--accent-color);
            margin-bottom: 15px;
            text-shadow: 0 0 5px rgba(0, 255, 0, 0.5);
        }
        
        .upload-text {
            color: var(--terminal-text-color);
            font-size: 1.1rem;
            margin-bottom: 10px;
        }
        
        .upload-hint {
            color: var(--accent-color);
            font-size: 0.85rem;
        }
        
        .file-list {
            margin-top: 20px;
            max-height: 250px;
            overflow-y: auto;
            border: 1px solid var(--border-color);
            border-radius: 3px;
            background-color: #0a0a0a;
        }
        
        .file-list:not(:empty) {
            padding: 10px;
        }
        
        .file-item {
            background-color: rgba(0, 255, 0, 0.05);
            padding: 10px 15px;
            border: 1px solid var(--border-color);
            border-radius: 3px;
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.2s;
        }
        
        .file-item:hover {
            background-color: rgba(0, 255, 0, 0.1);
            border-color: var(--accent-color);
        }
        
        .file-name {
            color: var(--terminal-text-color);
            font-size: 0.9rem;
            flex: 1;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .file-name::before {
            content: "[+] ";
            color: var(--accent-color);
        }
        
        .file-size {
            color: var(--accent-color);
            font-size: 0.8rem;
            margin-left: 15px;
        }
        
        .remove-file {
            background: transparent;
            color: var(--error-color);
            border: 1px solid var(--error-color);
            border-radius: 3px;
            width: 24px;
            height: 24px;
            cursor: pointer;
            font-size: 16px;
            line-height: 1;
            margin-left: 15px;
            transition: all 0.2s;
        }
        
        .remove-file:hover {
            background: var(--error-color);
            color: white;
            box-shadow: 0 0 5px var(--error-color);
        }
        
        .submit-btn {
            width: 100%;
            padding: 15px;
            background-color: transparent;
            color: var(--header-color);
            border: 2px solid var(--header-color);
            border-radius: 3px;
            font-size: 1rem;
            font-weight: bold;
            font-family: 'Courier New', monospace;
            text-transform: uppercase;
            letter-spacing: 2px;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }
        
        .submit-btn::before {
            content: "";
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(0, 255, 0, 0.4), transparent);
            transition: left 0.5s;
        }
        
        .submit-btn:hover {
            background-color: var(--header-color);
            color: var(--main-bg-color);
            box-shadow: 0 0 20px rgba(0, 255, 0, 0.5);
            text-shadow: none;
        }
        
        .submit-btn:hover::before {
            left: 100%;
        }
        
        .submit-btn:disabled {
            background-color: transparent;
            color: var(--border-color);
            border-color: var(--border-color);
            cursor: not-allowed;
            opacity: 0.5;
        }
        
        .message {
            padding: 15px;
            border-radius: 3px;
            margin-bottom: 20px;
            border: 1px solid;
            font-family: 'Courier New', monospace;
            position: relative;
            animation: fadeIn 0.5s;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .message::before {
            content: "$ ";
            color: inherit;
            font-weight: bold;
        }
        
        .message.success {
            background-color: rgba(0, 255, 0, 0.1);
            color: var(--terminal-text-color);
            border-color: var(--header-color);
            box-shadow: 0 0 10px rgba(0, 255, 0, 0.2);
        }
        
        .message.error {
            background-color: rgba(255, 0, 0, 0.1);
            color: var(--error-color);
            border-color: var(--error-color);
            box-shadow: 0 0 10px rgba(255, 0, 0, 0.2);
        }
        
        .message.warning {
            background-color: rgba(255, 170, 0, 0.1);
            color: #ffaa00;
            border-color: #ffaa00;
            box-shadow: 0 0 10px rgba(255, 170, 0, 0.2);
        }
        
        .blinking-cursor::after {
            content: "_";
            animation: blink 1s step-end infinite;
            color: var(--terminal-text-color);
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="upload-container">
        <div class="upload-header">
            <div class="upload-title">
                <span class="title-part">ctrlNods</span>
                <div class="data-flow">
                    <span class="data-packet"></span>
                    <span class="data-packet"></span>
                    <span class="data-packet"></span>
                    <span class="data-packet"></span>
                    <span class="data-packet"></span>
                </div>
                <span class="title-part">ctrlClus</span>
            </div>
            <div class="upload-subtitle">
                <span class="status-indicator"></span>
                [ DATA TRANSFER & PROCESSING INTERFACE ]
                <span class="status-indicator"></span>
            </div>
        </div>
        
        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" enctype="multipart/form-data" id="uploadForm">
            <div class="form-group">
                <label class="form-label" for="token">Authentication Token</label>
                <input type="text" class="token-input" id="token" name="token" placeholder="" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">JSON Files Upload</label>
                <div class="file-upload-area" id="dropZone">
                    <div class="upload-icon">⬆</div>
                    <div class="upload-text">DROP JSON FILES HERE</div>
                    <div class="upload-hint">[ CLICK TO BROWSE ]</div>
                    <input type="file" class="file-input" id="fileInput" name="files[]" accept=".json" multiple style="display: none;">
                </div>
                <div class="file-list" id="fileList"></div>
            </div>
            
            <button type="submit" class="submit-btn" id="submitBtn">
                <span class="terminal-prompt">$</span> EXECUTE UPLOAD
            </button>
        </form>
    </div>
    
    <script>
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('fileInput');
        const fileList = document.getElementById('fileList');
        const submitBtn = document.getElementById('submitBtn');
        const form = document.getElementById('uploadForm');
        
        let selectedFiles = [];
        
        // Click per aprire file browser
        dropZone.addEventListener('click', () => {
            fileInput.click();
        });
        
        // Gestione selezione file
        fileInput.addEventListener('change', (e) => {
            handleFiles(e.target.files);
        });
        
        // Drag and drop
        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('dragover');
        });
        
        dropZone.addEventListener('dragleave', () => {
            dropZone.classList.remove('dragover');
        });
        
        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('dragover');
            
            const files = Array.from(e.dataTransfer.files).filter(file => 
                file.type === 'application/json' || file.name.endsWith('.json')
            );
            
            if (files.length > 0) {
                handleFiles(files);
            } else {
                alert('Please select only JSON files');
            }
        });
        
        function handleFiles(files) {
            selectedFiles = Array.from(files);
            updateFileList();
            
            // Aggiorna l'input file
            const dataTransfer = new DataTransfer();
            selectedFiles.forEach(file => dataTransfer.items.add(file));
            fileInput.files = dataTransfer.files;
        }
        
        function updateFileList() {
            fileList.innerHTML = '';
            
            if (selectedFiles.length === 0) {
                return;
            }
            
            selectedFiles.forEach((file, index) => {
                const fileItem = document.createElement('div');
                fileItem.className = 'file-item';
                
                const fileName = document.createElement('span');
                fileName.className = 'file-name';
                fileName.textContent = file.name;
                
                const fileSize = document.createElement('span');
                fileSize.className = 'file-size';
                fileSize.textContent = formatFileSize(file.size);
                
                const removeBtn = document.createElement('button');
                removeBtn.className = 'remove-file';
                removeBtn.textContent = '×';
                removeBtn.type = 'button';
                removeBtn.onclick = () => removeFile(index);
                
                fileItem.appendChild(fileName);
                fileItem.appendChild(fileSize);
                fileItem.appendChild(removeBtn);
                fileList.appendChild(fileItem);
            });
        }
        
        function removeFile(index) {
            selectedFiles.splice(index, 1);
            updateFileList();
            
            // Aggiorna l'input file
            const dataTransfer = new DataTransfer();
            selectedFiles.forEach(file => dataTransfer.items.add(file));
            fileInput.files = dataTransfer.files;
        }
        
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        }
        
        // Validazione form
        form.addEventListener('submit', (e) => {
            if (selectedFiles.length === 0) {
                e.preventDefault();
                alert('[ERROR] No JSON files selected');
                return false;
            }
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="terminal-prompt">$</span> UPLOADING<span class="blinking-cursor"></span>';
        });
    </script>
</body>
</html>