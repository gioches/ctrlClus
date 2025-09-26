<?php
/**
 * MongoDB Connection Manager
 * 
 * This file handles the connection to MongoDB and provides query functions
 */

class MongoDBConnector {
    private $client;
    private $database;
    private $currentCluster;
    
    /**
     * Constructor - establishes connection to MongoDB
     * 
     * @param string $host MongoDB host
     * @param int $port MongoDB port
     * @param string $username MongoDB username
     * @param string $password MongoDB password
     * @param string $dbName Database name
     */
    public function __construct($host = 'localhost', $port = 27017, $username = '', $password = '', $dbName = 'ctrlClus') {
        try {
            // Create connection string
            $connectionString = "mongodb://";
            
            // Add authentication if provided
            if (!empty($username) && !empty($password)) {
                $connectionString .= "$username:$password@";
            }
            
            // Complete the connection string
            $connectionString .= "$host:$port";
            
            // Create MongoDB client
            $this->client = new \MongoDB\Client($connectionString); //MongoDB\Driver\Manager
            //$this->client = new  \MongoDB\Driver\Manager($connectionString);
            
            // Connect to the database
            $this->database = $this->client->$dbName;
            
            // Set default cluster
            $this->currentCluster = 'cluster1';
            
            // Log successful connection
            $this->logAction("Connected to MongoDB at $host:$port");
        } catch (Exception $e) {
            $this->logError("Failed to connect to MongoDB: " . $e->getMessage());
            die("Database connection failed: " . $e->getMessage());
        }
    }
    
    /**
     * Set the current cluster
     * 
     * @param string $clusterId The cluster ID to set
     * @return bool Success status
     */
    public function setCluster($clusterId) {
        if (empty($clusterId)) {
            $this->logError("Invalid cluster ID provided");
            return false;
        }
        
        $this->currentCluster = $clusterId;
        $this->logAction("Switched to cluster: $clusterId");
        return true;
    }
    
    /**
     * Get the latest events for all nodes in the current cluster
     * 
     * @return array Results from MongoDB
     */
    public function getLatestEvents() {
        // This will be implemented with actual MongoDB query
        // For now, just return the query name for demonstration
        return [
            'query_name' => 'getLatestEvents',
            'cluster' => $this->currentCluster,
            'query_id' => '1.1'
        ];
    }
    
    /**
     * Check if events have occurred previously
     * 
     * @param string $eventId ID of the event to check
     * @return array Results from MongoDB
     */
    public function checkRecurringEvents($eventId = null) {
        // This will be implemented with actual MongoDB query
        // For now, just return the query name for demonstration
        return [
            'query_name' => 'checkRecurringEvents',
            'cluster' => $this->currentCluster,
            'query_id' => '1.1.1',
            'event_id' => $eventId
        ];
    }
    
    /**
     * Get events that occurred in the same time period
     * 
     * @param string $startDate Start date
     * @param string $endDate End date
     * @return array Results from MongoDB
     */
    public function getEventsInPeriod($startDate = null, $endDate = null) {
        // This will be implemented with actual MongoDB query
        // For now, just return the query name for demonstration
        return [
            'query_name' => 'getEventsInPeriod',
            'cluster' => $this->currentCluster,
            'query_id' => '1.1.2',
            'start_date' => $startDate,
            'end_date' => $endDate
        ];
    }
    
    /**
     * Log action to system log
     * 
     * @param string $message Message to log
     */
    private function logAction($message) {
        // In a production environment, implement proper logging
        // For now, just append to a log file
        $logFile = __DIR__ . '/logs/mongodb.log';
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] INFO: $message" . PHP_EOL;
        
        // Create directory if it doesn't exist
        if (!file_exists(dirname($logFile))) {
            mkdir(dirname($logFile), 0755, true);
        }
        
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }
    
    /**
     * Log error to system log
     * 
     * @param string $message Error message to log
     */
    private function logError($message) {
        // In a production environment, implement proper error logging
        // For now, just append to an error log file
        $logFile = __DIR__ . '/logs/mongodb_error.log';
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] ERROR: $message" . PHP_EOL;
        
        // Create directory if it doesn't exist
        if (!file_exists(dirname($logFile))) {
            mkdir(dirname($logFile), 0755, true);
        }
        
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }
}
