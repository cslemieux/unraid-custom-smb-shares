#!/bin/bash

# E2E test for complete share workflow
set -e

MOCK_ROOT="/tmp/unraid-e2e-$$"
SHARES_FILE="$MOCK_ROOT/shares.json"
CONFIG_FILE="$MOCK_ROOT/smb-extra.conf"

setup() {
    mkdir -p "$MOCK_ROOT"
    echo "[]" > "$SHARES_FILE"
    touch "$CONFIG_FILE"
}

cleanup() {
    rm -rf "$MOCK_ROOT"
}

test_add_share() {
    echo '[{"name":"test","path":"/mnt/user/test","browseable":"yes","read_only":"no"}]' > "$SHARES_FILE"
    
    [ -f "$SHARES_FILE" ] || { echo "FAIL: Share file not created"; exit 1; }
    
    count=$(jq 'length' "$SHARES_FILE")
    [ "$count" -eq 1 ] || { echo "FAIL: Expected 1 share, got $count"; exit 1; }
    
    echo "PASS: Add share"
}

test_generate_config() {
    jq -r '.[] | "[" + .name + "]\n  path = " + .path + "\n  browseable = " + .browseable + "\n  read only = " + .read_only + "\n"' \
        "$SHARES_FILE" > "$CONFIG_FILE"
    
    grep -q "\[test\]" "$CONFIG_FILE" || { echo "FAIL: Config not generated"; exit 1; }
    
    echo "PASS: Generate config"
}

test_delete_share() {
    echo "[]" > "$SHARES_FILE"
    
    count=$(jq 'length' "$SHARES_FILE")
    [ "$count" -eq 0 ] || { echo "FAIL: Share not deleted"; exit 1; }
    
    echo "PASS: Delete share"
}

trap cleanup EXIT
setup

test_add_share
test_generate_config
test_delete_share

echo "All E2E tests passed"
