# Installation Guide

This guide provides step-by-step instructions for installing the OPNsense HA Failover system.

## Prerequisites

### System Requirements
- Two OPNsense firewalls (identical hardware recommended)
- Dedicated synchronization interface between firewalls
- Network access for file transfers

### Pre-Installation Checklist
- [ ] OPNsense firewalls are running and accessible
- [ ] CARP is configured and working
- [ ] WAN interfaces optionally configured with identical MAC addresses
- [ ] Backup of current configurations created
- [ ] Network maintenance window scheduled

## Installation Steps

### 1. Create Directory Structure

On **both** firewalls, create the necessary directories:

```bash
# Create directories
mkdir -p /usr/local/etc/rc.syshook.d/carp
mkdir -p /usr/local/etc/rc.d

# Verify directories exist
ls -la /usr/local/etc/rc.syshook.d/
ls -la /usr/local/etc/rc.d/
```

### 2. Install Configuration Files

Copy the following files to `/usr/local/etc/` on **both** firewalls:

#### Main Configuration File
```bash
# Copy ha_failover.conf to /usr/local/etc/
# Edit the file to match your environment (see Configuration section)
chmod 600 /usr/local/etc/ha_failover.conf
```

#### Validation Script
```bash
# Copy validate_ha_config.php to /usr/local/etc/
chmod +x /usr/local/etc/validate_ha_config.php
```

### 3. Install Core Scripts

#### Main Failover Script
```bash
# Copy 10-failover.php to /usr/local/etc/rc.syshook.d/carp/
chmod +x /usr/local/etc/rc.syshook.d/carp/10-failover.php
```

#### Route Management Script
```bash
# Copy 98-ha_set_routes.php to /usr/local/etc/rc.syshook.d/
chmod +x /usr/local/etc/rc.syshook.d/98-ha_set_routes.php
```

#### Boot Enforcer Script
```bash
# Copy 99-ha_passive_enforcer.sh to /usr/local/etc/rc.d/
chmod +x /usr/local/etc/rc.d/99-ha_passive_enforcer.sh
```

### 4. Enable Boot Service

On **both** firewalls:

```bash
echo 'ha_passive_enforcer_enable="YES"' >> /etc/rc.conf.local
```

### 5. Configuration Customization

Edit `/usr/local/etc/ha_failover.conf` with your specific settings:

#### Required Changes
- Replace `A.B.C.100` with your actual WAN IP
- Replace `A.B.C.97` with your gateway IP
- Update `YOUR_WAN_GATEWAY` with your actual gateway name
- Modify service lists to match your setup

#### For DHCP WAN Connections
```json
{
  "network": {
    "wan_mode": "dhcp",
    "wan_gateway_name": "YOUR_WAN_GATEWAY"
  }
}
```

#### For IPv6-less Setups
Remove these keys from the configuration:
- `tunnel_key`
- `tunnel_gateway_name`
- `target_v6`

### 6. Validation

Validate your configuration:

```bash
php /usr/local/etc/validate_ha_config.php
```

Expected output:
```
OPNsense HA Failover Configuration Validator
Found configuration file at /usr/local/etc/ha_failover.conf.
JSON syntax is valid. Validating schema and values...
--------------------------------------------------
Configuration is VALID
--------------------------------------------------
```

### 7. Dry Run Testing

Test the installation without making changes:

```bash
# Test failover script
php /usr/local/etc/rc.syshook.d/carp/10-failover.php carp MASTER dry-run
php /usr/local/etc/rc.syshook.d/carp/10-failover.php carp BACKUP dry-run

# Test boot enforcer (run on backup node)
sh /usr/local/etc/rc.d/99-ha_passive_enforcer.sh dry-run
```

### 8. File Synchronization

Ensure all files are identical on both firewalls:

```bash
# Verify file checksums match between firewalls
md5 /usr/local/etc/ha_failover.conf
md5 /usr/local/etc/validate_ha_config.php
md5 /usr/local/etc/rc.syshook.d/carp/10-failover.php
md5 /usr/local/etc/rc.syshook.d/98-ha_set_routes.php
md5 /usr/local/etc/rc.d/99-ha_passive_enforcer.sh
```

## Post-Installation Testing

### 1. Boot Test
Reboot the secondary (backup) firewall:
```bash
# On secondary firewall
shutdown -r now
```

After reboot, verify:
- CARP status shows BACKUP
- WAN interface has no IP assigned
- Services are stopped
- Check logs: `cat /var/log/ha_enforcer.log`

### 2. Failover Test
Put the primary firewall in maintenance mode:
- Go to **Interfaces → Virtual IPs → Settings**
- Enter persistent CARP maintenance mode

Verify secondary firewall:
- Becomes MASTER
- Acquires WAN IP (or DHCP lease)
- Starts managed services
- Check system logs for failover events

### 3. Failback Test
Remove primary firewall from maintenance mode:
- Should reclaim MASTER status
- Secondary should become BACKUP
- Services should stop on secondary

## Troubleshooting Installation

### Common Issues

#### Permission Errors
```bash
# Fix permissions
chmod 600 /usr/local/etc/ha_failover.conf
chmod +x /usr/local/etc/validate_ha_config.php
chmod +x /usr/local/etc/rc.syshook.d/carp/10-failover.php
chmod +x /usr/local/etc/rc.syshook.d/98-ha_set_routes.php
chmod +x /usr/local/etc/rc.d/99-ha_passive_enforcer.sh
```

#### Configuration Validation Fails
1. Check JSON syntax: `python -m json.tool /usr/local/etc/ha_failover.conf`
2. Review error message from validator
3. Verify all required fields are present

#### Scripts Not Executing
1. Verify file permissions (executable bit set)
2. Check shebang lines are correct
3. Ensure PHP and shell are available

#### Services Not Starting
1. Verify service names match OPNsense service names
2. Check PID file paths exist
3. Test manual service start: `service <name> start`

### Verification Commands

```bash
# Check CARP status
ifconfig | grep carp

# Verify routes
netstat -rn

# Check running services
ps aux | grep -E "(dhcpd|unbound|frr)"

# View system logs
tail -f /var/log/system.log | grep ha_failover
```

## Rollback Procedure

If installation fails and you need to rollback:

1. **Remove Boot Service**:
   ```bash
   sed -i '' '/ha_passive_enforcer_enable/d' /etc/rc.conf.local
   ```

2. **Remove Script Files**:
   ```bash
   rm -f /usr/local/etc/ha_failover.conf
   rm -f /usr/local/etc/validate_ha_config.php
   rm -f /usr/local/etc/rc.syshook.d/carp/10-failover.php
   rm -f /usr/local/etc/rc.syshook.d/98-ha_set_routes.php
   rm -f /usr/local/etc/rc.d/99-ha_passive_enforcer.sh
   ```

3. **Clean Up Temporary Files**:
   ```bash
   rm -f /tmp/carp_failover.*
   rm -f /tmp/ha_enforcer.lock
   rm -f /var/log/ha_enforcer.log
   ```

4. **Restart Services**:
   ```bash
   # Restart any affected services manually
   service dhcpd start
   service unbound start
   ```

## Next Steps

After successful installation:
1. Monitor logs for the first few days
2. Test failover scenarios during maintenance windows
3. Document your specific configuration
4. Set up monitoring alerts for CARP state changes
5. Create automated backup procedures for the configuration files
