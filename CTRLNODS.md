# ctrlNods - Node Monitoring Agent

**Version 1.20** | Nodes Control System

## 📋 Overview

ctrlNods is a Bash-based monitoring application designed to be installed on each node of a Cassandra cluster. It performs comprehensive health checks, logs state changes, and provides real-time monitoring capabilities with intelligent alerting.

## 🎯 Purpose

1. **Performs comprehensive checks** on node and cluster health
2. **Logs state changes** in real-time (ON/OFF, AVAILABLE/UNAVAILABLE, DOWN/UP, etc.)
3. **Saves events** to local SQLite database stored on RAM disk for optimal performance
4. **Synchronizes data** to central database for cluster-wide analysis by ctrlClus
5. **Sends alerts** via Teams chat, email, SMS depending on event criticality

## 🏗️ Architecture

### Core Components

```
ctrlNods/
├── SETUP & SYNC
│   ├── 00_POSTreboot.sh      # RAM disk setup after reboot
│   ├── 01_updatefs.sh        # Log file history management
│   └── 02_syncDB.sh          # Sync local DB to central database
│
├── APPLICATION CORE
│   ├── M_chk.sh              # Application launcher
│   ├── M_config.sh           # Configuration management
│   └── M_control.sh          # Main application logic
│
├── MONITORING MODULES
│   ├── /modules/generic/     # Generic system monitoring
│   ├── /modules/cassandra/   # Cassandra-specific monitoring
│   └── /modules/test/        # Test and diagnostic modules
│
├── DATA STORAGE
│   ├── /log/data.log         # Text-based event log
│   ├── /log/data.sqlite      # SQLite database for events
│   └── UP_$module_$node.ok   # Service status flag files
│
└── BINARY TOOLS
    └── /bin/                 # Required binaries (dd, ping, nmap, sqlite3, etc.)
```

### Data Flow

1. **Local Collection**: Each module collects metrics and detects state changes
2. **RAM Storage**: Events stored on tmpfs for high performance
3. **Central Sync**: Periodic synchronization to central database
4. **Analysis**: ctrlClus processes aggregated data for insights

## 📊 Monitoring Modules

### Generic System Modules

#### S_DISK.sh - I/O Disk Performance
- **Purpose**: Monitor disk I/O performance
- **Method**: `dd if=/dev/zero of=/opt/ctrlNods/testfile_100MB bs=1024 count=402400`
- **Alert**: Triggers when disk speed exceeds threshold

#### S_CPU.sh - CPU Usage Monitoring
- **Purpose**: Monitor Java process CPU usage
- **Method**: `top -b -n 1 | grep java | awk '{print $9}'`
- **Alert**: CPU usage > threshold indicates potential node overload

#### S_PING.sh - Network Connectivity
- **Purpose**: Monitor network connectivity between nodes
- **Method**: All nodes ping all other nodes in cluster
- **Alert**: Network connectivity issues between cluster nodes

#### S_NMAP.sh - Service Availability
- **Purpose**: Test critical Cassandra service ports
- **Ports Monitored**:
  - `7001` - Inter-node communication with SSL
  - `7199` - JMX (Java Management Extensions)
  - `9142` - CQL port for SSL/TLS connections

### Cassandra-Specific Modules

#### S_HINTS.sh - Hints File Monitoring
- **Purpose**: Monitor presence and age of hints files
- **Monitors**: Creation date, source node, keyspace, tables affected
- **Significance**: Hints indicate temporary node unavailability

#### S_QueryQueue.sh - Thread Pool Monitoring
- **Purpose**: Monitor query thread pool status
- **Method**: `nodetool tpstats | awk 'NR>1 && ($3+0 > 0 || $5+0 > 0)'`
- **Metrics**:
  - **Pending**: Queries waiting to be processed
  - **Blocked**: Blocked queries indicating high node load

#### S_Balancing.sh - Data Streaming Monitor
- **Purpose**: Monitor cluster balancing and data streaming
- **Method**: `nodetool netstats | grep -E 'Receiving from|Sending to'`
- **Alert**: Extended streaming operations indicate potential overload

#### S_CL_ClusterState.sh - Cluster State Monitor
- **Purpose**: Monitor overall cluster health
- **Method**: `nodetool status | grep -E '^D|^UJ|^UM|^UL'`
- **States Monitored**:
  - **D**: Down (node unreachable)
  - **UJ**: Joining (node joining cluster - problem if persistent)
  - **UM**: Moving (data migration - monitor duration)
  - **UL**: Leaving (node leaving cluster)

#### S_CL_QueryLatency.sh - Query Performance
- **Purpose**: Monitor query latency across percentiles
- **Method**: `nodetool proxyhistograms`
- **Analysis**: Tracks 50%, 75%, 95%, 99% percentiles for read/write/range operations
- **Thresholds**: Configurable latency limits with criticality detection

#### S_Partition.sh - Large Partition Detection
- **Purpose**: Identify oversized partitions affecting performance
- **Method**: `nodetool tablehistograms keyspace table` for all tables
- **Alert**: Partitions > configurable size threshold (e.g., XXMB)

#### S_MEM.sh - Memory & Garbage Collection
- **Purpose**: Monitor JVM memory management
- **Method**: `nodetool gcstats`
- **Metrics**:
  - **Min/Max/Mean**: GC operation duration
  - **Count**: Total GC operations
- **Alert**: GC times > 1 second indicate memory pressure

## 🔧 Configuration

### Installation Requirements
- Bash shell environment
- SQLite3
- Network tools (ping, nmap)
- Cassandra nodetool access
- Sufficient RAM for tmpfs mount

### Setup Process
1. **Install ctrlNods** on each cluster node
2. **Configure RAM disk** for high-performance logging
3. **Set up monitoring modules** based on cluster requirements
4. **Configure central database** connection for synchronization
5. **Enable alerting** channels (Teams, email, SMS)

## 📈 Performance Optimization

### RAM Disk Storage
- Events stored on tmpfs (RAM disk) for ultra-fast I/O
- Reduces disk contention on production systems
- Automatic persistence to central database

### Efficient Synchronization
- Configurable sync intervals
- Delta synchronization to minimize network traffic
- Automatic retry mechanisms for reliability

## 🚨 Alerting System

### Alert Channels
- **Teams Chat**: Real-time notifications
- **Email**: Detailed reports and summaries
- **SMS**: Critical alerts for immediate attention

### Alert Levels
- **INFO**: Status changes and routine events
- **WARNING**: Performance threshold breaches
- **CRITICAL**: Node failures and cluster issues
- **EMERGENCY**: Multi-node failures requiring immediate intervention

## 🔍 Diagnostic Questions ctrlNods Answers

- **Node Availability**: Is a node down? When did it go down?
- **Failure Patterns**: How many times has this happened to this node vs others?
- **Root Cause**: Why did it happen? (network, disk, RAM, queries, datacenter, hardware, CPU)
- **Cluster Impact**: Were other nodes down at the same time?
- **Configuration**: Are the settings correct for optimal performance?

## 📊 Integration with ctrlClus

ctrlNods feeds data to ctrlClus for:
- Cluster-wide pattern analysis
- Historical trend identification
- Correlation analysis across multiple nodes
- Predictive insights and recommendations
- Centralized reporting and visualization

---

*ctrlNods is designed to provide the detailed, real-time insights needed for proactive Cassandra cluster management and rapid issue resolution.*