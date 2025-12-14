#!/bin/bash
# E2E Test Runner with Robust ChromeDriver Management

set -e

CHROMEDRIVER_PORT=9515
CHROMEDRIVER_PID_FILE="/tmp/chromedriver-e2e.pid"
CHROMEDRIVER_LOCK="/tmp/chromedriver-e2e.lock"
MAX_WAIT=10
RETRY_ATTEMPTS=3

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}=== E2E Test Runner ===${NC}"

# Function to acquire lock
acquire_lock() {
    local lockfile="$1"
    local timeout=30
    local elapsed=0
    
    while [ $elapsed -lt $timeout ]; do
        if mkdir "$lockfile" 2>/dev/null; then
            trap "rm -rf '$lockfile'" EXIT INT TERM
            return 0
        fi
        sleep 1
        elapsed=$((elapsed + 1))
    done
    
    echo -e "${RED}Failed to acquire lock after ${timeout}s${NC}"
    return 1
}

# Function to check if ChromeDriver is running and healthy
check_chromedriver() {
    # Check if process exists
    if [ -f "$CHROMEDRIVER_PID_FILE" ]; then
        local pid=$(cat "$CHROMEDRIVER_PID_FILE")
        if ! kill -0 "$pid" 2>/dev/null; then
            return 1
        fi
    fi
    
    # Check if port is responding
    if ! curl -sf --max-time 2 http://localhost:${CHROMEDRIVER_PORT}/status > /dev/null 2>&1; then
        return 1
    fi
    
    # Verify JSON response is valid
    local response=$(curl -sf --max-time 2 http://localhost:${CHROMEDRIVER_PORT}/status 2>/dev/null)
    if ! echo "$response" | grep -q "ready"; then
        return 1
    fi
    
    return 0
}

# Function to kill ChromeDriver processes
kill_chromedriver() {
    # Kill by PID file
    if [ -f "$CHROMEDRIVER_PID_FILE" ]; then
        local pid=$(cat "$CHROMEDRIVER_PID_FILE")
        if kill -0 "$pid" 2>/dev/null; then
            kill "$pid" 2>/dev/null || true
            sleep 0.5
            kill -9 "$pid" 2>/dev/null || true
        fi
        rm -f "$CHROMEDRIVER_PID_FILE"
    fi
    
    # Kill by port
    local port_pid=$(lsof -ti:${CHROMEDRIVER_PORT} 2>/dev/null || true)
    if [ -n "$port_pid" ]; then
        kill "$port_pid" 2>/dev/null || true
        sleep 0.5
        kill -9 "$port_pid" 2>/dev/null || true
    fi
    
    # Kill by process name
    pkill -f "chromedriver.*${CHROMEDRIVER_PORT}" 2>/dev/null || true
    
    # Wait for port to be free
    for i in $(seq 1 5); do
        if ! lsof -ti:${CHROMEDRIVER_PORT} >/dev/null 2>&1; then
            return 0
        fi
        sleep 1
    done
}

# Function to start ChromeDriver with retries
start_chromedriver() {
    echo -e "${YELLOW}Starting ChromeDriver on port ${CHROMEDRIVER_PORT}...${NC}"
    
    # Acquire lock to prevent concurrent starts
    if ! acquire_lock "$CHROMEDRIVER_LOCK"; then
        echo -e "${RED}Another instance is starting ChromeDriver${NC}"
        return 1
    fi
    
    # Kill any existing instances
    kill_chromedriver
    
    # Verify chromedriver binary exists
    if ! command -v chromedriver &> /dev/null; then
        echo -e "${RED}chromedriver not found in PATH${NC}"
        echo -e "${YELLOW}Install with: brew install chromedriver${NC}"
        return 1
    fi
    
    # Start ChromeDriver with retry
    local attempt=1
    while [ $attempt -le $RETRY_ATTEMPTS ]; do
        echo -e "${YELLOW}Attempt $attempt/$RETRY_ATTEMPTS...${NC}"
        
        # Start in background with explicit log file
        chromedriver --port=${CHROMEDRIVER_PORT} --verbose \
            > /tmp/chromedriver.log 2>&1 &
        local pid=$!
        echo $pid > "$CHROMEDRIVER_PID_FILE"
        
        # Wait for startup with exponential backoff
        echo -n "Waiting for ChromeDriver"
        for i in $(seq 1 $MAX_WAIT); do
            if check_chromedriver; then
                echo -e " ${GREEN}✓${NC}"
                echo -e "${GREEN}ChromeDriver started (PID: $pid)${NC}"
                return 0
            fi
            
            # Check if process died
            if ! kill -0 "$pid" 2>/dev/null; then
                echo -e " ${RED}✗ Process died${NC}"
                break
            fi
            
            echo -n "."
            sleep 1
        done
        
        echo -e " ${RED}✗${NC}"
        kill_chromedriver
        attempt=$((attempt + 1))
        
        if [ $attempt -le $RETRY_ATTEMPTS ]; then
            sleep 2
        fi
    done
    
    echo -e "${RED}Failed to start ChromeDriver after $RETRY_ATTEMPTS attempts${NC}"
    if [ -f /tmp/chromedriver.log ]; then
        echo -e "${YELLOW}Last 20 lines of log:${NC}"
        tail -20 /tmp/chromedriver.log
    fi
    return 1
}

# Function to stop ChromeDriver with thorough cleanup
stop_chromedriver() {
    echo -e "${YELLOW}Stopping ChromeDriver...${NC}"
    
    kill_chromedriver
    
    # Cleanup test harness processes
    pkill -f "php.*-S.*localhost:888" 2>/dev/null || true
    
    # Cleanup lock files
    rm -rf "$CHROMEDRIVER_LOCK" 2>/dev/null || true
    
    # Cleanup temp files
    rm -f /tmp/chromedriver.log 2>/dev/null || true
    rm -f /tmp/unraid-test-harness-*.lock 2>/dev/null || true
    
    echo -e "${GREEN}Cleanup complete${NC}"
}

# Trap to ensure cleanup on exit
trap stop_chromedriver EXIT INT TERM

# Check if ChromeDriver is already running and healthy
if check_chromedriver; then
    echo -e "${GREEN}ChromeDriver already running and healthy${NC}"
else
    start_chromedriver || exit 1
fi

# Final health check
echo -e "${YELLOW}Running health check...${NC}"
if check_chromedriver; then
    echo -e "${GREEN}✓ ChromeDriver is healthy${NC}"
else
    echo -e "${RED}✗ ChromeDriver health check failed${NC}"
    exit 1
fi

# Run E2E tests
echo -e "${YELLOW}Running E2E tests...${NC}"

# Timeout for entire test suite (5 minutes)
TEST_TIMEOUT=300

if [ "$1" == "--individual" ]; then
    echo -e "${YELLOW}Running tests individually to avoid cleanup issues${NC}"
    
    # Get list of test files
    TEST_FILES=$(find tests/e2e -name "*Test.php" -not -name "E2ETestBase.php" -not -name "VerboseTestListener.php")
    
    TOTAL=0
    PASSED=0
    FAILED=0
    
    for TEST_FILE in $TEST_FILES; do
        echo -e "\n${YELLOW}Running $(basename $TEST_FILE)...${NC}"
        
        # Run with timeout (60 seconds per test file)
        if timeout 60 vendor/bin/phpunit "$TEST_FILE" --no-coverage; then
            PASSED=$((PASSED + 1))
        else
            EXIT_CODE=$?
            if [ $EXIT_CODE -eq 124 ]; then
                echo -e "${RED}✗ Test timed out after 60 seconds${NC}"
            fi
            FAILED=$((FAILED + 1))
        fi
        TOTAL=$((TOTAL + 1))
        
        # Small delay between tests
        sleep 2
    done
    
    echo -e "\n${GREEN}=== E2E Test Summary ===${NC}"
    echo -e "Total: $TOTAL"
    echo -e "${GREEN}Passed: $PASSED${NC}"
    if [ $FAILED -gt 0 ]; then
        echo -e "${RED}Failed: $FAILED${NC}"
        exit 1
    else
        echo -e "${GREEN}All tests passed!${NC}"
    fi
else
    # Run full suite with timeout
    echo -e "${YELLOW}Running full suite with ${TEST_TIMEOUT}s timeout...${NC}"
    if timeout $TEST_TIMEOUT vendor/bin/phpunit --testsuite=E2E --no-coverage; then
        echo -e "${GREEN}✓ All tests passed${NC}"
    else
        EXIT_CODE=$?
        if [ $EXIT_CODE -eq 124 ]; then
            echo -e "${RED}✗ Test suite timed out after ${TEST_TIMEOUT} seconds${NC}"
            exit 1
        else
            echo -e "${RED}✗ Tests failed${NC}"
            exit $EXIT_CODE
        fi
    fi
fi

echo -e "${GREEN}=== E2E Tests Complete ===${NC}"
