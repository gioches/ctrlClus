<?php
/**
 * AJAX Handler for ctrlClus Interface
 * 
 * This file processes AJAX requests from the frontend and returns data
 * from MongoDB based on the requested query
 */

// Include required files
require_once 'vendor/autoload.php';
require_once 'mongodb-connector.php';
require_once 'ctrlClusSchema.php';
//require_once 'query_schema.php';

// Set content type to JSON
header('Content-Type: application/json');

// Get query parameters
$action = isset($_GET['action']) ? $_GET['action'] : '';
$queryId = isset($_GET['query_id']) ? $_GET['query_id'] : '';
$clusterId = isset($_GET['cluster_id']) ? $_GET['cluster_id'] : 'cluster1';

// Initialize MongoDB connector
$config = include('config.php');
$mongo = new MongoDBConnector(
    $config['mongo']['host'],
    $config['mongo']['port'],
    $config['mongo']['username'],
    $config['mongo']['password'],
    $config['mongo']['database']
);

// Initialize query schema
$querySchema = new ctrlClusSchema();

// Set the cluster
$mongo->setCluster($clusterId);

// Process the request
$response = [
    'status' => 'error',
    'message' => 'Invalid action',
    'data' => null
];

try {
    switch ($action) {
        case 'all':
            // Handle query 1.1
            $response = handleLatestEvents($mongo, $querySchema, $queryId, $_GET);
            break;
            
        case 'check_recurring_events':
            // Handle query 1.1.1
            $response = handleRecurringEvents($mongo, $querySchema, $queryId, $_GET);
            break;
            
        case 'get_events_in_period':
            // Handle query 1.1.2
            $response = handleEventsInPeriod($mongo, $querySchema, $queryId, $_GET);
            break;
            
        case 'get_query_schema':
            // Return the query schema for a specific query ID
            $schema = $querySchema->getQuery($queryId);
            if ($schema) {
                $response = [
                    'status' => 'success',
                    'message' => 'Query schema retrieved',
                    'data' => $schema
                ];
            } else {
                $response = [
                    'status' => 'error',
                    'message' => 'Query schema not found',
                    'data' => null
                ];
            }
            break;
            
        default:
            // Invalid action
            $response = [
                'status' => 'error',
                'message' => 'Invalid action: ' . $action,
                'data' => null
            ];
            break;
    }
} catch (Exception $e) {
    // Handle exceptions
    $response = [
        'status' => 'error',
        'message' => 'Error: ' . $e->getMessage(),
        'data' => null
    ];
}

// Output the response as JSON
echo json_encode($response);
exit;

/**
 * Handle Latest Events Query (1.1)
 * 
 * @param MongoDBConnector $mongo MongoDB connector instance
 * @param QuerySchema $querySchema Query schema instance
 * @param string $queryId Query ID
 * @param array $params Request parameters
 * @return array Response data
 */
function handleLatestEvents($mongo, $querySchema, $queryId, $params) {
    // Get the query schema
    $schema = $querySchema->getQuery($queryId);
    die(print_r($schema));
    if (!$schema) {
        return [
            'status' => 'error',
            'message' => 'Query schema not found',
            'data' => null
        ];
    }
    
    // Get parameters
    $limit = isset($params['limit']) ? (int)$params['limit'] : $schema['parameters']['limit']['default'];
    $includeStatus = isset($params['includeStatus']) ? 
        filter_var($params['includeStatus'], FILTER_VALIDATE_BOOLEAN) : 
        $schema['parameters']['includeStatus']['default'];
    
    // For now, just return the query schema without actual MongoDB query
    // In a production environment, this would execute the actual query
    return [
        'status' => 'success',
        'message' => 'Latest events retrieved',
        'data' => [
            'query_id' => $queryId,
            'cluster_id' => $params['cluster_id'] ?? 'cluster1',
            'limit' => $limit,
            'includeStatus' => $includeStatus,
            'schema' => $schema,
            // Placeholder for actual data that would come from MongoDB
            'results' => [
               
            ]
        ]
    ];
}

/**
 * Handle Recurring Events Query (1.1.1)
 * 
 * @param MongoDBConnector $mongo MongoDB connector instance
 * @param QuerySchema $querySchema Query schema instance
 * @param string $queryId Query ID
 * @param array $params Request parameters
 * @return array Response data
 */
function handleRecurringEvents($mongo, $querySchema, $queryId, $params) {
    // Get the query schema
    $schema = $querySchema->getQuery($queryId);
    
    if (!$schema) {
        return [
            'status' => 'error',
            'message' => 'Query schema not found',
            'data' => null
        ];
    }
    
    // Get parameters
    $eventType = isset($params['eventType']) ? $params['eventType'] : null;
    $nodeId = isset($params['nodeId']) ? $params['nodeId'] : null;
    $lookbackDays = isset($params['lookbackDays']) ? (int)$params['lookbackDays'] : 
        $schema['parameters']['lookbackDays']['default'];
    
    // For now, just return the query schema without actual MongoDB query
    return [
        'status' => 'success',
        'message' => 'Recurring events checked',
        'data' => [
            'query_id' => $queryId,
            'cluster_id' => $params['cluster_id'] ?? 'cluster1',
            'eventType' => $eventType,
            'nodeId' => $nodeId,
            'lookbackDays' => $lookbackDays,
            'schema' => $schema,
            // Placeholder for actual data that would come from MongoDB
            'results' => [
                [
                    'Node ID' => 'node_003',
                    'Event Type' => 'High Memory Usage',
                    'Occurrences' => 5,
                    'First Occurrence' => '2025-04-12 14:22:10',
                    'Last Occurrence' => '2025-05-06 10:05:22'
                ],
                [
                    'Node ID' => 'node_005',
                    'Event Type' => 'Connection Timeout',
                    'Occurrences' => 3,
                    'First Occurrence' => '2025-04-28 09:11:05',
                    'Last Occurrence' => '2025-05-06 10:12:58'
                ]
            ]
        ]
    ];
}

/**
 * Handle Events in Period Query (1.1.2)
 * 
 * @param MongoDBConnector $mongo MongoDB connector instance
 * @param QuerySchema $querySchema Query schema instance
 * @param string $queryId Query ID
 * @param array $params Request parameters
 * @return array Response data
 */
function handleEventsInPeriod($mongo, $querySchema, $queryId, $params) {
    // Get the query schema
    $schema = $querySchema->getQuery($queryId);
    
    if (!$schema) {
        return [
            'status' => 'error',
            'message' => 'Query schema not found',
            'data' => null
        ];
    }
    
    // Get parameters
    $startDate = isset($params['startDate']) ? $params['startDate'] : date('Y-m-d H:i:s', strtotime('-1 day'));
    $endDate = isset($params['endDate']) ? $params['endDate'] : date('Y-m-d H:i:s');
    $eventCategory = isset($params['eventCategory']) ? $params['eventCategory'] : null;
    
    // For now, just return the query schema without actual MongoDB query
    return [
        'status' => 'success',
        'message' => 'Events in period retrieved',
        'data' => [
            'query_id' => $queryId,
            'cluster_id' => $params['cluster_id'] ?? 'cluster1',
            'startDate' => $startDate,
            'endDate' => $endDate,
            'eventCategory' => $eventCategory,
            'schema' => $schema,
            // Placeholder for actual data that would come from MongoDB
            'results' => [
                [
                    'Timestamp' => '2025-05-05 23:15:42',
                    'Node ID' => 'node_001',
                    'Event Type' => 'System Shutdown',
                    'Description' => 'Graceful shutdown initiated',
                    'Severity' => 'Info'
                ],
                [
                    'Timestamp' => '2025-05-06 08:30:15',
                    'Node ID' => 'node_001',
                    'Event Type' => 'System Startup',
                    'Description' => 'System started successfully',
                    'Severity' => 'Info'
                ],
                [
                    'Timestamp' => '2025-05-06 10:05:22',
                    'Node ID' => 'node_003',
                    'Event Type' => 'Resource Warning',
                    'Description' => 'High memory usage detected (87%)',
                    'Severity' => 'Warning'
                ]
            ]
        ]
    ];
}
