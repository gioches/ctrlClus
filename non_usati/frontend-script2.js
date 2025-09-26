/**
 * ctrlClus Terminal Interface
 * Frontend JavaScript for handling user interactions and AJAX requests
 */

// Global variables
let currentCluster = 'cluster1';
let currentQueryId = '';
let queryInProgress = false;

// Document ready function
document.addEventListener('DOMContentLoaded', function() {
    // Initialize the interface
    initializeInterface();
    
    // Set up event listeners
    setupEventListeners();
    
    // Load initial data
    loadSystemOverview();
});

/**
 * Initialize the interface
 */
function initializeInterface() {
    // Update current time
    updateTime();
    setInterval(updateTime, 1000);
    
    // Display terminal startup animation
    simulateTerminalStartup();
    
    // Open the first menu category by default
    document.querySelector('.menu-items').style.display = 'block';
}

/**
 * Set up event listeners for the interface elements
 */
function setupEventListeners() {
    // Cluster selector
    document.getElementById('cluster-select').addEventListener('change', function() {
        currentCluster = this.value;
        addTerminalMessage(`Switching to cluster: ${this.options[this.selectedIndex].text}`);
        loadSystemOverview();
    });
    
    // Menu category buttons
    document.querySelectorAll('.menu-category > button').forEach(button => {
        button.addEventListener('click', function() {
            const menuItems = this.nextElementSibling;
            const isVisible = menuItems.style.display !== 'none';
            
            if (isVisible) {
                menuItems.style.display = 'none';
            } else {
                menuItems.style.display = 'block';
            }
        });
    });
    
    // Set up menu item click events
    setupMenuItemEvents();
}

/**
 * Setup menu item click events
 */
function setupMenuItemEvents() {
    // Latest Events per Node (1.1)
    document.getElementById('latest-events').addEventListener('click', function() {
        if (queryInProgress) return; // Prevent multiple queries
        
        currentQueryId = '1.1';
        document.querySelector('.content-title').textContent = 'Latest Events per Node';
        addTerminalMessage(`Executing query 1.1: Latest Events per Node`);
        
        // Execute the query
        executeQuery('get_latest_events', currentQueryId, {
            cluster_id: currentCluster,
            limit: 10,
            includeStatus: true
        });
    });
    
    // Recurring Events (1.1.1)
    document.getElementById('recurring-events').addEventListener('click', function() {
        if (queryInProgress) return; // Prevent multiple queries
        
        currentQueryId = '1.1.1';
        document.querySelector('.content-title').textContent = 'Recurring Events Analysis';
        addTerminalMessage(`Executing query 1.1.1: Recurring Events Analysis`);
        
        // Execute the query
        executeQuery('check_recurring_events', currentQueryId, {
            cluster_id: currentCluster,
            lookbackDays: 30
        });
    });
    
    // Events in Same Period (1.1.2)
    document.getElementById('period-events').addEventListener('click', function() {
        if (queryInProgress) return; // Prevent multiple queries
        
        currentQueryId = '1.1.2';
        document.querySelector('.content-title').textContent = 'Events in Same Period';
        addTerminalMessage(`Executing query 1.1.2: Events in Same Period`);
        
        // Get date range for last 24 hours
        const endDate = new Date().toISOString();
        const startDate = new Date(Date.now() - 86400000).toISOString(); // 24 hours ago
        
        // Execute the query
        executeQuery('get_events_in_period', currentQueryId, {
            cluster_id: currentCluster,
            startDate: startDate,
            endDate: endDate
        });
    });
}

/**
 * Execute a query and update the results table
 * 
 * @param {string} action The AJAX action to execute
 * @param {string} queryId The ID of the query
 * @param {object} params Additional parameters for the query
 */
function executeQuery(action, queryId, params = {}) {
    queryInProgress = true;
    
    // Show loading indicator
    showLoadingIndicator();
    
    // Build the URL with query parameters
    let url = `ajax_handler.php?action=${action}&query_id=${queryId}`;
    
    // Add additional parameters
    for (const [key, value] of Object.entries(params)) {
        url += `&${encodeURIComponent(key)}=${encodeURIComponent(value)}`;
    }
    
    // Make the AJAX request
    fetch(url)
        .then(response => response.json())
        .then(data => {
            // Hide loading indicator
            hideLoadingIndicator();
            
            // Handle the response
            if (data.status === 'success') {
                // Update the results table
                updateResultsTable(data.data.results, data.data.schema.outputFormat.headers);
                addTerminalMessage(`Query executed successfully. ${data.data.results.length} results found.`);
            } else {
                // Show error message
                showError(data.message);
            }
            
            queryInProgress = false;
        })
        .catch(error => {
            // Hide loading indicator
            hideLoadingIndicator();
            
            // Show error message
            showError(`Error executing query: ${error.message}`);
            queryInProgress = false;
        });
}

/**
 * Update the results table with the provided data
 * 
 * @param {array} results The results to display
 * @param {array} headers The table headers
 */
function updateResultsTable(results, headers) {
    const table = document.getElementById('results-table');
    
    // Clear existing table
    table.innerHTML = '';
    
    // Create table header
    const thead = document.createElement('thead');
    const headerRow = document.createElement('tr');
    
    // Add headers
    headers.forEach(header => {
        const th = document.createElement('th');
        th.textContent = header;
        headerRow.appendChild(th);
    });
    
    thead.appendChild(headerRow);
    table.appendChild(thead);
    
    // Create table body
    const tbody = document.createElement('tbody');
    
    // Add results
    results.forEach(result => {
        const row = document.createElement('tr');
        
        // Add data cells
        headers.forEach(header => {
            const td = document.createElement('td');
            td.textContent = result[header] || '';
            row.appendChild(td);
        });
        
        tbody.appendChild(row);
    });
    
    table.appendChild(tbody);
    
    // Animate table appearance
    table.style.opacity = '0';
    setTimeout(() => {
        table.style.opacity = '1';
    }, 100);
}

/**
 * Load system overview data
 */
function loadSystemOverview() {
    // Reset the content title
    document.querySelector('.content-title').textContent = 'System Overview';
    
    // For now, use the default data that's already in the HTML
    addTerminalMessage(`Loaded system overview for cluster: ${currentCluster}`);
}

/**
 * Show loading indicator
 */
function showLoadingIndicator() {
    const table = document.getElementById('results-table');
    table.style.opacity = '0.3';
    
    // Add terminal message
    addTerminalMessage('Query in progress...');
}

/**
 * Hide loading indicator
 */
function hideLoadingIndicator() {
    const table = document.getElementById('results-table');
    setTimeout(() => {
        table.style.opacity = '1';
    }, 300);
}

/**
 * Show error message
 * 
 * @param {string} message The error message to display
 */
function showError(message) {
    addTerminalMessage(`ERROR: ${message}`, 'error');
}

/**
 * Add a message to the terminal-like interface
 * 
 * @param {string} message The message to add
 * @param {string} type The type of message (info, error, etc.)
 */
function addTerminalMessage(message, type = 'info') {
    const content = document.querySelector('.content');
    const terminal = document.createElement('div');
    terminal.className = 'terminal-loading';
    
    const line = document.createElement('div');
    line.className = 'terminal-line';
    
    if (type === 'error') {
        line.style.color = '#ff4444';
    }
    
    line.textContent = `$ ${message}`;
    terminal.appendChild(line);
    
    // Insert before the table
    const table = document.getElementById('results-table');
    content.insertBefore(terminal, table);
    
    // Scroll to bottom
    content.scrollTop = content.scrollHeight;
}

/**
 * Update current time display
 */
function updateTime() {
    const now = new Date();
    const timeString = now.toLocaleTimeString();
    document.getElementById('current-time').textContent = timeString;
}

/**
 * Simulate terminal startup animation
 */
function simulateTerminalStartup() {
    // Already provided in the HTML
}
