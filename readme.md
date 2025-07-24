Of course. Here is the revised implementation guide that includes instructions for DHCP WAN configurations, setups without a managed IPv6 interface, and details on how to perform a "dry run" for safe testing.

Implementation Guide: OPNsense Single WAN High-Availability (Unabridged)

This guide details the setup of a two-node, active/passive OPNsense cluster that shares a single public IP address, whether static or dynamic (DHCP). It uses a combination of CARP, a boot-time enforcement script, and a live-event handler to ensure stability and prevent the common "split-brain" or IP conflict race condition that can occur when the backup node reboots.

Core Concepts

The solution is built on a multi-script architecture where each component has a distinct role:

Passive Enforcer (99-ha_passive_enforcer.sh): A script that runs at the end of the boot process. Its only job is to ensure the backup firewall is truly passive by stopping critical services and setting its default route to go through the active firewall. This prevents any traffic leakage or service conflicts.

Live Failover Handler (10-failover.php): This is the primary logic script. It is triggered by OPNsense's CARP subsystem whenever a state change occurs (e.g., this node becomes MASTER or BACKUP). It manages the WAN interface configuration, IP address assignment, and the starting/stopping of services during a live failover event.

Route Setter (98-ha_set_routes.php): A helper script used by the Passive Enforcer to correctly configure the backup node's routing table.

Central Configuration (ha_failover.conf): A single JSON file that acts as the "source of truth" for all scripts, defining interfaces, services, and timeouts. This makes the entire system easy to manage and customize.

Configuration Validator (validate_ha_config.php): A crucial safety utility to verify the syntax and values in ha_failover.conf before deploying changes.

Prerequisites: OPNsense GUI Configuration

Before creating the script files, ensure your two OPNsense firewalls are configured for High Availability.

Firewall 1 (Primary):

Go to System -> High Availability -> Settings.

Enable HA by checking Synchronize States.

Set the Synchronize Interface (e.g., a dedicated SYNC interface).

Set the Synchronize Peer IP to the SYNC interface IP of the secondary firewall.

Go to Interfaces -> Virtual IPs -> Settings.

Set a CARP Failover tunable: create a new tunable net.inet.carp.preempt and set its value to 1. This tells the primary node it should always try to become the master if possible.

Firewall 2 (Secondary):

Configure HA Sync settings to point to the primary firewall.

Go to Interfaces -> Virtual IPs -> Settings.

Check the box for Disable Preemptive Mode. This sets the net.inet.carp.preempt tunable to 0 and adds the <disablepreempt> tag to the configuration, which our scripts use to identify the backup node.

On Both Firewalls:

Set up your CARP VIPs as usual on the WAN and LAN interfaces. Use a unique VHID for each VIP, but ensure the VHID for a given interface (e.g., WAN) is the same on both firewalls. Use different, non-conflicting advertising frequencies (advskew). A good practice is 0 on the primary and 100 on the secondary.

Ensure Configuration -> High Availability -> Settings is configured to synchronize settings from the primary to the secondary firewall.

Step 1: Create the Central Configuration File

This file will contain all your site-specific settings. It must be identical on both firewalls.

Create the file:

Generated shell
touch /usr/local/etc/ha_failover.conf


Edit the file and paste the contents from ha_failover.conf (2).txt.

Customize the file for your environment.

For DHCP WAN Connections:
Set "wan_mode": "dhcp". When you do this, the "wan_ipv4" and "wan_subnet_v4" keys are ignored and can be removed from the "network" section. The script will dynamically enable DHCP on the WAN interface when it becomes MASTER.

For Setups Without IPv6/Tunnels:
If you do not have a managed IPv6 tunnel, you can remove the "tunnel_key", "tunnel_gateway_name", and "target_v6" keys entirely from the configuration file. The script will automatically skip logic related to them.

Pay close attention to these sections:

"interfaces": Ensure "wan_key" matches your logical interface name in OPNsense (e.g., "wan").

"network":

For a static IP, set your shared "wan_ipv4" address and "wan_subnet_v4".

Update "wan_gateway_name" to match the name of your WAN gateway in System -> Routing -> Gateways.

"health_check":

The "local_target" should be a reliable IP on the local network segment, like your gateway's IP address.

The external "target" IPs are for checking internet connectivity.

"failover_gateways":

These are crucial for the backup node. They should be the names of Gateway entries you create that point to the LAN CARP VIP of the cluster. This allows the backup node to route its outbound traffic (like for updates) through the active master node.

"ha_core_services" and "ha_controlled_services": List the services the failover script should manage. Use the correct service name and PID file path.

Set secure permissions:

Generated shell
chmod 600 /usr/local/etc/ha_failover.conf
IGNORE_WHEN_COPYING_START
content_copy
download
Use code with caution.
Shell
IGNORE_WHEN_COPYING_END
Step 2: Create the Configuration Validator

This script is a safety measure to ensure your configuration file is valid.

Create the file:

Generated shell
touch /usr/local/etc/validate_ha_config.php
IGNORE_WHEN_COPYING_START
content_copy
download
Use code with caution.
Shell
IGNORE_WHEN_COPYING_END

Edit the file and paste the full contents from validate_ha_config.php.txt.

Make it executable:

Generated shell
chmod +x /usr/local/etc/validate_ha_config.php
IGNORE_WHEN_COPYING_START
content_copy
download
Use code with caution.
Shell
IGNORE_WHEN_COPYING_END
Step 3: Create the Route Setting Helper Script

This script is called by the boot-time enforcer on the backup node.

Create the directory if it doesn't exist:

Generated shell
mkdir -p /usr/local/etc/rc.syshook.d
IGNORE_WHEN_COPYING_START
content_copy
download
Use code with caution.
Shell
IGNORE_WHEN_COPYING_END

Create the file:

Generated shell
touch /usr/local/etc/rc.syshook.d/98-ha_set_routes.php
IGNORE_WHEN_COPYING_START
content_copy
download
Use code with caution.
Shell
IGNORE_WHEN_COPYING_END

Edit the file and paste the full contents from 98-ha_set_routes.php.txt.

Make it executable:

Generated shell
chmod +x /usr/local/etc/rc.syshook.d/98-ha_set_routes.php
IGNORE_WHEN_COPYING_START
content_copy
download
Use code with caution.
Shell
IGNORE_WHEN_COPYING_END
Step 4: Create the Passive Enforcer Boot Script

This script ensures the backup node stays passive upon boot.

Create the file:

Generated shell
touch /usr/local/etc/rc.d/99-ha_passive_enforcer.sh
IGNORE_WHEN_COPYING_START
content_copy
download
Use code with caution.
Shell
IGNORE_WHEN_COPYING_END

Edit the file and paste the full contents from 99-ha_passive_enforcer.sh.txt.

Make it executable:

Generated shell
chmod +x /usr/local/etc/rc.d/99-ha_passive_enforcer.sh
IGNORE_WHEN_COPYING_START
content_copy
download
Use code with caution.
Shell
IGNORE_WHEN_COPYING_END

Enable the service to run at boot: You must add an entry to the local runtime configuration file.

Generated shell
echo 'ha_passive_enforcer_enable="YES"' >> /etc/rc.conf.local
IGNORE_WHEN_COPYING_START
content_copy
download
Use code with caution.
Shell
IGNORE_WHEN_COPYING_END
Step 5: Create the Live Failover CARP Script

This is the main script that handles failover events.

Create the directory if it doesn't exist:

Generated shell
mkdir -p /usr/local/etc/rc.syshook.d/carp
IGNORE_WHEN_COPYING_START
content_copy
download
Use code with caution.
Shell
IGNORE_WHEN_COPYING_END

Create the file:

Generated shell
touch /usr/local/etc/rc.syshook.d/carp/10-failover.php
IGNORE_WHEN_COPYING_START
content_copy
download
Use code with caution.
Shell
IGNORE_WHEN_COPYING_END

Edit the file and paste the full contents from 10-failover.php (2).txt.

Make it executable:

Generated shell
chmod +x /usr/local/etc/rc.syshook.d/carp/10-failover.php
IGNORE_WHEN_COPYING_START
content_copy
download
Use code with caution.
Shell
IGNORE_WHEN_COPYING_END
Step 6: Testing with a Dry Run (Optional but Recommended)

Before rebooting or forcing a live failover, you can perform a "dry run" to see what the scripts would do without actually making any system changes. This is excellent for verifying your configuration.

Validate the configuration first:

Generated shell
php /usr/local/etc/validate_ha_config.php
IGNORE_WHEN_COPYING_START
content_copy
download
Use code with caution.
Shell
IGNORE_WHEN_COPYING_END

Ensure it reports as VALID.

Dry run the failover script (10-failover.php):
From a shell on either firewall, you can simulate a CARP event. The output will be a series of JSON log messages printed to your console.

To simulate a transition to MASTER:

Generated shell
php /usr/local/etc/rc.syshook.d/carp/10-failover.php carp MASTER dry-run
IGNORE_WHEN_COPYING_START
content_copy
download
Use code with caution.
Shell
IGNORE_WHEN_COPYING_END

To simulate a transition to BACKUP:

Generated shell
php /usr/local/etc/rc.syshook.d/carp/10-failover.php carp BACKUP dry-run
IGNORE_WHEN_COPYING_START
content_copy
download
Use code with caution.
Shell
IGNORE_WHEN_COPYING_END

Dry run the boot-time enforcer script (99-ha_passive_enforcer.sh):
This is especially useful on the backup node to see what it will do on boot.

Generated shell
sh /usr/local/etc/rc.d/99-ha_passive_enforcer.sh dry-run
IGNORE_WHEN_COPYING_START
content_copy
download
Use code with caution.
Shell
IGNORE_WHEN_COPYING_END

Look for messages indicating it detected the backup node and would stop services and set routes.

Step 7: Final Deployment and Verification

Synchronize: Ensure all five created files (ha_failover.conf, validate_ha_config.php, 98-ha_set_routes.php, 99-ha_passive_enforcer.sh, and 10-failover.php) and the change to /etc/rc.conf.local exist on both the primary and secondary firewalls. The scripts have logic to detect which node they are on.

Test the Boot-Up State:

Reboot the secondary (backup) firewall.

After it boots, check its state in Lobby -> Dashboard -> CARP. It should be in the BACKUP state.

From the console or SSH, verify the WAN interface does not have the shared static IP or a DHCP lease.

Check the logs for the enforcer script: cat /var/log/ha_enforcer.log. You should see it detect the BACKUP node and stop services.

Test a Live Failover:

On the primary firewall, enter persistent CARP maintenance mode via Interfaces -> Virtual IPs -> Settings.

Observe the secondary firewall. It should transition to MASTER.

Check the system logs (System -> Log Files -> General) on the secondary node. You should see log entries from ha_failover indicating the transition to the MASTER state.

Verify that the secondary firewall now has the shared WAN IP address (or has acquired a DHCP lease) and that the services defined in ha_controlled_services are running.

Test a Failback:

Take the primary firewall out of maintenance mode.

It should reclaim the MASTER role (due to net.inet.carp.preempt=1).

The secondary firewall should see a BACKUP event and run the script to transition back to a passive state, removing the IP and stopping services. This transition should also be visible in its system logs.
