# ctrlClus Setup Guide

## Prerequisites

- PHP 7.4 or higher
- MongoDB 4.0 or higher
- Composer
- Web server (Apache/Nginx)

## Installation Steps

### 1. Clone and Setup

```bash
git clone <your-repository-url> ctrlClus
cd ctrlClus
```

### 2. Install Dependencies

```bash
# Install main dependencies
composer install

# Install upload-specific dependencies
cd lib
composer install
cd ..
```

### 3. Set Up MongoDB Database

**📋 Complete MongoDB Setup Guide**: [MONGODB_SETUP.md](./MONGODB_SETUP.md)

**Important**: Follow the complete MongoDB setup guide before proceeding with application configuration.

Quick setup summary:
```bash
# Install MongoDB (Ubuntu/Debian)
sudo apt install -y mongodb-org

# Configure MongoDB
sudo nano /etc/mongod.conf
# Set port: 31007, enable authentication

# Start MongoDB
sudo systemctl start mongod
sudo systemctl enable mongod

# Create database and users (see MONGODB_SETUP.md for complete script)
mongosh --port 31007
```

### 4. Configure Application

```bash
# Copy configuration template
cp config.template.php config.php

# Edit config.php with your MongoDB credentials
nano config.php
```

### 5. MongoDB Configuration Details

Update `config.php` with your MongoDB settings:

```php
'mongodb' => [
    'host' => 'your-mongodb-host',
    'port' => 31007,
    'database' => 'ctrlNods',
    'username' => 'your-username',
    'password' => 'your-password',
    'auth_source' => 'admin',
    'auth_mechanism' => 'SCRAM-SHA-1',
],
```

### 5. Configure Clusters

Define your cluster configurations in `config.php`:

```php
'clusters' => [
    'production' => [
        'name' => 'Production Environment',
        'environment' => 'prod',
        'upload_collection' => 'upload_12345',
        'events_collection' => 'events'
    ],
    'development' => [
        'name' => 'Development Environment',
        'environment' => 'dev',
        'upload_collection' => 'upload_67890',
        'events_collection' => 'events'
    ]
],
```

### 6. Set Permissions

```bash
# Ensure logs directory is writable
chmod 755 logs/
chown www-data:www-data logs/

# Set proper permissions for web files
chmod 644 *.php *.css *.js
chmod 755 .
```

### 7. Web Server Configuration

#### Apache Configuration

Create a virtual host or add to existing configuration:

```apache
<VirtualHost *:80>
    ServerName ctrlclus.yourdomain.com
    DocumentRoot /path/to/ctrlClus

    <Directory /path/to/ctrlClus>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/ctrlclus_error.log
    CustomLog ${APACHE_LOG_DIR}/ctrlclus_access.log combined
</VirtualHost>
```

#### Nginx Configuration

```nginx
server {
    listen 80;
    server_name ctrlclus.yourdomain.com;
    root /path/to/ctrlClus;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### 8. Database Setup

Ensure your MongoDB has the required collections:

```javascript
// Connect to MongoDB
use ctrlNods;

// Create collections
db.createCollection("events");
db.createCollection("token");
db.createCollection("clusters");

// Create indexes for better performance
db.events.createIndex({"timestamp": 1});
db.token.createIndex({"token": 1}, {"unique": true});
```

### 9. Create Upload Tokens

Add authentication tokens for upload access:

```javascript
// Insert upload token
db.token.insertOne({
    "token": "your-secure-token-here",
    "idazienda": "12345",
    "created": new Date(),
    "active": true
});
```

### 10. Test Installation

1. **Access main interface**: `http://yourdomain.com/`
2. **Test upload interface**: `http://yourdomain.com/upload_FILE.php`
3. **Upload test data**:
   ```bash
   curl -X POST -F "token=your-token" -F "files[]=@test.json" \
     http://yourdomain.com/upload_FILE.php
   ```

## Security Considerations

- Never commit `config.php` with real credentials
- Use strong tokens for upload authentication
- Enable HTTPS in production
- Consider implementing IP restrictions
- Regularly rotate database passwords
- Monitor logs for suspicious activity

## Troubleshooting

### Common Issues

1. **Connection refused**: Check MongoDB connection details
2. **Permission denied**: Verify file permissions and web server user
3. **Composer errors**: Ensure PHP extensions are installed
4. **Upload failures**: Check token validity and file permissions

### Debug Mode

Enable debug logging by adding to `config.php`:

```php
'debug' => [
    'enabled' => true,
    'log_file' => 'logs/debug.log'
]
```

### Log Files

- Application logs: `logs/`
- Web server logs: Check your web server configuration
- MongoDB logs: Check your MongoDB configuration

## Maintenance

- Regularly backup your MongoDB database
- Monitor disk space for log files
- Update dependencies periodically
- Review uploaded data for consistency

## Support

For issues and questions, refer to the documentation in the `DOC/` directory or create an issue in the repository.

---

## About the Author

**Giorgio Chessari** - Enterprise Database Administrator & Infrastructure Architect

Professional database consulting and enterprise solutions: [giorgio.chessari.it](http://giorgio.chessari.it)

*Specialized in Cassandra clusters, MongoDB, Redis with Sentinel, financial infrastructure, and mission-critical monitoring systems with 15+ years of enterprise experience.*