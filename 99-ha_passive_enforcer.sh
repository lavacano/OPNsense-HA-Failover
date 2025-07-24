#!/bin/sh
# PROVIDE: ha_passive_enforcer
# REQUIRE: LOGIN
# KEYWORD: shutdown

. /etc/rc.subr

name="ha_passive_enforcer"
start_cmd="${name}_start"
stop_cmd=":"
rcvar="ha_passive_enforcer_enable"

LOG_FILE="/var/log/ha_enforcer.log"
LOCK_FILE="/tmp/ha_enforcer.lock"
HA_CONF="/usr/local/etc/ha_failover.conf"
MAX_LOG_SIZE=10485760
IS_DRY_RUN=0

log() {
    if [ -f "$LOG_FILE" ] && [ "$(stat -f%z "$LOG_FILE" 2>/dev/null || echo 0)" -gt $MAX_LOG_SIZE ]; then
        mv "$LOG_FILE" "${LOG_FILE}.old"
    fi
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" >> "$LOG_FILE"
    logger -t ha_passive_enforcer "$1"
}

cleanup() {
    rm -f "$LOCK_FILE"
}
trap cleanup EXIT INT TERM

# Helper function to stop a list of services defined by a JQ filter
stop_service_list() {
    local service_list_jq_filter=$1
    echo "$service_list_jq_filter" | jq -r '.[]? | "\(.name):\(.pid_file):\(.shutdown_timeout // 10)"' "$HA_CONF" | while IFS=: read -r service_name pid_file shutdown_timeout; do
        if [ -f "$pid_file" ] && PID=$(cat "$pid_file" 2>/dev/null) && [ -n "$PID" ] && kill -0 "$PID" 2>/dev/null; then
            log "Stopping '$service_name' (PID: $PID, Timeout: ${shutdown_timeout}s)..."
            [ $IS_DRY_RUN -eq 0 ] && kill "$PID"
            
            shutdown_wait=$shutdown_timeout
            while [ $shutdown_wait -gt 0 ]; do
                if [ $IS_DRY_RUN -eq 1 ] || ! kill -0 "$PID" 2>/dev/null; then
                    log "'$service_name' stopped gracefully."
                    break
                fi
                sleep 1
                shutdown_wait=$((shutdown_wait - 1))
            done

            if [ $shutdown_wait -eq 0 ]; then
                log "Escalating: Forcefully stopping '$service_name' (PID: $PID)..."
                [ $IS_DRY_RUN -eq 0 ] && kill -9 "$PID"
                sleep 2
                if [ $IS_DRY_RUN -eq 0 ] && kill -0 "$PID" 2>/dev/null; then
                    log "CRITICAL: Service '$service_name' (PID: $PID) could not be terminated."
                else
                    log "'$service_name' stopped forcefully."
                fi
            fi
        fi
    done
}

ha_passive_enforcer_start()
{
    if [ "$1" = "dry-run" ]; then
        IS_DRY_RUN=1
        log "========= Starting DRY RUN ========="
    fi

    if ! [ -r "$HA_CONF" ] || ! jq empty "$HA_CONF" 2>/dev/null; then
        log "ERROR: Config file $HA_CONF is missing, unreadable, or not valid JSON."
        return 1
    fi

    # --- Pre-flight Configuration Validation ---
    log "Validating HA configuration file schema and values..."
    if ! /usr/local/etc/validate_ha_config.php >/dev/null 2>&1; then
        log "CRITICAL: HA configuration file is invalid. Please run /usr/local/etc/validate_ha_config.php for details. Aborting."
        exit 1
    fi
    log "HA configuration file is valid."
    # ----------------------------------------

    LOCK_TIMEOUT=$(jq -r '.timeouts.lock_wait_timeout // 60' "$HA_CONF" 2>/dev/null)
    exec 200>"$LOCK_FILE"
    if ! flock -n -w "$LOCK_TIMEOUT" 200; then
        log "ERROR: Could not acquire lock after ${LOCK_TIMEOUT}s. Aborting."
        exit 1;
    fi
    echo $$ >&200

    if ! grep -q "<disablepreempt>" /conf/config.xml; then
        log "PRIMARY node detected. No action needed."
        return 0
    fi

    log "BACKUP node detected. Enforcing passive state."
    
    DELAY=$(jq -r '.timeouts.passive_enforcer_delay // 20' "$HA_CONF" 2>/dev/null)
    log "Waiting for ${DELAY}s for system to settle..."
    [ $IS_DRY_RUN -eq 0 ] && sleep "$DELAY"

    log "Stopping standard HA-controlled services..."
    stop_service_list "$(jq -c '.ha_controlled_services' "$HA_CONF" 2>/dev/null)"

    log "Stopping core HA-controlled services..."
    stop_service_list "$(jq -c '.ha_core_services' "$HA_CONF" 2>/dev/null)"

    log "Setting default routes via dedicated script..."
    if [ $IS_DRY_RUN -eq 1 ]; then
        log "DRY RUN: Would execute /usr/local/etc/rc.syshook.d/98-ha_set_routes.php"
    elif /usr/local/etc/rc.syshook.d/98-ha_set_routes.php; then
        log "Default routes configured successfully."
    else
        log "ERROR: Failed to configure default routes. Check system logs for details."
    fi

    [ $IS_DRY_RUN -eq 1 ] && log "========= Finished DRY RUN ========="
}

load_rc_config $name
run_rc_command "$1"
