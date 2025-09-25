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
    
    // Set up simple sorting
    setupSimpleSort();
    
    // TEST DEBUG
    console.log('🧪 JavaScript loaded, testing...');
    window.testAddHeaders = function() {
        console.log('🧪 Manual test function called');
        addSortHeaders();
    };
    
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
        const content = document.querySelector('.parte_log');
        content.innerHTML = '';
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
    
    // Set up date filter events
    console.log('🚀 Calling setupDateFilterEvents...');
    setupDateFilterEvents();
}

/**
 * Setup menu item click events
 */
function setupMenuItemEvents() {
    
    document.getElementById('all').addEventListener('click', function() {
        if (queryInProgress) return; // Prevent multiple queries
        const content = document.querySelector('.parte_log');
        content.innerHTML = '';
        currentQueryId = '1.1';
        document.querySelector('.content-title').textContent = 'All data';
       // addTerminalMessage(`Executing query 1.1: Latest Events per Node`);
        
        // Execute the query
        executeQuery('all', currentQueryId, {
            cluster_id: currentCluster,
            limit: 10,
            includeStatus: true
        });
    });
    
    
    
    
    // Latest Events per Node (1.1)
    document.getElementById('latest-events').addEventListener('click', function() {
        if (queryInProgress) return; // Prevent multiple queries
        const content = document.querySelector('.parte_log');
        content.innerHTML = '';
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
/*    document.getElementById('recurring-events').addEventListener('click', function() {
        if (queryInProgress) return; // Prevent multiple queries
        
        currentQueryId = '1.1.1';
        document.querySelector('.content-title').textContent = 'Recurring Events Analysis';
        addTerminalMessage(`Executing query 1.1.1: Recurring Events Analysis`);
        
        // Execute the query
        executeQuery('check_recurring_events', currentQueryId, {
            cluster_id: currentCluster,
            lookbackDays: 30
        });
    }); */
    
    // Events in Same Period (1.1.2)
    document.getElementById('period-events').addEventListener('click', function() {
        if (queryInProgress) return; // Prevent multiple queries
        const content = document.querySelector('.parte_log');
        content.innerHTML = '';
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
 * Setup date filter events
 */
function setupDateFilterEvents() {
    console.log('🔧 setupDateFilterEvents called!');
    
    const dateInput = document.getElementById('date-filter-input');
    const directionSelect = document.getElementById('date-filter-direction');
    const applyBtn = document.getElementById('apply-date-filter');
    const clearBtn = document.getElementById('clear-date-filter');
    const dateSection = document.querySelector('.date-filter-section');
    
    console.log('🔍 Elements found:', {
        dateInput: !!dateInput,
        directionSelect: !!directionSelect,  
        applyBtn: !!applyBtn,
        clearBtn: !!clearBtn,
        dateSection: !!dateSection
    });
    
    if (!applyBtn) {
        console.error('❌ ERRORE: Pulsante apply-date-filter non trovato!');
        alert('ERRORE: Pulsante Applica non trovato nel DOM!');
        return;
    }
    
    // Apply date filter
    applyBtn.addEventListener('click', function() {
        console.log('🔴 CLICK su pulsante Applica rilevato!');
        
        const filterDate = dateInput.value;
        const direction = directionSelect.value;
        
        console.log('📅 Valori letti:', { filterDate, direction });
        
        if (!filterDate) {
            alert('Seleziona una data per applicare il filtro');
            return;
        }
        
        applyDateFilter(filterDate, direction);
        
        // Visual feedback
        dateSection.classList.add('date-filter-active');
        
        // Add info text
        updateDateFilterInfo(filterDate, direction);
    });
    
    // Clear date filter
    clearBtn.addEventListener('click', function() {
        clearDateFilter();
        dateInput.value = '';
        dateSection.classList.remove('date-filter-active');
        
        // Remove info text
        const existingInfo = dateSection.querySelector('.date-filter-info');
        if (existingInfo) {
            existingInfo.remove();
        }
    });
}

/**
 * Apply date filter to the current table
 * @param {string} filterDate - Date in YYYY-MM-DDTHH:MM format
 * @param {string} direction - 'future' or 'past'
 */
function applyDateFilter(filterDate, direction) {
    console.log('🚨 CHIAMATA applyDateFilter:', filterDate, direction);
    
    const table = document.getElementById('results-table');
    const tbody = table.querySelector('tbody');
    
    if (!tbody) {
        alert('Nessuna tabella da filtrare. Carica prima i dati.');
        return;
    }
    
    // Escludiamo tutte le righe che contengono filtri (select dropdown)
    const allRows = Array.from(tbody.rows);
    const rows = allRows.filter(row => {
        // Controlliamo se QUALSIASI cella contiene una select - se sì, è una riga filtri
        for (let i = 0; i < row.cells.length; i++) {
            if (row.cells[i] && row.cells[i].querySelector('select')) {
                console.log(`🚫 Esclusa riga ${i} - contiene filtri`);
                return false; // Escludi questa riga
            }
        }
        return true; // Includi questa riga
    });
    
    console.log(`🔍 Righe filtrate: ${rows.length} su ${allRows.length} totali (escluse ${allRows.length - rows.length} righe filtro)`);
    
    const headers = Array.from(table.querySelectorAll('thead th')).map(th => th.textContent.trim());
    // L'utente ha specificato che Now è la quarta colonna (indice 3)
    const nowColumnIndex = 3;
    
    console.log('🐛 DEBUG applyDateFilter:', {
        filterDate,
        direction,
        rowsCount: rows.length,
        headers: headers,
        nowColumnIndex: nowColumnIndex,
        headerCount: headers.length,
        nowColumnHeader: headers[nowColumnIndex] || 'UNDEFINED'
    });
    
    // Debug dettagliato degli headers
    console.log('📋 Headers dettagliati:');
    headers.forEach((header, index) => {
        console.log(`  ${index}: "${header}" ${index === nowColumnIndex ? '← COLONNA NOW (pos 4)!' : ''}`);
    });
    
    if (nowColumnIndex >= headers.length) {
        console.error('❌ Indice colonna Now (3) supera il numero di headers:', headers.length);
        alert(`Colonna Now (indice 3) non esiste. Headers disponibili: ${headers.length}`);
        return;
    }
    
    // Convert filter date to comparison format
    const filterDateTime = new Date(filterDate);
    let visibleCount = 0;
    let totalCount = rows.length;
    
    console.log('🗓️ Applying date filter:', {
        filterDate: filterDate,
        direction: direction,
        filterDateTime: filterDateTime,
        nowColumnIndex: nowColumnIndex,
        totalRows: totalCount
    });
    
    rows.forEach((row, index) => {
        // Debug della prima riga per capire la struttura
        if (index === 0) {
            console.log('🔍 Prima riga - struttura celle:');
            for (let i = 0; i < row.cells.length; i++) {
                const cell = row.cells[i];
                const content = cell ? cell.textContent.trim().substring(0, 20) : 'NULL';
                console.log(`  Cella ${i}: "${content}"`);
            }
        }
        
        const nowCell = row.cells[nowColumnIndex];
        if (!nowCell) {
            console.error(`❌ Row ${index + 1}: nowCell is null! (cells: ${row.cells.length}, nowIndex: ${nowColumnIndex})`);
            if (index < 3) { // Log HTML solo per le prime 3 righe
                console.error('❌ Row HTML:', row.outerHTML);
            }
            // Skip questa riga ma continuiamo con le altre
            return;
        }
        
        const nowValue = nowCell.textContent.trim();
        const nowDateTime = new Date(nowValue);
        
        // Verifica se la data è valida
        if (isNaN(nowDateTime.getTime())) {
            console.warn(`⚠️ Row ${index + 1}: Invalid date "${nowValue}"`);
            return;
        }
        
        let showRow = false;
        
        if (direction === 'future') {
            // Show records from filter date towards future (>= filter date)
            showRow = nowDateTime >= filterDateTime;
        } else {
            // Show records from filter date towards past (<= filter date)
            showRow = nowDateTime <= filterDateTime;
        }
        
        if (showRow) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
        
        // Debug per prime 3 righe
        if (index < 3) {
            console.log(`🔍 Row ${index + 1}: ${nowValue} → ${showRow ? 'VISIBLE' : 'HIDDEN'}`);
        }
    });
    
    console.log(`🔍 Date filter applied: ${visibleCount}/${totalCount} rows visible`);
    
    // DISABILITATO: Non aggiorniamo i filtri per ora per evitare interferenze
    // setTimeout(() => {
    //     updateFiltersForVisibleRows();  
    // }, 100);
}

/**
 * Clear date filter from the current table
 */
function clearDateFilter() {
    const table = document.getElementById('results-table');
    const tbody = table.querySelector('tbody');
    
    if (!tbody) return;
    
    const rows = Array.from(tbody.rows);
    rows.forEach(row => {
        row.style.display = '';
    });
    
    console.log('🗓️ Date filter cleared, showing all rows');
    
    // Update filter dropdowns to reflect all data
    setTimeout(() => {
        updateFiltersForVisibleRows();
    }, 100);
}

/**
 * Update filter dropdowns based on currently visible rows
 */
function updateFiltersForVisibleRows() {
    // This will trigger the existing filter system to update
    const table = document.getElementById('results-table');
    const filtersRow = table.querySelector('thead tr:first-child');
    
    if (filtersRow) {
        // Trigger the existing filter update mechanism
        const event = new Event('change');
        const firstSelect = filtersRow.querySelector('select');
        if (firstSelect) {
            // This will trigger the existing updateSelectOptions logic
            firstSelect.dispatchEvent(event);
        }
    }
}

/**
 * Update date filter info display
 * @param {string} filterDate 
 * @param {string} direction 
 */
function updateDateFilterInfo(filterDate, direction) {
    const dateSection = document.querySelector('.date-filter-section');
    
    // Remove existing info
    const existingInfo = dateSection.querySelector('.date-filter-info');
    if (existingInfo) {
        existingInfo.remove();
    }
    
    // Add new info
    const info = document.createElement('div');
    info.className = 'date-filter-info';
    
    const displayDate = new Date(filterDate).toLocaleString('it-IT');
    const directionText = direction === 'future' ? 'dal futuro' : 'dal passato';
    
    info.textContent = `Active filter: ${displayDate} ${directionText}`;
    dateSection.appendChild(info);
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
    let url = `ajax.php?action=${action}&query_id=${queryId}`;
    
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
                updateResultsTable(data.data.results, data.data.headers);
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
    
    // DEBUG: Log per state=0
    console.log('=== updateResultsTable DEBUG ===');
    console.log('Total results:', results.length);
    const stateZeroCount = results.filter(r => r.State === 0).length;
    const stateOneCount = results.filter(r => r.State === 1).length;
    console.log('State=0 count:', stateZeroCount);
    console.log('State=1 count:', stateOneCount);
    
    // Clear existing table
    table.innerHTML = '';
    
    // Create table header
    const thead = document.createElement('thead');
    const headerRow = document.createElement('tr');
    
    // Add headers
    headers.forEach(header => {
        const th = document.createElement('th');
        th.textContent = header;
        //th.innerHTML  = '<select value="'+header+'" />';
        headerRow.appendChild(th);
    });
    
    thead.appendChild(headerRow);
    table.appendChild(thead);
    
    // Create table body
    const tbody = document.createElement('tbody');
    
    // Add results
    results.forEach((result, index) => {
        const row = document.createElement('tr');
        
        // Add data cells
        headers.forEach(header => {
            const td = document.createElement('td');
            // Fix: gestisce correttamente state=0 e altri valori falsy
            let value = (result[header] !== undefined && result[header] !== null) ? result[header] : '';
            
            // Firefox-specific fix: assicura conversione esplicita per numeri
            if (typeof value === 'number') {
                value = String(value);
            }
            
            td.textContent = value;
            
            // DEBUG specifico per State
            if (header === 'State' && result[header] === 0) {
                console.log(`Row ${index}: State=0 -> processed as "${value}" -> textContent="${td.textContent}"`);
            }
            
            row.appendChild(td);
        });
        
        tbody.appendChild(row);
    });
    
    table.appendChild(tbody);
    
    // Animate table appearance
    table.style.opacity = '0';
    setTimeout(() => {
          const container = document.getElementById("contenuto");
          container.scrollTo({ top: 0, behavior: 'smooth' });
        table.style.opacity = '1';
        creaFiltri(table);
        
        //applyFilters();
        
    }, 300);
    //table.scrollIntoView({ behavior: "smooth", block: "start" });
    
     
    
    
}

function creaFiltri(table) {
  const tbody = table.querySelector('tbody');
  const thead = table.querySelector('thead') || table.createTHead();
  const filtersRow = thead.insertRow(0);
  const rows = Array.from(tbody.rows);
  const colCount = rows[0].cells.length;
  const selects = [];

  // Funzione: crea una select per una colonna (con supporto multiselezione per IP)
  function createSelect(colIndex) {
    // Determina se questa colonna contiene IP (colonne 5 e 7 dai log precedenti)
    const isIPColumn = colIndex === 5 || colIndex === 7; // fromip, toip
    
    if (isIPColumn) {
      // Crea select multipla per IP
      const select = document.createElement('select');
      select.dataset.col = colIndex;
      select.multiple = true;
      select.size = 3; // Mostra 3 opzioni visibili
      select.style.cssText = `
        width: 100%;
        height: 60px;
        font-size: 11px;
        background: var(--bg-color);
        color: var(--terminal-text-color);
        border: 1px solid var(--border-color);
      `;
      
      const th = document.createElement('th');
      th.appendChild(select);
      filtersRow.appendChild(th);
      selects[colIndex] = select;

      select.addEventListener('change', () => {
        applyFilters(colIndex);
      });
      
      console.log(`📍 Created multi-select for IP column ${colIndex}`);
    } else {
      // Select normale per altre colonne
      const select = document.createElement('select');
      select.dataset.col = colIndex;

      const th = document.createElement('th');
      th.appendChild(select);
      filtersRow.appendChild(th);
      selects[colIndex] = select;

      select.addEventListener('change', () => {
        applyFilters(colIndex);
      });
    }
  }

  // Funzione: ottieni valori unici visibili in una colonna
  function getUniqueValues(colIndex, filteredRows) {
    const values = new Set();
    for (let row of filteredRows) {
      let cellValue = row.cells[colIndex].textContent;
      
      // Firefox-specific fix: normalizza valori numerici
      if (cellValue === '0' || cellValue === 0) {
        cellValue = '0'; // Forza stringa per consistenza
      } else if (cellValue === '1' || cellValue === 1) {
        cellValue = '1'; // Forza stringa per consistenza
      }
      
      values.add(cellValue);
      
      // DEBUG per colonna State (assumendo che sia colonna 9)
      if (colIndex === 9) {
        console.log(`Filter debug - Row cell value: "${cellValue}" (normalized)`);
      }
    }
    
    const uniqueValues = Array.from(values);
    
    // DEBUG per colonna State
    if (colIndex === 9) {
      console.log(`State column unique values:`, uniqueValues);
    }
    
    return uniqueValues;
  }

  // Funzione: aggiorna le opzioni di tutte le select (tranne quella modificata)
  function updateSelectOptions(excludeCol) {
    const visibleRows = rows.filter(row => row.style.display !== 'none');
    selects.forEach((select, colIndex) => {
      if (colIndex === excludeCol) return;

      const uniqueValues = getUniqueValues(colIndex, visibleRows);

      if (select.multiple) {
        // Gestione select multipla (IP)
        const currentlySelected = Array.from(select.selectedOptions).map(opt => opt.value);
        
        // Ricrea le opzioni
        select.innerHTML = '';
        
        uniqueValues.forEach(value => {
          const opt = document.createElement('option');
          opt.value = value;
          opt.textContent = value;
          // Mantieni selezione se ancora valida
          if (currentlySelected.includes(value)) {
            opt.selected = true;
          }
          select.appendChild(opt);
        });
        
        console.log(`📍 Updated IP filter column ${colIndex}: ${uniqueValues.length} options available`);
      } else {
        // Gestione select normale
        const currentValue = select.value;
        
        // Ricrea le opzioni
        select.innerHTML = '';
        const allOption = document.createElement('option');
        allOption.value = '';
        allOption.textContent = 'Tutti';
        select.appendChild(allOption);

        uniqueValues.forEach(value => {
          const opt = document.createElement('option');
          opt.value = value;
          opt.textContent = value;
          select.appendChild(opt);
        });

        // Ripristina selezione se ancora valida
        if (uniqueValues.includes(currentValue)) {
          select.value = currentValue;
        } else {
          select.value = '';
        }
      }
    });
  }

  // Funzione: applica i filtri correnti
  function applyFilters(triggerCol = null) {
    rows.forEach(row => {
      row.style.display = '';
      for (let i = 0; i < colCount; i++) {
        const select = selects[i];
        const cellValue = row.cells[i].textContent;
        
        // Gestione multiselezione per colonne IP
        if (select.multiple) {
          const selectedValues = Array.from(select.selectedOptions).map(option => option.value);
          
          if (selectedValues.length > 0 && !selectedValues.includes(cellValue)) {
            row.style.display = 'none';
            break;
          }
          
          if (triggerCol === i) {
            console.log(`📍 IP filter column ${i}: selected = [${selectedValues.join(', ')}], cell = "${cellValue}"`);
          }
        }
        // Gestione normale per altre colonne
        else {
          const selected = select.value;
          if (selected && cellValue !== selected) {
            row.style.display = 'none';
            break;
          }
        }
      }
    });

    updateSelectOptions(triggerCol);
  }

  // Initialization: create a select for each column and populate
  for (let i = 0; i < colCount; i++) {
    createSelect(i);
  }

  applyFilters(); // popolamento iniziale
}


/*function creaFiltri()
{
    const table = document.getElementById("results-table");
        const thead = table.querySelector("thead");
        const tbody = table.querySelector("tbody");
        const numColonne = table.querySelectorAll("thead tr th").length;
  
        // Crea una nuova riga per i filtri e la inserisce come prima riga dell'header
        const filterRow = document.createElement("tr");

        // For each column, create a select with unique values found in tbody
        for (let colIndex = 0; colIndex < numColonne; colIndex++) {
          const th = document.createElement("th");
          const select = document.createElement("select");
          select.classList.add("myfiltro");

          // Primo option: "Tutti" (valore vuoto per indicare nessun filtro)
          const defaultOpt = document.createElement("option");
          defaultOpt.value = "";
          defaultOpt.text = "Tutti";
          select.appendChild(defaultOpt);

          // Usa un Set per memorizzare i valori univoci
          const uniqueValues = new Set();
          tbody.querySelectorAll("tr").forEach(row => {
            const cell = row.cells[colIndex];
            if (cell) {
              uniqueValues.add(cell.textContent.trim());
            }
          });

          // Ordina e aggiungi le opzioni univoche
          Array.from(uniqueValues).sort().forEach(val => {
            const opt = document.createElement("option");
            opt.value = val;
            opt.text = val;
            select.appendChild(opt);
          });

          // Quando viene cambiato il filtro, richiama la funzione di filtraggio
          select.addEventListener("change", function (event) {
            filterTable(tbody, filterRow);
          });
          th.appendChild(select);
          filterRow.appendChild(th);
        }

        // Inserisci la riga dei filtri all'inizio del thead
        thead.insertBefore(filterRow, thead.firstChild);

}

    function filterTable(tbody,filterRow) {
          // Get selected values for each column
          const filters = Array.from(filterRow.querySelectorAll("select")).map(select => select.value);
          // For each tbody row, evaluate whether to show or hide based on filters set
          tbody.querySelectorAll("tr").forEach(row => {
            let mostra = true;
            filters.forEach((filterValue, idx) => {
              if (filterValue !== "") { // Solo se è stato scelto un filtro
                const cellText = row.cells[idx].textContent.trim();
                if (cellText !== filterValue) {
                  mostra = false;
                }
              }
            });
            row.style.display = mostra ? "" : "none";
          });
        }
*/

/**
 * Load system overview data
 */
function loadSystemOverview() {
    // Reset the content title
    document.querySelector('.content-title').textContent = 'System Overview';
    
    // For now, use the default data that's already in the HTML
   // addTerminalMessage(`Loaded system overview for cluster: ${currentCluster}`);
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
    const content = document.querySelector('.parte_log');
    
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
    //content.insertBefore(terminal, table);
    content.appendChild(terminal);
    
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
    currentQueryId = '1.1';
        document.querySelector('.content-title').textContent = 'All data';
       // addTerminalMessage(`Executing query 1.1: Latest Events per Node`);
        
        // Execute the query
        executeQuery('all', currentQueryId, {
            cluster_id: currentCluster,
            limit: 10,
            includeStatus: true
        });
}

/**
 * Setup simple sorting - APPROCCIO SEMPLICE
 */
function setupSimpleSort() {
    // Osserva quando la tabella viene popolata
    const table = document.getElementById('results-table');
    if (!table) return;
    
    const observer = new MutationObserver(function() {
        addSortHeaders();
    });
    
    observer.observe(table, { childList: true, subtree: true });
}

/**
 * Aggiunge header ordinabili sopra i filtri
 */
function addSortHeaders() {
    const table = document.getElementById('results-table');
    const thead = table.querySelector('thead');
    if (!thead) return;
    
    // Verifica se abbiamo già gli header ordinabili
    if (thead.querySelector('.sort-header')) return;
    
    // Verifica se ci sono filtri (select)
    const filterRow = thead.querySelector('tr');
    if (!filterRow || !filterRow.querySelector('select')) return;
    
    console.log('📋 Aggiungendo header ordinabili...');
    
    // Conta le colonne dinamicamente dalla riga filtri
    const filterCells = filterRow.querySelectorAll('th');
    const columnCount = filterCells.length;
    console.log('📋 Colonne rilevate:', columnCount);
    
    // Nomi delle colonne (estesi per coprire tutte le colonne)
    const columnNames = [
        'Node ID', 'Status', 'Service', 'Now', 'Message', 'Details', 
        'Col 7', 'Col 8', 'Col 9', 'Col 10', 'Col 11', 'Col 12'
    ];
    
    // Prendi solo il numero di colonne necessarie
    const actualColumnNames = columnNames.slice(0, columnCount);
    
    // Crea nuova riga header sopra i filtri
    const headerRow = document.createElement('tr');
    headerRow.className = 'sort-headers';
    
    actualColumnNames.forEach((name, index) => {
        const th = document.createElement('th');
        th.className = 'sort-header';
        th.textContent = name;
        th.dataset.columnIndex = index;
        th.style.cursor = 'pointer';
        th.style.backgroundColor = 'var(--accent-color)';
        th.style.color = 'black';
        
        // Event listener per ordinamento
        th.addEventListener('click', function(event) {
            handleSort(index, th, event);
        });
        
        headerRow.appendChild(th);
    });
    
    // Inserisci come prima riga
    thead.insertBefore(headerRow, filterRow);
    
    console.log('✅ Header ordinabili aggiunti');
}

// Array per ordinamento multiplo: [{column: 0, direction: 'asc'}, {column: 2, direction: 'desc'}]
let multipleSorts = [];

/**
 * Gestisce il click su header per ordinamento MULTIPLO
 */
function handleSort(columnIndex, headerElement, event) {
    const isMultiple = event && (event.ctrlKey || event.metaKey);
    
    console.log('🔀 Sort header clicked:', {
        column: columnIndex,
        multiple: isMultiple,
        currentSorts: multipleSorts
    });
    
    if (!isMultiple) {
        // Click normale: RESET tutti gli ordinamenti tranne questo
        multipleSorts = [];
        clearAllSortVisuals();
    }
    
    // Trova ordinamento esistente per questa colonna
    const existingIndex = multipleSorts.findIndex(sort => sort.column === columnIndex);
    
    if (existingIndex !== -1) {
        // Colonna già ordinata: cicla ASC -> DESC -> REMOVE
        const existing = multipleSorts[existingIndex];
        if (existing.direction === 'asc') {
            existing.direction = 'desc';
            console.log(`🔄 Changed column ${columnIndex} to DESC`);
        } else {
            // Rimuovi questo ordinamento
            multipleSorts.splice(existingIndex, 1);
            console.log(`❌ Removed column ${columnIndex} from sort`);
        }
    } else {
        // Nuova colonna: aggiungi con ASC
        multipleSorts.push({ column: columnIndex, direction: 'asc' });
        console.log(`🆕 Added column ${columnIndex} as ASC`);
    }
    
    // Aggiorna visual
    updateSortVisuals();
    
    // Applica ordinamento
    applySortToTable();
}

/**
 * Rimuove tutti gli indicatori visivi
 */
function clearAllSortVisuals() {
    document.querySelectorAll('.sort-header').forEach(header => {
        header.classList.remove('sort-asc', 'sort-desc', 'sort-priority');
        header.removeAttribute('data-sort-priority');
        header.style.position = 'relative'; // Assicura position per numeri
    });
}

/**
 * Aggiorna indicatori visivi per ordinamento multiplo
 */
function updateSortVisuals() {
    clearAllSortVisuals();
    
    const allHeaders = document.querySelectorAll('.sort-header');
    
    multipleSorts.forEach((sort, priorityIndex) => {
        const header = allHeaders[sort.column];
        if (!header) return;
        
        // Aggiungi classe per freccia
        header.classList.add('sort-' + sort.direction);
        
        // Se ci sono più ordinamenti, aggiungi numero priorità
        if (multipleSorts.length > 1) {
            header.classList.add('sort-priority');
            header.setAttribute('data-sort-priority', priorityIndex + 1);
            
            // Aggiungi numero visibile come fallback
            header.style.position = 'relative';
            const prioritySpan = document.createElement('span');
            prioritySpan.className = 'priority-number';
            prioritySpan.textContent = priorityIndex + 1;
            prioritySpan.style.cssText = `
                position: absolute;
                top: 2px;
                right: 20px;
                background: #00ff00;
                color: black;
                border-radius: 50%;
                width: 16px;
                height: 16px;
                font-size: 10px;
                line-height: 16px;
                text-align: center;
                font-weight: bold;
            `;
            
            // Rimuovi numero precedente se esiste
            const existingNumber = header.querySelector('.priority-number');
            if (existingNumber) existingNumber.remove();
            
            header.appendChild(prioritySpan);
        }
        
        console.log(`🎨 Visual updated: column ${sort.column} = ${sort.direction} (priority ${priorityIndex + 1})`);
    });
}

/**
 * Applica l'ordinamento alla tabella
 */
function applySortToTable() {
    if (multipleSorts.length === 0) {
        console.log('🔄 No sorting applied');
        return;
    }
    
    const table = document.getElementById('results-table');
    const tbody = table.querySelector('tbody');
    if (!tbody) return;
    
    // Prendi solo le righe dati (non i filtri) - INCLUSE quelle nascoste dal filtro data
    const rows = Array.from(tbody.rows).filter(row => !row.querySelector('select'));
    
    console.log(`📋 Found ${rows.length} data rows for sorting`);
    
    console.log(`🔀 Sorting ${rows.length} rows by multiple columns:`, multipleSorts);
    
    // Ordina con logica multipla: priorità nell'ordine dell'array
    rows.sort((a, b) => {
        // Prova ogni ordinamento nell'ordine di priorità
        for (let sort of multipleSorts) {
            const cellA = a.cells[sort.column];
            const cellB = b.cells[sort.column];
            
            if (!cellA || !cellB) continue;
            
            const comparison = compareValues(cellA, cellB);
            
            if (comparison !== 0) {
                return sort.direction === 'desc' ? -comparison : comparison;
            }
        }
        return 0; // Tutte uguali
    });
    
    // SOLUZIONE: Prima rimuovi tutte le righe dati, poi reinserisci nell'ordine corretto
    rows.forEach(row => row.remove());
    
    // Poi reinserisci nell'ordine ordinato
    rows.forEach(row => tbody.appendChild(row));
    
    console.log('✅ Rows reordered in DOM');
    
    console.log('✅ Sorting applied');
}

/**
 * Confronta i valori di due celle per l'ordinamento
 */
function compareValues(cellA, cellB) {
    const textA = cellA.textContent.trim();
    const textB = cellB.textContent.trim();
    
    // Prova prima a interpretare come indirizzo IP (formato XXX.XXX.XXX.XXX)
    if (textA.match(/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/) && textB.match(/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/)) {
        const ipToNumber = (ip) => {
            return ip.split('.').reduce((acc, octet) => (acc << 8) + parseInt(octet), 0) >>> 0;
        };
        const ipA = ipToNumber(textA);
        const ipB = ipToNumber(textB);
        console.log(`🌐 IP comparison: ${textA} (${ipA}) vs ${textB} (${ipB})`);
        return ipA - ipB;
    }
    // Poi prova come data (formato YYYY-MM-DD HH:MM:SS)
    else if (textA.match(/\d{4}-\d{2}-\d{2}/) && textB.match(/\d{4}-\d{2}-\d{2}/)) {
        const dateA = new Date(textA);
        const dateB = new Date(textB);
        if (!isNaN(dateA.getTime()) && !isNaN(dateB.getTime())) {
            console.log(`📅 Date comparison: ${textA} vs ${textB}`);
            return dateA.getTime() - dateB.getTime();
        } else {
            return textA.localeCompare(textB);
        }
    }
    // Poi prova come numero
    else {
        const numA = parseFloat(textA);
        const numB = parseFloat(textB);
        
        if (!isNaN(numA) && !isNaN(numB)) {
            return numA - numB;
        } else {
            return textA.localeCompare(textB);
        }
    }
}
