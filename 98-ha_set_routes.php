#!/usr/local/bin/php
<?php

require_once "/usr/local/etc/inc/config.inc";
require_once "/usr/local/etc/inc/util.inc";
require_once "/usr/local/etc/inc/system.inc";
require_once "/usr/local/etc/inc/interfaces.inc";

use OPNsense\Core\Backend;

function log_enforcer($message, $is_error = true)
{
    $level = $is_error ? "ERROR" : "INFO";
    logger("ha_passive_enforcer: ${level} - " . $message);
}

function verify_route_installed($gateway_ip, $family): bool
{
    $be = new Backend();
    $routes_json = $be->configdRun("interface routes list -n json");
    $routes = json_decode($routes_json, true) ?? [];
    
    foreach ($routes as $route) {
        if ($route['destination'] === 'default' &&
            $route['gateway'] === $gateway_ip &&
            $route['proto'] === $family) {
            return true;
        }
    }
    return false;
}

function set_and_verify_route($gateway_ip, $if_friendly, $settle_delay, $ifdetails): bool
{
    if (empty($gateway_ip) || empty($if_friendly)) {
        return true;
    }

    $real_if = get_real_interface($if_friendly);
    $family = strpos($gateway_ip, ':') === false ? 'ipv4' : 'ipv6';

    if (!isset($ifdetails[$real_if]) || !in_array('up', $ifdetails[$real_if]['flags']) || !in_array('running', $ifdetails[$real_if]['flags'])) {
        log_enforcer("Skipping {$family} route: Interface {$if_friendly} ({$real_if}) is not UP and RUNNING.");
        return false;
    }

    $max_retries = 3;
    for ($i = 1; $i <= $max_retries; $i++) {
        log_enforcer("Setting {$family} default route to {$gateway_ip} (Attempt {$i}/{$max_retries})", false);
        ob_start();
        system_default_route(["gateway" => $gateway_ip, "if" => $real_if], []);
        ob_end_clean();
        sleep($settle_delay);

        if (verify_route_installed($gateway_ip, $family)) {
            log_enforcer("Successfully installed {$family} default route to {$gateway_ip}.", false);
            return true;
        }
        log_enforcer("Failed to verify {$family} route installation on attempt {$i}. Retrying...");
        if ($i < $max_retries) sleep(2);
    }

    log_enforcer("Failed to install and verify {$family} default route to {$gateway_ip} after {$max_retries} attempts.");
    return false;
}

try {
    $ha_conf_json = @file_get_contents('/usr/local/etc/ha_failover.conf');
    if ($ha_conf_json === false) {
        throw new \Exception("Could not read ha_failover.conf");
    }
    $ha_conf = json_decode($ha_conf_json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new \Exception("Failed to decode ha_failover.conf JSON: " . json_last_error_msg());
    }
    
    $settle_delay = $ha_conf['timeouts']['route_settle_delay'] ?? 2;
    $failover_gateways = $ha_conf['failover_gateways'] ?? [
        'ipv4' => 'LAN_FAILOVER_GW',
        'ipv6' => 'LAN_FAILOVER_GW_V6'
    ];
    $gw_v4_name = $failover_gateways['ipv4'];
    $gw_v6_name = $failover_gateways['ipv6'];

    $config = load_config_from_file("/conf/config.xml");
    $gws = $config["gateways"]["gateway_item"] ?? [];
    $gw_v4 = null; $gw_v4_if_friendly = null;
    $gw_v6 = null; $gw_v6_if_friendly = null;

    foreach ($gws as $gw) {
        if (isset($gw["name"]) && $gw["name"] === $gw_v4_name) {
            $gw_v4 = $gw["gateway"];
            $gw_v4_if_friendly = $gw["interface"];
        }
        if (isset($gw["name"]) && $gw["name"] === $gw_v6_name) {
            $gw_v6 = $gw["gateway"];
            $gw_v6_if_friendly = $gw["interface"];
        }
    }

    if (!$gw_v4 && !$gw_v6) {
        log_enforcer("Failover gateways ('{$gw_v4_name}' or '{$gw_v6_name}') not found in config.xml.");
        exit(1);
    }

    $ifdetails = legacy_interfaces_details();
    $v4_ok = set_and_verify_route($gw_v4, $gw_v4_if_friendly, $settle_delay, $ifdetails);
    $v6_ok = set_and_verify_route($gw_v6, $gw_v6_if_friendly, $settle_delay, $ifdetails);
    
    $all_ok = $v4_ok && $v6_ok;
    exit($all_ok ? 0 : 1);

} catch (Exception $e) {
    log_enforcer("Exception caught: " . $e->getMessage());
    exit(1);
}
