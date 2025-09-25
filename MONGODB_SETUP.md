# MongoDB Database Setup for ctrlClus

Complete guide for setting up MongoDB database infrastructure for the ctrlClus monitoring system.

## 📋 MongoDB Requirements

- **MongoDB Version**: 4.4+ (recommended: 6.0+)
- **Authentication**: SCRAM-SHA-1 mechanism
- **Storage**: Minimum 10GB (recommended: 100GB+ for production)
- **Memory**: 4GB RAM minimum
- **Network**: Access from ctrlClus web server and all ctrlNods agents

## 🚀 Installation & Initial Configuration

### 1. MongoDB Installation

#### Ubuntu/Debian:
```bash
# Install MongoDB Community Edition
wget -qO - https://www.mongodb.org/static/pgp/server-6.0.asc | sudo apt-key add -
echo "deb [ arch=amd64,arm64 ] https://repo.mongodb.org/apt/ubuntu $(lsb_release -cs)/mongodb-org/6.0 multiverse" | sudo tee /etc/apt/sources.list.d/mongodb-org-6.0.list
sudo apt update
sudo apt install -y mongodb-org

# Start and enable MongoDB
sudo systemctl start mongod
sudo systemctl enable mongod
```

#### CentOS/RHEL:
```bash
# Create repository file
sudo tee /etc/yum.repos.d/mongodb-org-6.0.repo <<EOF
[mongodb-org-6.0]
name=MongoDB Repository
baseurl=https://repo.mongodb.org/yum/redhat/\$releasever/mongodb-org/6.0/x86_64/
gpgcheck=1
enabled=1
gpgkey=https://www.mongodb.org/static/pgp/server-6.0.asc
EOF

# Install MongoDB
sudo yum install -y mongodb-org

# Start and enable MongoDB
sudo systemctl start mongod
sudo systemctl enable mongod
```

### 2. Basic MongoDB Configuration

Edit `/etc/mongod.conf`:
```yaml
# Network interfaces
net:
  port: 27018
  bindIp: 0.0.0.0  # WARNING: Only for development. Restrict in production!

# Security
security:
  authorization: enabled

# Storage
storage:
  dbPath: /var/lib/mongodb
  journal:
    enabled: true
  wiredTiger:
    engineConfig:
      cacheSizeGB: 2  # Adjust based on available RAM

# Operation Profiling
operationProfiling:
  slowOpThresholdMs: 1000
  mode: slowOp
```

Restart MongoDB:
```bash
sudo systemctl restart mongod
```

## 🔐 User Authentication Setup

### 1. Create Admin User

```bash
# Connect to MongoDB
mongosh --port 27018

# Switch to admin database
use admin

# Create admin user
db.createUser({
  user: "admin",
  pwd: "your-secure-admin-password",
  roles: [
    { role: "userAdminAnyDatabase", db: "admin" },
    { role: "readWriteAnyDatabase", db: "admin" },
    { role: "dbAdminAnyDatabase", db: "admin" }
  ]
})
```

### 2. Create ctrlNods Application User

```bash
# Authenticate as admin
db.auth("admin", "your-secure-admin-password")

# Switch to ctrlNods database
use ctrlNods

# Create application user for ctrlNods/ctrlClus
db.createUser({
  user: "ctrlnods_app",
  pwd: "your-secure-app-password",
  roles: [
    { role: "readWrite", db: "ctrlNods" },
    { role: "dbAdmin", db: "ctrlNods" }
  ]
})
```

## 🗄️ Database Schema Creation

### 1. Connect with Application User

```bash
mongosh --port 27018 -u ctrlnods_app -p your-secure-app-password --authenticationDatabase ctrlNods ctrlNods
```

### 2. Core Collections Setup

```javascript
// ===== EVENTS COLLECTION =====
// Tracks all upload events from ctrlNods agents
db.createCollection("events");

// Add indexes for performance
db.events.createIndex({ "timestamp": 1 });
db.events.createIndex({ "sender_ip": 1 });
db.events.createIndex({ "id_azienda": 1 });
db.events.createIndex({ "filename": 1 });

// ===== TOKEN COLLECTION =====
// Authentication tokens for upload access
db.createCollection("token");

// Unique index on token field
db.token.createIndex({ "token": 1 }, { "unique": true });
db.token.createIndex({ "idazienda": 1 });

// ===== CLUSTERS COLLECTION =====
// Auto-generated cluster information
db.createCollection("clusters");

// Composite unique index
db.clusters.createIndex(
  { "IDCLUSTER": 1, "idazienda": 1 },
  { "unique": true }
);
db.clusters.createIndex({ "ambiente": 1 });
db.clusters.createIndex({ "last_update": 1 });
```

### 3. Sample Data Insertion

```javascript
// ===== INSERT SAMPLE TOKEN =====
db.token.insertOne({
  "token": "sample-token-12345",
  "idazienda": "67890",
  "company_name": "Sample Company",
  "created": new Date(),
  "active": true,
  "description": "Sample token for testing"
});

// ===== INSERT SAMPLE CLUSTER =====
db.clusters.insertOne({
  "IDCLUSTER": "cluster_67890",
  "idazienda": "67890",
  "ambiente": "prod",
  "nome": "Production Cluster (67890)",
  "note": "Sample cluster for testing",
  "last_update": new Date(),
  "upload_collection": "upload_67890"
});

// ===== CREATE SAMPLE UPLOAD COLLECTION =====
db.createCollection("upload_67890");

// Add performance indexes to upload collection
db.upload_67890.createIndex(
  { "id": 1, "now": 1, "service": 1, "FROM_IP": 1 },
  {
    "unique": true,
    "name": "unique_node_time_service_ip",
    "background": true,
    "sparse": true
  }
);

// Additional indexes for queries
db.upload_67890.createIndex({ "idupload": 1 });
db.upload_67890.createIndex({ "now": 1 });
db.upload_67890.createIndex({ "FROM_IP": 1 });
db.upload_67890.createIndex({ "service": 1 });
```

## ⚡ Performance Optimization

### 1. Collection Sharding (for large deployments)

```javascript
// Enable sharding (run on mongos in sharded environment)
sh.enableSharding("ctrlNods");

// Shard upload collections by FROM_IP and timestamp
sh.shardCollection("ctrlNods.upload_67890", { "FROM_IP": 1, "now": 1 });
```

### 2. Time-Based Collection Rotation

```javascript
// Create monthly collections for large data volumes
// Example: upload_67890_202501, upload_67890_202502, etc.

// Function to create monthly upload collection
function createMonthlyUploadCollection(companyId, year, month) {
  const collectionName = `upload_${companyId}_${year}${month.toString().padStart(2, '0')}`;

  db.createCollection(collectionName);

  // Add indexes
  db[collectionName].createIndex(
    { "id": 1, "now": 1, "service": 1, "FROM_IP": 1 },
    {
      "unique": true,
      "name": "unique_node_time_service_ip",
      "background": true,
      "sparse": true
    }
  );

  db[collectionName].createIndex({ "idupload": 1 });
  db[collectionName].createIndex({ "now": 1 });
  db[collectionName].createIndex({ "FROM_IP": 1 });

  return collectionName;
}

// Usage example
createMonthlyUploadCollection("67890", 2025, 1);
```

### 3. Aggregation Pipeline Optimization

```javascript
// Create views for common queries
db.createView(
  "cluster_health_summary",
  "upload_67890",
  [
    {
      $group: {
        _id: "$FROM_IP",
        latest_update: { $max: "$now" },
        total_events: { $sum: 1 },
        services: { $addToSet: "$service" }
      }
    },
    {
      $lookup: {
        from: "clusters",
        localField: "_id",
        foreignField: "upload_collection",
        as: "cluster_info"
      }
    }
  ]
);
```

## 🔒 Security Hardening

### 1. Network Security

```bash
# Configure firewall (UFW example)
sudo ufw allow from ctrlclus-web-server-ip to any port 27018
sudo ufw allow from ctrlnods-agent-ip-range to any port 27018
sudo ufw deny 27018
```

### 2. SSL/TLS Configuration

Add to `/etc/mongod.conf`:
```yaml
net:
  tls:
    mode: requireTLS
    certificateKeyFile: /etc/ssl/mongodb/mongodb.pem
    CAFile: /etc/ssl/mongodb/ca.pem
```

### 3. Audit Logging

```yaml
auditLog:
  destination: file
  format: JSON
  path: /var/log/mongodb/auditLog.json
```

## 📊 Monitoring & Maintenance

### 1. Database Statistics

```javascript
// Check database size and statistics
db.stats();

// Check collection statistics
db.upload_67890.stats();

// Check index usage
db.upload_67890.aggregate([{ $indexStats: {} }]);
```

### 2. Maintenance Scripts

```javascript
// Clean old events (older than 90 days)
db.events.deleteMany({
  "timestamp": {
    $lt: new Date(Date.now() - 90 * 24 * 60 * 60 * 1000)
  }
});

// Compact collections
db.runCommand({ compact: "upload_67890" });

// Re-index collections
db.upload_67890.reIndex();
```

### 3. Backup Strategy

```bash
#!/bin/bash
# backup_mongodb.sh
BACKUP_DIR="/backup/mongodb"
DATE=$(date +%Y%m%d_%H%M%S)

# Create backup
mongodump --host localhost:27018 \
          --username ctrlnods_app \
          --password your-secure-app-password \
          --authenticationDatabase ctrlNods \
          --db ctrlNods \
          --out $BACKUP_DIR/ctrlnods_$DATE

# Compress backup
tar -czf $BACKUP_DIR/ctrlnods_$DATE.tar.gz $BACKUP_DIR/ctrlnods_$DATE
rm -rf $BACKUP_DIR/ctrlnods_$DATE

# Keep only last 30 days of backups
find $BACKUP_DIR -name "ctrlnods_*.tar.gz" -mtime +30 -delete
```

## 🔍 Troubleshooting

### Common Issues:

1. **Connection Refused**:
   ```bash
   # Check MongoDB status
   sudo systemctl status mongod

   # Check logs
   tail -f /var/log/mongodb/mongod.log
   ```

2. **Authentication Failed**:
   ```bash
   # Verify user exists
   mongosh --port 27018
   use admin
   db.auth("admin", "password")
   db.getUsers()
   ```

3. **Performance Issues**:
   ```javascript
   // Check slow queries
   db.setProfilingLevel(2, { slowms: 1000 });
   db.system.profile.find().limit(5).sort({ ts: -1 });
   ```

## 🔧 ctrlClus Configuration Integration

Update your `config.php` file:

```php
<?php
return [
    'mongodb' => [
        'host' => 'your-mongodb-host',
        'port' => 27018,
        'database' => 'ctrlNods',
        'username' => 'ctrlnods_app',
        'password' => 'your-secure-app-password',
        'auth_source' => 'ctrlNods',
        'auth_mechanism' => 'SCRAM-SHA-1',
    ],
    // ... rest of configuration
];
```

## 🧪 Testing Database Setup

### 1. Test MongoDB Connection

Create a test script `test_mongodb.php`:

```php
<?php
require 'lib/vendor/autoload.php';

use MongoDB\Client;

// Load configuration
$config = include 'config.php';
$mongodb_config = $config['mongodb'];

// Build connection string
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

try {
    echo "Testing MongoDB connection...\n";

    $mongo = new Client($connection_string);
    $db = $mongo->selectDatabase($mongodb_config['database']);

    // Test connection
    $result = $db->command(['ping' => 1]);
    echo "✅ MongoDB connection successful!\n";

    // Test collections
    $collections = ['events', 'token', 'clusters'];
    foreach ($collections as $collection) {
        $count = $db->$collection->countDocuments();
        echo "✅ Collection '$collection': $count documents\n";
    }

    // Test upload collection (if exists)
    $uploadCollections = $db->listCollections(['filter' => ['name' => ['$regex' => '^upload_']]]);
    foreach ($uploadCollections as $collection) {
        $name = $collection->getName();
        $count = $db->$name->countDocuments();
        echo "✅ Upload collection '$name': $count documents\n";
    }

} catch (Exception $e) {
    echo "❌ MongoDB connection failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
```

Run the test:
```bash
php test_mongodb.php
```

### 2. Test Upload Functionality

```bash
# Test token creation
curl -X POST http://your-server/upload_FILE.php \
     -F "token=sample-token-12345" \
     -F "files[]=@test_data.json"
```

Sample `test_data.json`:
```json
[
  {
    "id": "node1",
    "now": "2025-01-15T10:30:00Z",
    "service": "cassandra",
    "FROM_IP": "192.168.1.10",
    "status": "UP",
    "cpu_usage": 45.2,
    "memory_usage": 67.8
  },
  {
    "id": "node2",
    "now": "2025-01-15T10:30:00Z",
    "service": "cassandra",
    "FROM_IP": "192.168.1.11",
    "status": "UP",
    "cpu_usage": 52.1,
    "memory_usage": 71.3
  }
]
```

## 📚 Advanced Configuration

### 1. Replica Set Setup (Production)

```bash
# Initialize replica set
mongosh --port 27018
rs.initiate({
  _id: "ctrlnods-rs",
  members: [
    { _id: 0, host: "mongodb1:27018" },
    { _id: 1, host: "mongodb2:27018" },
    { _id: 2, host: "mongodb3:27018" }
  ]
})
```

### 2. Monitoring with MongoDB Ops Manager

Add to `/etc/mongod.conf`:
```yaml
monitoring:
  enabled: true
  host: "ops-manager.your-domain.com"
```

### 3. Custom Aggregation Pipelines

```javascript
// Complex cluster health analysis
db.upload_67890.aggregate([
  {
    $match: {
      "now": { $gte: new Date(Date.now() - 3600000) } // Last hour
    }
  },
  {
    $group: {
      _id: {
        node: "$FROM_IP",
        service: "$service"
      },
      avg_cpu: { $avg: "$cpu_usage" },
      avg_memory: { $avg: "$memory_usage" },
      event_count: { $sum: 1 },
      latest_status: { $last: "$status" }
    }
  },
  {
    $match: {
      $or: [
        { "avg_cpu": { $gt: 80 } },
        { "avg_memory": { $gt: 85 } },
        { "latest_status": { $ne: "UP" } }
      ]
    }
  }
]);
```

---

## About the Author

**Giorgio Chessari** - Senior Database Administrator & Enterprise Infrastructure Specialist

With 15+ years of experience managing enterprise-scale database infrastructure, Giorgio has specialized in designing monitoring solutions for mission-critical environments, including financial services and payment processing systems. Expert in Cassandra clusters, MongoDB, Redis with Sentinel, and distributed database architectures.

🌐 **Professional Portfolio**: [giorgio.chessari.it](http://giorgio.chessari.it) - Enterprise Database Architecture & Consulting

---

**📚 Related Documentation**:
- [MongoDB Official Documentation](https://docs.mongodb.com/)
- [ctrlClus Installation Guide](./SETUP.md)
- [ctrlNods Agent Setup](https://github.com/gioches/ctrlNods)
- [Configuration Template](./config.template.php)
- [Professional Database Solutions](http://giorgio.chessari.it)