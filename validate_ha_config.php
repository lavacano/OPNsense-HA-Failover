#!/usr/local/bin/php
<?php

/*
 * OPNsense HA Failover - Configuration Validator
 *
 * This script validates the syntax and values of the ha_failover.conf file
 * without taking any actions. It should be run before deploying a new or
 * modified configuration to prevent runtime errors.
 *
 * Usage: /usr/local/etc/validate_ha_config.php
 */

// We only need the DTO and custom exceptions from the main script.
require_once '/usr/local/etc/rc.syshook.d/carp/10-failover.php';

echo "OPNsense HA Failover Configuration Validator\n";

$config_path = '/usr/local/etc/ha_failover.conf';

if (!file_exists($config_path) || !is_readable($config_path)) {
    echo "ERROR: Configuration file not found or not readable at: {$config_path}\n";
    exit(1);
}

echo "Found configuration file at {$config_path}.\n";

$json_content = file_get_contents($config_path);
$config_data = json_decode($json_content, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo "--------------------------------------------------\n";
    echo "Configuration INVALID\n";
    echo "--------------------------------------------------\n";
    echo "Reason: Not valid JSON. Error: " . json_last_error_msg() . "\n";
    exit(1);
}

echo "JSON syntax is valid. Validating schema and values...\n";

try {
    // Attempt to instantiate the SettingsDTO. This will trigger all validation
    // logic within its constructor.
    new SettingsDTO($config_data);

    echo "--------------------------------------------------\n";
    echo "Configuration is VALID\n";
    echo "--------------------------------------------------\n";
    echo "The configuration file appears to be well-formed and all values are within their expected ranges.\n";
    exit(0);
} catch (HAConfigurationException $e) {
    echo "--------------------------------------------------\n";
    echo "Configuration INVALID\n";
    echo "--------------------------------------------------\n";
    echo "Reason: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "--------------------------------------------------\n";
    echo "An unexpected error occurred during validation.\n";
    echo "--------------------------------------------------\n";
    echo "Reason: " . $e->getMessage() . "\n";
    exit(1);
}
