// MongoDB Initialization Script for ctrlClus
// Run with: mongosh --port 31007 mongodb_init.js

print("🚀 ctrlClus MongoDB Initialization Script");
print("==========================================");

// Configuration
const ADMIN_USER = "admin";
const ADMIN_PASSWORD = "change-this-admin-password"; // CHANGE THIS!
const APP_USER = "ctrlnods_app";
const APP_PASSWORD = "change-this-app-password";   // CHANGE THIS!
const DATABASE_NAME = "ctrlNods";

try {
    print("\n📋 Step 1: Creating Admin User");
    db = db.getSiblingDB('admin');

    // Create admin user
    try {
        db.createUser({
            user: ADMIN_USER,
            pwd: ADMIN_PASSWORD,
            roles: [
                { role: "userAdminAnyDatabase", db: "admin" },
                { role: "readWriteAnyDatabase", db: "admin" },
                { role: "dbAdminAnyDatabase", db: "admin" }
            ]
        });
        print("✅ Admin user created successfully");
    } catch (error) {
        if (error.code === 51003) {
            print("⚠️  Admin user already exists");
        } else {
            throw error;
        }
    }

    print("\n🔐 Step 2: Authenticating as Admin");
    const authResult = db.auth(ADMIN_USER, ADMIN_PASSWORD);
    if (authResult) {
        print("✅ Admin authentication successful");
    } else {
        throw new Error("Admin authentication failed");
    }

    print("\n📊 Step 3: Creating Application Database and User");
    db = db.getSiblingDB(DATABASE_NAME);

    // Create application user
    try {
        db.createUser({
            user: APP_USER,
            pwd: APP_PASSWORD,
            roles: [
                { role: "readWrite", db: DATABASE_NAME },
                { role: "dbAdmin", db: DATABASE_NAME }
            ]
        });
        print("✅ Application user created successfully");
    } catch (error) {
        if (error.code === 51003) {
            print("⚠️  Application user already exists");
        } else {
            throw error;
        }
    }

    print("\n🗄️  Step 4: Creating Core Collections");

    // Events collection
    if (!db.getCollectionNames().includes("events")) {
        db.createCollection("events");
        print("✅ Created 'events' collection");
    } else {
        print("⚠️  'events' collection already exists");
    }

    // Token collection
    if (!db.getCollectionNames().includes("token")) {
        db.createCollection("token");
        print("✅ Created 'token' collection");
    } else {
        print("⚠️  'token' collection already exists");
    }

    // Clusters collection
    if (!db.getCollectionNames().includes("clusters")) {
        db.createCollection("clusters");
        print("✅ Created 'clusters' collection");
    } else {
        print("⚠️  'clusters' collection already exists");
    }

    print("\n📇 Step 5: Creating Indexes");

    // Events collection indexes
    db.events.createIndex({ "timestamp": 1 });
    db.events.createIndex({ "sender_ip": 1 });
    db.events.createIndex({ "id_azienda": 1 });
    db.events.createIndex({ "filename": 1 });
    print("✅ Created indexes for 'events' collection");

    // Token collection indexes
    try {
        db.token.createIndex({ "token": 1 }, { "unique": true });
        print("✅ Created unique index for 'token' collection");
    } catch (error) {
        if (error.code === 85) {
            print("⚠️  Unique index for 'token' already exists");
        } else {
            throw error;
        }
    }
    db.token.createIndex({ "idazienda": 1 });

    // Clusters collection indexes
    try {
        db.clusters.createIndex(
            { "IDCLUSTER": 1, "idazienda": 1 },
            { "unique": true }
        );
        print("✅ Created unique index for 'clusters' collection");
    } catch (error) {
        if (error.code === 85) {
            print("⚠️  Unique index for 'clusters' already exists");
        } else {
            throw error;
        }
    }
    db.clusters.createIndex({ "ambiente": 1 });
    db.clusters.createIndex({ "last_update": 1 });

    print("\n🧪 Step 6: Inserting Test Data");

    // Insert sample token
    const sampleToken = {
        "token": "test-token-67890",
        "idazienda": "67890",
        "company_name": "Test Company",
        "created": new Date(),
        "active": true,
        "description": "Sample token for testing ctrlClus setup"
    };

    const existingToken = db.token.findOne({ "token": sampleToken.token });
    if (!existingToken) {
        db.token.insertOne(sampleToken);
        print("✅ Inserted sample token: test-token-67890");
    } else {
        print("⚠️  Sample token already exists");
    }

    // Insert sample cluster
    const sampleCluster = {
        "IDCLUSTER": "cluster_67890",
        "idazienda": "67890",
        "ambiente": "test",
        "nome": "Test Cluster (67890)",
        "note": "Auto-generated during ctrlClus setup",
        "last_update": new Date(),
        "upload_collection": "upload_67890"
    };

    const existingCluster = db.clusters.findOne({
        "IDCLUSTER": sampleCluster.IDCLUSTER,
        "idazienda": sampleCluster.idazienda
    });
    if (!existingCluster) {
        db.clusters.insertOne(sampleCluster);
        print("✅ Inserted sample cluster: cluster_67890");
    } else {
        print("⚠️  Sample cluster already exists");
    }

    // Create sample upload collection
    const uploadCollectionName = "upload_67890";
    if (!db.getCollectionNames().includes(uploadCollectionName)) {
        db.createCollection(uploadCollectionName);
        print("✅ Created sample upload collection: " + uploadCollectionName);

        // Add indexes to upload collection
        db[uploadCollectionName].createIndex(
            { "id": 1, "now": 1, "service": 1, "FROM_IP": 1 },
            {
                "unique": true,
                "name": "unique_node_time_service_ip",
                "background": true,
                "sparse": true
            }
        );
        db[uploadCollectionName].createIndex({ "idupload": 1 });
        db[uploadCollectionName].createIndex({ "now": 1 });
        db[uploadCollectionName].createIndex({ "FROM_IP": 1 });
        db[uploadCollectionName].createIndex({ "service": 1 });
        print("✅ Created indexes for upload collection");
    } else {
        print("⚠️  Sample upload collection already exists");
    }

    print("\n🔍 Step 7: Verification");

    // Verify collections
    const collections = db.getCollectionNames();
    print("📊 Available collections: " + collections.join(", "));

    // Count documents
    print("📈 Document counts:");
    print("   - events: " + db.events.countDocuments());
    print("   - token: " + db.token.countDocuments());
    print("   - clusters: " + db.clusters.countDocuments());
    print("   - upload_67890: " + db.upload_67890.countDocuments());

    print("\n🎉 SUCCESS: MongoDB initialization completed!");
    print("\n📋 Next Steps:");
    print("1. Update config.php with these credentials:");
    print("   - Host: your-mongodb-host");
    print("   - Port: 31007");
    print("   - Database: " + DATABASE_NAME);
    print("   - Username: " + APP_USER);
    print("   - Password: " + APP_PASSWORD);
    print("2. Test the configuration with: php scripts/test_mongodb.php");
    print("3. Upload test data using: curl -X POST -F 'token=test-token-67890' -F 'files[]=@test.json' http://your-server/upload_FILE.php");

} catch (error) {
    print("\n❌ ERROR: " + error.message);
    print("Stack trace: " + error.stack);
    print("\n🔧 Troubleshooting:");
    print("1. Ensure MongoDB is running: sudo systemctl status mongod");
    print("2. Check MongoDB logs: tail -f /var/log/mongodb/mongod.log");
    print("3. Verify port 31007 is accessible: netstat -tlnp | grep 31007");
    print("4. Update passwords in this script before running");
}