#!/bin/bash
# Production-grade build script for Custom SMB Shares plugin
set -e

# Usage
if [ "$1" = "-h" ] || [ "$1" = "--help" ]; then
    echo "Usage: $0 [OPTIONS]"
    echo ""
    echo "Options:"
    echo "  -f, --fast           Fast build (skip tests)"
    echo "  -h, --help           Show this help"
    echo ""
    echo "Environment:"
    echo "  GENERATE_COVERAGE=true   Generate coverage report (slow)"
    echo ""
    echo "Examples:"
    echo "  $0                   Full build with tests (~10s)"
    echo "  $0 --fast            Fast build without tests (~3s)"
    echo "  GENERATE_COVERAGE=true $0   Full build with coverage (~6min)"
    exit 0
fi

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
VERSION="2025.01.18"
PLUGIN_NAME="custom.smb.shares"
BUILD_DIR="build"
ARCHIVE_DIR="archive"
COVERAGE_DIR="coverage"

# Functions
log_info() {
    echo -e "${BLUE}ℹ${NC} $1"
}

log_success() {
    echo -e "${GREEN}✓${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}⚠${NC} $1"
}

log_error() {
    echo -e "${RED}✗${NC} $1"
}

run_step() {
    local step_name=$1
    local step_cmd=$2
    
    echo ""
    log_info "Running: $step_name"
    if eval "$step_cmd"; then
        log_success "$step_name passed"
        return 0
    else
        log_error "$step_name failed"
        return 1
    fi
}

# Main build process
main() {
    # Parse arguments
    FAST_BUILD=false
    if [ "$1" = "--fast" ] || [ "$1" = "-f" ]; then
        FAST_BUILD=true
    fi
    
    echo "╔════════════════════════════════════════════════════════════╗"
    echo "║  Custom SMB Shares Plugin - Production Build              ║"
    echo "║  Version: $VERSION                                    ║"
    if [ "$FAST_BUILD" = true ]; then
        echo "║  Mode: FAST (skip tests)                                   ║"
    fi
    echo "╚════════════════════════════════════════════════════════════╝"
    echo ""
    
    # Check dependencies
    log_info "Checking dependencies..."
    if ! command -v composer &> /dev/null; then
        log_error "Composer not found. Please install composer."
        exit 1
    fi
    log_success "Dependencies OK"
    
    # Validate source code syntax
    run_step "Source Code Validation" "composer validate:sources" || {
        log_error "Source validation failed. Fix syntax errors before building."
        exit 1
    }
    
    # Install/update dependencies
    if [ ! -d "vendor" ]; then
        log_info "Installing dependencies..."
        composer install --no-interaction --prefer-dist
        log_success "Dependencies installed"
    fi
    
    # Step 1: Code Linting (PSR-12)
    log_info "Auto-fixing linting issues..."
    composer lint:fix > /dev/null 2>&1 || true
    
    run_step "Code Linting (PSR-12)" "composer lint" || {
        log_error "Linting failed after auto-fix. Manual fixes required."
        exit 1
    }
    
    # Step 2: Static Analysis (PHPStan level 8)
    run_step "Static Analysis (PHPStan level 8)" "composer analyze" || {
        log_error "Static analysis failed. Fix errors before building."
        exit 1
    }
    
    # Step 3: JavaScript Syntax Validation
    log_info "Validating JavaScript syntax..."
    if ! command -v jshint &> /dev/null; then
        log_error "JSHint not found. Install with: npm install -g jshint"
        exit 1
    fi
    
    JS_ERRORS=0
    for js_file in source/usr/local/emhttp/plugins/${PLUGIN_NAME}/js/*.js; do
        if [ -f "$js_file" ]; then
            if jshint "$js_file" 2>&1; then
                log_success "$(basename $js_file) - syntax OK"
            else
                log_error "$(basename $js_file) - syntax errors"
                JS_ERRORS=$((JS_ERRORS + 1))
            fi
        fi
    done
    
    if [ $JS_ERRORS -gt 0 ]; then
        log_error "$JS_ERRORS JavaScript file(s) have syntax errors"
        exit 1
    fi
    
    # Step 4: Run Tests
    if [ "$FAST_BUILD" = true ]; then
        log_warning "Tests skipped (fast build mode)"
    else
        run_step "Unit Tests" "composer test:unit" || {
            log_error "Unit tests failed"
            exit 1
        }
        
        run_step "Integration Tests" "composer test:integration" || {
            log_error "Integration tests failed"
            exit 1
        }
    fi
    
    # E2E tests disabled in build (run manually with: composer test:e2e)
    log_warning "E2E tests skipped in build. Run manually if needed: composer test:e2e"
    
    # Step 5: Generate Coverage Report (optional, slow)
    if [ "${GENERATE_COVERAGE:-false}" = "true" ]; then
        log_info "Generating test coverage report..."
        composer test:coverage > /dev/null 2>&1 || true
        if [ -d "$COVERAGE_DIR" ]; then
            log_success "Coverage report generated: $COVERAGE_DIR/index.html"
        fi
    else
        log_info "Coverage report skipped (set GENERATE_COVERAGE=true to enable)"
    fi
    
    # Step 6: Build Package
    echo ""
    log_info "Building plugin package..."
    
    # Clean previous builds
    rm -rf ${BUILD_DIR} ${ARCHIVE_DIR}
    mkdir -p ${BUILD_DIR} ${ARCHIVE_DIR}
    
    # Copy source files
    cp -r source/* ${BUILD_DIR}/
    
    # Write VERSION file
    echo "${VERSION}" > ${BUILD_DIR}/usr/local/emhttp/plugins/${PLUGIN_NAME}/VERSION
    
    # Create tarball (Unraid uses .txz which is tar + xz compression)
    cd ${BUILD_DIR}
    tar --owner=root --group=root -cJf ../${ARCHIVE_DIR}/${PLUGIN_NAME}-${VERSION}.txz *
    cd ..
    
    # Calculate MD5
    cd ${ARCHIVE_DIR}
    md5sum ${PLUGIN_NAME}-${VERSION}.txz | awk '{print $1}' > ${PLUGIN_NAME}-${VERSION}.md5
    MD5=$(cat ${PLUGIN_NAME}-${VERSION}.md5)
    cd ..
    
    log_success "Package built successfully"
    
    # Summary
    echo ""
    echo "╔════════════════════════════════════════════════════════════╗"
    echo "║  Build Complete!                                           ║"
    echo "╚════════════════════════════════════════════════════════════╝"
    echo ""
    echo "Package: ${ARCHIVE_DIR}/${PLUGIN_NAME}-${VERSION}.txz"
    echo "MD5:     ${MD5}"
    echo "Size:    $(du -h ${ARCHIVE_DIR}/${PLUGIN_NAME}-${VERSION}.txz | cut -f1)"
    echo ""
    echo "Update .plg file with this MD5:"
    echo "<!ENTITY md5       \"${MD5}\">"
    echo ""
    echo "Next steps:"
    echo "  1. Update custom.smb.shares.plg with new MD5"
    echo "  2. Deploy to test: ./deploy.sh"
    echo "  3. Test on Unraid server"
    echo "  4. Commit and push to GitHub"
    echo ""
}

# Run main function
main "$@"
