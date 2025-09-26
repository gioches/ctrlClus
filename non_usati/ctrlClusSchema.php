<?php

/* 
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/EmptyPHP.php to edit this template
 */
class ctrlClusSchema {
    // Store query definitions
    private $queries = [];
    
    /**
     * Constructor - initialize query schema
     */
    public function __construct() {
        $this->initializeSchema();
    }
    
    private function initializeSchema() {
        
    
        $this->queries['1.1'] = [
                    'id' => '1.1',
                    'name' => 'Active Connections Summary',
                    'description' => 'Counts active (UP) connections grouped by cluster, service, and destination IP',
                    'collection' => 'upload_5f5c3',
                    'parameters' => [
                        'cluster' => [
                            'type' => 'string',
                            'required' => false,
                            'description' => 'Cluster ID'
                        ],
                        'service' => [
                            'type' => 'string',
                            'required' => false,
                            'description' => 'Service name (e.g., NMAP)'
                        ],
                        'lookbackDays' => [
                            'type' => 'integer',
                            'required' => false,
                            'default' => 7,
                            'description' => 'Days to look back from now'
                        ]
                    ],
                    'template' => '
                        [
                            { "$match": {
                                "state": 1,
                                {{#cluster}}"cluster": "{{cluster}}",{{/cluster}}
                                {{#service}}"service": "{{service}}",{{/service}}
                                "now": { "$gte": "{{lookbackDate}}" }
                            }},
                            { "$group": {
                                "_id": {
                                    "cluster": "$cluster",
                                    "service": "$service",
                                    "to_ip": "$TO_IP"
                                },
                                "activeCount": { "$sum": 1 },
                                "lastMessage": { "$max": "$now" }
                            }},
                            { "$sort": { "activeCount": -1 } }
                        ]
                    ',
                    'outputFormat' => [
                        'headers' => ['Node ID', 'Sync', 'Cluster', 'Now', 'Service','FROM_IP','FROM_MC','TO_IP','TO_MC','State','Message','ID Padre','Durata'],
                        'mapping' => [
                            'Node ID' => '_id.id',
                            'Sync' => '_id.sync',
                            'Cluster' => '_id.cluster',
                            'Now' => '_id.now',
                            'Service' => '',
                            'FROM_IP' => '',
                            'FROM_MC' => '',
                            'TO_IP' => '',
                            'TO_MC' => '',
                            'State' => '',
                            'Message' => '',
                            'ID Padre' => '',
                            'Durata' =>   ''  
                        ]
                    ]
                ];

        }
        
          public function getQuery($queryId) {
        if (isset($this->queries[$queryId])) {
            return $this->queries[$queryId];
        }
        return null;
    }
    
    /**
     * Get all queries
     * 
     * @return array All query definitions
     */
    public function getAllQueries() {
        return $this->queries;
    }
    
    /**
     * Get queries by category
     * 
     * @param string $category The category to filter by (e.g., '1' for Panorama)
     * @return array Queries in the specified category
     */
    public function getQueriesByCategory($category) {
        $result = [];
        foreach ($this->queries as $id => $query) {
            if (strpos($id, $category) === 0) {
                $result[$id] = $query;
            }
        }
        return $result;
    }
}