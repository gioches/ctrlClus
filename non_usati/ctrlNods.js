use ctrlClus

db.createCollection("anagrafe");
db.createCollection("events");
db.createCollection("token");
db.getCollection("upload_5f5c3").drop();
db.createCollection("upload_5f5c3");
