#!/bin/bash

PHPUNIT="../vendor/bin/phpunit"

echo "=== Running Unit Tests ==="
$PHPUNIT --testsuite Unit

echo -e "\n=== Running Integration Tests ==="
$PHPUNIT --testsuite Integration

echo -e "\n=== Running E2E Tests ==="
./e2e/test-workflow.sh

echo -e "\n=== All Tests Complete ==="
