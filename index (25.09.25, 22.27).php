<?php
// Include configuration and required files
//require_once 'config.php';
//require_once 'query_schema.php';

// Initialize query schema to get available queries
//$querySchema = new QuerySchema();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ctrlClus Terminal Interface</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <header>
        <h1>ctrlClus</h1>
        <div class="header-controls">
            <div class="cluster-selector">
                <label for="cluster-select">Cluster:</label>
                <select id="cluster-select">
                    <?php
                    
                    $config = include('non_usati/config.php');
                    foreach ($config['clusters'] as $id => $cluster) {
                        echo '<option value="' . htmlspecialchars($id) . '">' . 
                             htmlspecialchars($cluster['name']) . '</option>';
                    }
                    ?>
                </select>
            </div>
            
            <div class="date-filter-section">
                <label for="date-filter-input">Date Filter:</label>
                <div class="date-filter-controls">
                    <input type="datetime-local" id="date-filter-input" class="date-input">
                    <select id="date-filter-direction" class="date-direction">
                        <option value="future">→ Future</option>
                        <option value="past">← Past</option>
                    </select>
                    <button id="apply-date-filter" class="date-filter-btn">Apply</button>
                    <button id="clear-date-filter" class="date-filter-btn clear">Remove</button>
                </div>
            </div>
            
            
            <div class="admin-links">
                <a href="manage_clusters.php" class="admin-link">
                    <span class="admin-icon">⚙</span>
                    Cluster Management
                </a>
            </div>
        </div>
    </header>
    
    <div class="menu">
        <div class="menu-category">
            <button id="panorama-btn">Panorama</button>
            <div class="menu-items">
                <div class="menu-item" id="latest-events">Latest Events per Node</div>
                <div class="submenu-items">
                    <div class="submenu-item" id="all">Recurring Events</div>
                    <div class="submenu-item" id="period-events">Events in Same Period</div>
                </div>
            </div>
        </div>
        <div class="menu-category">
            <button id="statistics-btn">Statistics</button>
            <div class="menu-items" style="display: none;">
                <div class="menu-item">Performance Metrics</div>
                <div class="menu-item">Resource Usage</div>
                <div class="menu-item">Error Rate</div>
            </div>
        </div>
        <div class="menu-category">
            <button id="nodes-btn">Nodes</button>
            <div class="menu-items" style="display: none;">
                <div class="menu-item">Node Status</div>
                <div class="menu-item">Configuration</div>
                <div class="menu-item">Connectivity</div>
            </div>
        </div>
        <div class="menu-category">
            <button id="logs-btn">Logs</button>
            <div class="menu-items" style="display: none;">
                <div class="menu-item">System Logs</div>
                <div class="menu-item">Application Logs</div>
                <div class="menu-item">Audit Logs</div>
            </div>
        </div>
    </div>
    
    <div class="content" id="contenuto">
        <div class="content-header">
            <div class="content-title">System Overview</div>
            <div class="content-meta">Last updated: <span id="current-time"></span></div>
        </div>
        <div class="parte_log">
            <!--<div class="terminal-loading">
                <div class="terminal-line">$ Initializing ctrlClus interface...</div>
            </div>

            <div class="terminal-loading">
                <div class="terminal-line">$ Loading cluster data...</div>
            </div> -->
        </div>    
        
        <table id="results-table">
           
        </table>
        
        <div class="blinking-cursor"></div>
    </div>
    
    <script src="script.js"></script>
</body>
</html>