<?php
/**
 * MongoDB Query Schema
 * 
 * This file contains the schema for all queries used in the ctrlClus interface
 * Each query is defined with a unique ID that corresponds to the menu structure
 */

class QuerySchema {
    // Store query definitions
    private $queries = [];
    
    /**
     * Constructor - initialize query schema
     */
    public function __construct() {
        $this->initializeSchema();
    }
    
    /**
     * Initialize the query schema with all predefined queries
     */
    private function initializeSchema() {
        // Define query 1.1 - Latest Events per Node
        $this->queries['1.1'] = [
            'id' => '1.1',
            'name' => 'Latest Events per Node',
            'description' => 'Retrieves the most recent events for each node in the cluster',
            'collection' => 'events',
            'parameters' => [
                'cluster' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'Cluster ID'
                ],
                'limit' => [
                    'type' => 'integer',
                    'required' => false,
                    'default' => 10,
                    'description' => 'Maximum number of events per node'
                ],
                'includeStatus' => [
                    'type' => 'boolean',
                    'required' => false,
                    'default' => true,
                    'description' => 'Include node status information'
                ]
            ],
            'template' => '
                // MongoDB aggregation pipeline for latest events per node
                // This is a template that will be populated with actual parameters
                [
                    { "$match": { "clusterId": "{{cluster}}" } },
                    { "$sort": { "timestamp": -1 } },
                    { "$group": {
                        "_id": "$nodeId",
                        "latestEvents": { "$slice": ["$ROOT", 0, {{limit}}] },
                        "nodeStatus": { "$first": "$nodeStatus" }
                    }},
                    // Optional status inclusion
                    {{#includeStatus}}
                    { "$lookup": {
                        "from": "nodes",
                        "localField": "_id",
                        "foreignField": "nodeId",
                        "as": "nodeDetails"
                    }},
                    {{/includeStatus}}
                    { "$limit": 100 }
                ]
            ',
            'outputFormat' => [
                'headers' => ['Node ID', 'IP Address', 'Status', 'Last Event', 'Timestamp'],
                'mapping' => [
                    'Node ID' => '_id',
                    'IP Address' => 'nodeDetails.ipAddress',
                    'Status' => 'nodeStatus',
                    'Last Event' => 'latestEvents.eventDescription',
                    'Timestamp' => 'latestEvents.timestamp'
                ]
            ]
        ];
        
        // Define query 1.1.1 - Recurring Events
        $this->queries['1.1.1'] = [
            'id' => '1.1.1',
            'name' => 'Recurring Events',
            'description' => 'Checks if events have occurred previously and their frequency',
            'collection' => 'events',
            'parameters' => [
                'cluster' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'Cluster ID'
                ],
                'eventType' => [
                    'type' => 'string',
                    'required' => false,
                    'description' => 'Type of event to check for recurrence'
                ],
                'nodeId' => [
                    'type' => 'string',
                    'required' => false,
                    'description' => 'Specific node ID to check'
                ],
                'lookbackDays' => [
                    'type' => 'integer',
                    'required' => false,
                    'default' => 30,
                    'description' => 'Number of days to look back for recurring events'
                ]
            ],
            'template' => '
                // MongoDB aggregation pipeline for recurring events
                [
                    { "$match": {
                        "clusterId": "{{cluster}}",
                        {{#eventType}}"eventType": "{{eventType}}",{{/eventType}}
                        {{#nodeId}}"nodeId": "{{nodeId}}",{{/nodeId}}
                        "timestamp": {
                            "$gte": ISODate("{{lookbackDate}}")
                        }
                    }},
                    { "$group": {
                        "_id": {
                            "eventType": "$eventType",
                            "nodeId": "$nodeId"
                        },
                        "count": { "$sum": 1 },
                        "firstOccurrence": { "$min": "$timestamp" },
                        "lastOccurrence": { "$max": "$timestamp" },
                        "descriptions": { "$push": "$eventDescription" }
                    }},
                    { "$match": { "count": { "$gt": 1 } }},
                    { "$sort": { "count": -1 } }
                ]
            ',
            'outputFormat' => [
                'headers' => ['Node ID', 'Event Type', 'Occurrences', 'First Occurrence', 'Last Occurrence'],
                'mapping' => [
                    'Node ID' => '_id.nodeId',
                    'Event Type' => '_id.eventType',
                    'Occurrences' => 'count',
                    'First Occurrence' => 'firstOccurrence',
                    'Last Occurrence' => 'lastOccurrence'
                ]
            ]
        ];
        
        // Define query 1.1.2 - Events in Same Period
        $this->queries['1.1.2'] = [
            'id' => '1.1.2',
            'name' => 'Events in Same Period',
            'description' => 'Retrieves events that occurred during the same time period',
            'collection' => 'events',
            'parameters' => [
                'cluster' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'Cluster ID'
                ],
                'startDate' => [
                    'type' => 'date',
                    'required' => false,
                    'default' => '-1 day',
                    'description' => 'Start date for the period'
                ],
                'endDate' => [
                    'type' => 'date',
                    'required' => false,
                    'default' => 'now',
                    'description' => 'End date for the period'
                ],
                'eventCategory' => [
                    'type' => 'string',
                    'required' => false,
                    'description' => 'Filter by event category'
                ]
            ],
            'template' => '
                // MongoDB aggregation pipeline for events in same period
                [
                    { "$match": {
                        "clusterId": "{{cluster}}",
                        "timestamp": {
                            "$gte": ISODate("{{startDate}}"),
                            "$lte": ISODate("{{endDate}}")
                        }
                        {{#eventCategory}},"category": "{{eventCategory}}"{{/eventCategory}}
                    }},
                    { "$sort": { "timestamp": 1 } },
                    { "$project": {
                        "nodeId": 1,
                        "eventType": 1,
                        "eventDescription": 1,
                        "timestamp": 1,
                        "severity": 1,
                        "category": 1
                    }}
                ]
            ',
            'outputFormat' => [
                'headers' => ['Timestamp', 'Node ID', 'Event Type', 'Description', 'Severity'],
                'mapping' => [
                    'Timestamp' => 'timestamp',
                    'Node ID' => 'nodeId',
                    'Event Type' => 'eventType',
                    'Description' => 'eventDescription',
                    'Severity' => 'severity'
                ]
            ]
        ];
    }
    
    /**
     * Get a query by its ID
     * 
     * @param string $queryId The ID of the query to retrieve
     * @return array|null The query definition or null if not found
     */
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
