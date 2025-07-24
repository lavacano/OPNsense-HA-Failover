# Repository Structure

```
opnsense-ha-failover/
├── README.md                           # Main documentation
├── INSTALLATION.md                     # Detailed installation guide
├── LICENSE                             # MIT License
├── docs/                              # Additional documentation
│   ├── CONFIGURATION.md               # Configuration reference
│   ├── TROUBLESHOOTING.md             # Troubleshooting guide
│   └── examples/                      # Configuration examples
│       ├── static-ip-example.json     # Static IP configuration
│       ├── dhcp-example.json          # DHCP configuration
│       └── ipv4-only-example.json     # IPv4-only configuration
├── scripts/                           # Main script files
│   ├── 10-failover.php                # Main failover logic
│   ├── 98-ha_set_routes.php           # Route management helper
│   ├── 99-ha_passive_enforcer.sh      # Boot-time enforcer
│   └── validate_ha_config.php         # Configuration validator
├── config/                            # Configuration files
│   ├── ha_failover.conf.example       # Example configuration
│   └── ha_failover.conf.template      # Template with placeholders
├── tests/                             # Test scripts and examples
│   ├── dry-run-tests.sh               # Automated dry-run testing
│   ├── validation-tests.sh            # Configuration validation tests
│   └── integration-tests.sh           # Integration test suite
└── contrib/                           # Contributed utilities
    ├── backup-config.sh               # Configuration backup utility
    ├── install.sh                     # Automated installation script
    └── monitor-carp.sh                # CARP monitoring script
```

## File Descriptions

### Core Script Files

| File | Location | Purpose | Permissions |
|------|----------|---------|-------------|
| `10-failover.php` | `/usr/local/etc/rc.syshook.d/carp/` | Main CARP event handler | `755` |
| `98-ha_set_routes.php` | `/usr/local/etc/rc.syshook.d/` | Route configuration helper | `755` |
| `99-ha_passive_enforcer.sh` | `/usr/local/etc/rc.d/` | Boot-time backup node enforcer | `755` |
| `validate_ha_config.php` | `/usr/local/etc/` | Configuration validator | `755` |
| `ha_failover.conf` | `/usr/local/etc/` | Central configuration file | `600` |

### Installation Locations

```bash
# Main configuration (secure permissions)
/usr/local/etc/ha_failover.conf                    # 600 (-rw-------)

# Validation utility  
/usr/local/etc/validate_ha_config.php              # 755 (-rwxr-xr-x)

# CARP event handler
/usr/local/etc/rc.syshook.d/carp/10-failover.php   # 755 (-rwxr-xr-x)

# Route management helper
/usr/local/etc/rc.syshook.d/98-ha_set_routes.php   # 755 (-rwxr-xr-x)

# Boot-time service
/usr/local/etc/rc.d/99-ha_passive_enforcer.sh      # 755 (-rwxr-xr-x)

# Runtime files (created automatically)
/tmp/carp_failover.lock                             # Lock file
/tmp/carp_failover.state                            # State tracking
/tmp/carp_failover.failures                         # Failure counter
/var/log/ha_enforcer.log                           # Boot enforcer log
```

### Repository File Manifest

#### Documentation
- `README.md` - Main project documentation and quick start
- `INSTALLATION.md` - Detailed step-by-step installation guide
- `docs/CONFIGURATION.md` - Complete configuration reference
- `docs/TROUBLESHOOTING.md` - Common issues and solutions
- `docs/API.md` - Script API and customization guide

#### Configuration Templates
- `config/ha_failover.conf.example` - Complete working example
- `config/ha_failover.conf.template` - Template with placeholders
- `docs/examples/static-ip-example.json` - Static IP setup
- `docs/examples/dhcp-example.json` - DHCP WAN setup
- `docs/examples/ipv4-only-example.json` - No IPv6 tunnel

#### Core Scripts
- `scripts/10-failover.php` - Main failover logic (15.6KB)
- `scripts/98-ha_set_routes.php` - Route helper (4.2KB) 
- `scripts/99-ha_passive_enforcer.sh` - Boot enforcer (3.8KB)
- `scripts/validate_ha_config.php` - Config validator (2.1KB)

#### Testing and Utilities
- `tests/dry-run-tests.sh` - Automated dry-run testing
- `tests/validation-tests.sh` - Configuration validation
- `tests/integration-tests.sh` - Full integration tests
- `contrib/install.sh` - Automated installation
- `contrib/backup-config.sh` - Configuration backup
- `contrib/monitor-carp.sh` - CARP status monitoring

### File Dependencies

```
ha_failover.conf
├── 10-failover.php (reads config)
├── 98-ha_set_routes.php (reads config)  
├── 99-ha_passive_enforcer.sh (reads config)
└── validate_ha_config.php (validates config)

10-failover.php
├── Requires: config.inc, interfaces.inc, util.inc, system.inc
├── Creates: /tmp/carp_failover.* files
└── Logs to: syslog (LOG_LOCAL4)

98-ha_set_routes.php  
├── Requires: config.inc, interfaces.inc, util.inc, system.inc
├── Called by: 99-ha_passive_enforcer.sh
└── Logs to: syslog

99-ha_passive_enforcer.sh
├── Reads: ha_failover.conf
├── Calls: 98-ha_set_routes.php
├── Logs to: /var/log/ha_enforcer.log
└── Enabled by: /etc/rc.conf.local
```

### Checksum Verification

After installation, verify file integrity:

```bash
#!/bin/bash
# Generate checksums for verification
echo "Generating checksums for HA Failover files..."

md5sum /usr/local/etc/ha_failover.conf > /tmp/ha_checksums.txt
md5sum /usr/local/etc/validate_ha_config.php >> /tmp/ha_checksums.txt  
md5sum /usr/local/etc/rc.syshook.d/carp/10-failover.php >> /tmp/ha_checksums.txt
md5sum /usr/local/etc/rc.syshook.d/98-ha_set_routes.php >> /tmp/ha_checksums.txt
md5sum /usr/local/etc/rc.d/99-ha_passive_enforcer.sh >> /tmp/ha_checksums.txt

echo "Checksums saved to /tmp/ha_checksums.txt"
echo "Compare checksums between primary and backup firewalls"
```

### Version Information

- **Current Version**: 15.6 (Production Release)
- **PHP Version Required**: 7.4+ (OPNsense native)
- **OPNsense Version**: 23.1+ (tested)
- **Dependencies**: OPNsense Core, CARP, jq (for shell scripts)

### File Size Reference

| File | Approximate Size |
|------|------------------|
| 10-failover.php | ~15.6 KB |
| 98-ha_set_routes.php | ~4.2 KB |
| 99-ha_passive_enforcer.sh | ~3.8 KB |
| validate_ha_config.php | ~2.1 KB |
| ha_failover.conf | ~1.2 KB |
| **Total** | **~27 KB** |
