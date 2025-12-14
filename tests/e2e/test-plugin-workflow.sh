#!/bin/bash

BASE_URL="http://localhost:8080"
MOCK_ROOT="/tmp/unraid-mock-workflow-$$"
SHARES_FILE="$MOCK_ROOT/config/plugins/custom.smb.shares/shares.json"
CONFIG_FILE="$MOCK_ROOT/config/smb-extra.conf"
LIB_SOURCE="$(cd "$(dirname "$0")/../../source/usr/local/emhttp/plugins/custom.smb.shares/include" && pwd)/lib.php"

setup() {
    mkdir -p "$MOCK_ROOT/config/plugins/custom.smb.shares"
    echo "[]" > "$SHARES_FILE"
    touch "$CONFIG_FILE"
    
    # Copy lib.php to mock location
    cp "$LIB_SOURCE" "$MOCK_ROOT/lib.php"
}

cleanup() {
    rm -rf "$MOCK_ROOT"
}

test_add_share() {
    echo "Testing: Add share..."
    
    # Simulate form submission
    php -r "
    define('CONFIG_BASE', '$MOCK_ROOT/config');
    require '$MOCK_ROOT/lib.php';
    
    \$_POST = [
        'name' => 'test_share',
        'path' => '/mnt/user/test',
        'comment' => 'Test Share',
        'browseable' => 'yes',
        'read_only' => 'no'
    ];
    
    \$newShare = array_filter(\$_POST, fn(\$v) => \$v !== '');
    \$errors = validateShare(\$newShare);
    
    if (!empty(\$errors)) {
        echo 'FAIL: Validation errors: ' . implode(', ', \$errors);
        exit(1);
    }
    
    \$shares = loadShares();
    \$shares[] = \$newShare;
    saveShares(\$shares);
    
    \$config = generateSambaConfig(\$shares);
    file_put_contents('$CONFIG_FILE', \$config);
    
    echo 'Share added';
    "
    
    if [ $? -eq 0 ]; then
        # Verify share was added
        count=$(jq 'length' "$SHARES_FILE")
        if [ "$count" -eq 1 ]; then
            echo "PASS: Add share"
        else
            echo "FAIL: Expected 1 share, got $count"
            return 1
        fi
    else
        echo "FAIL: Add share script failed"
        return 1
    fi
}

test_validation() {
    echo "Testing: Validation..."
    
    php -r "
    define('CONFIG_BASE', '$MOCK_ROOT/config');
    require '$MOCK_ROOT/lib.php';
    
    // Test invalid share name
    \$errors = validateShare(['name' => 'bad name!', 'path' => '/mnt/user/test']);
    if (empty(\$errors)) {
        echo 'FAIL: Should reject invalid name';
        exit(1);
    }
    
    // Test invalid path
    \$errors = validateShare(['name' => 'test', 'path' => '/home/test']);
    if (empty(\$errors)) {
        echo 'FAIL: Should reject invalid path';
        exit(1);
    }
    
    // Test invalid mask
    \$errors = validateShare(['name' => 'test', 'path' => '/mnt/user/test', 'create_mask' => '999']);
    if (empty(\$errors)) {
        echo 'FAIL: Should reject invalid mask';
        exit(1);
    }
    
    echo 'Validation working';
    "
    
    if [ $? -eq 0 ]; then
        echo "PASS: Validation"
    else
        echo "FAIL: Validation"
        return 1
    fi
}

test_update_share() {
    echo "Testing: Update share..."
    
    php -r "
    define('CONFIG_BASE', '$MOCK_ROOT/config');
    require '$MOCK_ROOT/lib.php';
    
    \$shares = loadShares();
    if (empty(\$shares)) {
        echo 'FAIL: No shares to update';
        exit(1);
    }
    
    \$shares[0]['comment'] = 'Updated Comment';
    saveShares(\$shares);
    
    \$config = generateSambaConfig(\$shares);
    file_put_contents('$CONFIG_FILE', \$config);
    
    echo 'Share updated';
    "
    
    if [ $? -eq 0 ]; then
        comment=$(jq -r '.[0].comment' "$SHARES_FILE")
        if [ "$comment" = "Updated Comment" ]; then
            echo "PASS: Update share"
        else
            echo "FAIL: Comment not updated"
            return 1
        fi
    else
        echo "FAIL: Update share script failed"
        return 1
    fi
}

test_config_generation() {
    echo "Testing: Config generation..."
    
    if grep -q "\[test_share\]" "$CONFIG_FILE"; then
        if grep -q "path = /mnt/user/test" "$CONFIG_FILE"; then
            if grep -q "comment = Test Share" "$CONFIG_FILE"; then
                echo "PASS: Config generation"
            else
                echo "FAIL: Comment not in config"
                cat "$CONFIG_FILE"
                return 1
            fi
        else
            echo "FAIL: Path not in config"
            return 1
        fi
    else
        echo "FAIL: Share name not in config"
        return 1
    fi
}

test_delete_share() {
    echo "Testing: Delete share..."
    
    php -r "
    define('CONFIG_BASE', '$MOCK_ROOT/config');
    require '$MOCK_ROOT/lib.php';
    
    \$shares = loadShares();
    unset(\$shares[0]);
    \$shares = array_values(\$shares);
    saveShares(\$shares);
    
    \$config = generateSambaConfig(\$shares);
    file_put_contents('$CONFIG_FILE', \$config);
    
    echo 'Share deleted';
    "
    
    if [ $? -eq 0 ]; then
        count=$(jq 'length' "$SHARES_FILE")
        if [ "$count" -eq 0 ]; then
            echo "PASS: Delete share"
        else
            echo "FAIL: Share not deleted"
            return 1
        fi
    else
        echo "FAIL: Delete share script failed"
        return 1
    fi
}

# Run tests
trap cleanup EXIT
setup

echo "=== Plugin Workflow Tests ==="
test_validation || exit 1
test_add_share || exit 1
test_config_generation || exit 1
test_update_share || exit 1
test_delete_share || exit 1

echo -e "\n=== All Workflow Tests Passed ==="
