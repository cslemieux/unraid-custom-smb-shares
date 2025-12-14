#!/bin/bash
# Local CI simulation - runs all checks that CI will run
set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
NC='\033[0m'

log_step() {
    echo -e "\n${BLUE}▶${NC} $1"
}

log_success() {
    echo -e "${GREEN}✓${NC} $1"
}

log_error() {
    echo -e "${RED}✗${NC} $1"
}

FAILED=0

run_check() {
    local name=$1
    local cmd=$2
    
    log_step "$name"
    if eval "$cmd"; then
        log_success "$name passed"
    else
        log_error "$name failed"
        FAILED=$((FAILED + 1))
    fi
}

echo "╔════════════════════════════════════════════════════════════╗"
echo "║  Local CI Simulation                                       ║"
echo "╚════════════════════════════════════════════════════════════╝"

# Validate composer.json
run_check "Validate composer.json" "composer validate --strict"

# Install dependencies
log_step "Installing dependencies"
composer install --prefer-dist --no-progress --quiet
log_success "Dependencies installed"

# Code quality checks
run_check "Code linting (PSR-12)" "composer lint"
run_check "Static analysis (PHPStan level 8)" "composer analyze"
run_check "JavaScript syntax check" "composer check:js"

# Tests
run_check "Unit tests" "composer test:unit"
run_check "Integration tests" "composer test:integration"

# E2E tests (optional)
if pgrep -x "chromedriver" > /dev/null; then
    run_check "E2E tests" "composer test:e2e"
else
    echo -e "\n${BLUE}▶${NC} E2E tests"
    echo "  ⊘ Skipped (ChromeDriver not running)"
fi

# Coverage
log_step "Generating coverage report"
composer test:coverage > /dev/null 2>&1
if [ -d "coverage" ]; then
    log_success "Coverage report generated"
else
    log_error "Coverage generation failed"
    FAILED=$((FAILED + 1))
fi

# Summary
echo ""
echo "╔════════════════════════════════════════════════════════════╗"
if [ $FAILED -eq 0 ]; then
    echo "║  ${GREEN}✓ All checks passed!${NC}                                    ║"
    echo "╚════════════════════════════════════════════════════════════╝"
    exit 0
else
    echo "║  ${RED}✗ $FAILED check(s) failed${NC}                                    ║"
    echo "╚════════════════════════════════════════════════════════════╝"
    exit 1
fi
