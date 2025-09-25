<?php
/**
 * Configuration template for ctrlClus
 * Copy this file to config.php and update with your settings
 */

return [
    'mongodb' => [
        'host' => 'your-mongodb-host',
        'port' => 31007,
        'database' => 'ctrlNods',
        'username' => 'your-username',
        'password' => 'your-password',
        'auth_source' => 'admin',
        'auth_mechanism' => 'SCRAM-SHA-1',
        // Full connection string will be:
        // mongodb://username:password@host:port/database?authSource=admin&authMechanism=SCRAM-SHA-1
    ],

    'clusters' => [
        'example_cluster' => [
            'name' => 'Example Cluster',
            'environment' => 'prod', // prod, dev, test, staging
            'upload_collection' => 'upload_12345',
            'events_collection' => 'events'
        ],
        // Add more clusters as needed
    ],

    'security' => [
        'allowed_upload_extensions' => ['.json'],
        'max_file_size' => '10M',
        'upload_timeout' => 300
    ]
];