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
            const isVisible = menuItems.style.display !== 