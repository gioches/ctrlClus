# Vendor Directory Unification Analysis
Date: 2025-09-09

## Current Situation

### Two separate vendor directories:
1. **`/vendor`** (main)
   - Used by: `ajax.php`, `non_usati/ajax_handler.php`
   - Contains: mongodb, psr, composer, bin/
   - Autoloader: ComposerAutoloaderInitf57648cbbf97389505f669eeed361938
   - Older Composer version with PHP 5.6 compatibility check
   - composer.json with MyFrame namespace

2. **`/lib/vendor`** (secondary)
   - Used by: `upload.php`, `upload_FILE.php`, `upload_CURL.php`
   - Contains: mongodb, psr, composer
   - Autoloader: ComposerAutoloaderInit57ab227228ebee1e6af16d0a10b52b15
   - Newer and simplified version
   - Minimal composer.json

## Files using each vendor

### Using `/vendor/autoload.php`:
- ajax.php:2
- non_usati/ajax_handler.php:10

### Using `/lib/vendor/autoload.php`:
- upload.php:2
- upload_FILE.php:2
- upload_CURL.php:2

## Main Differences
1. Different autoloader hashes (possibly different versions)
2. `/vendor` has PHP version check
3. `/vendor` includes bin/ directory
4. Different composer.json files (main has PSR-4 autoload for MyFrame)

## Unification Plan

### Prerequisites:
1. Verify MongoDB driver versions in both
2. Complete backup of both directories
3. Test all involved files

### Procedure:
```bash
# 1. Backup
cp -r lib/vendor lib/vendor.backup
cp -r vendor vendor.backup

# 2. Check versions
composer show -d . mongodb/mongodb
composer show -d lib/ mongodb/mongodb

# 3. If compatible, update files:
# Modify in upload.php, upload_FILE.php, upload_CURL.php:
# FROM: require 'lib/vendor/autoload.php';
# TO:   require 'vendor/autoload.php';

# 4. Test functionality:
# - Test upload.php with curl
# - Test upload_FILE.php via web
# - Test ajax.php
```

### Risks:
- Possible incompatibilities between different package versions
- Breaking existing functionality if versions are not compatible
- Loss of specific configurations in lib/vendor

### Benefits:
- Centralized dependency management
- Simplified maintenance
- Disk space savings
- Single composer.json to maintain

## Recommendation:
Proceed with caution, testing each change. If everything is currently working,
consider keeping them separate until a dependency update is necessary.