#!/bin/bash
# Production-grade deployment script
set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Configuration
PLUGIN_NAME="custom.smb.shares"
UNRAID_SERVER="${UNRAID_SERVER:-YOUR_UNRAID_SERVER}"
UNRAID_USER="${UNRAID_USER:-root}"
PLUGIN_DIR="/usr/local/emhttp/plugins/${PLUGIN_NAME}"
CONFIG_DIR="/boot/config/plugins/${PLUGIN_NAME}"

# Functions
log_info() { echo -e "${BLUE}ℹ${NC} $1"; }
log_success() { echo -e "${GREEN}✓${NC} $1"; }
log_warning() { echo -e "${YELLOW}⚠${NC} $1"; }
log_error() { echo -e "${RED}✗${NC} $1"; }

# Setup SSH connection sharing for faster operations
setup_ssh() {
    SSH_CONTROL_PATH="/tmp/ssh-unraid-$$"
    ssh -o ControlMaster=yes -o ControlPath="$SSH_CONTROL_PATH" -o ControlPersist=30 -o BatchMode=yes -o ConnectTimeout=5 -Nf "${UNRAID_USER}@${UNRAID_SERVER}" 2>/dev/null || true
    SSH_OPTS="-o ControlPath=$SSH_CONTROL_PATH -o BatchMode=yes"
}

cleanup_ssh() {
    if [ -n "$SSH_CONTROL_PATH" ]; then
        ssh -o ControlPath="$SSH_CONTROL_PATH" -O exit "${UNRAID_USER}@${UNRAID_SERVER}" 2>/dev/null || true
    fi
}
trap cleanup_ssh EXIT

# Main deployment
main() {
    echo "╔════════════════════════════════════════════════════════════╗"
    echo "║  Custom SMB Shares Plugin - Deployment                    ║"
    echo "╚════════════════════════════════════════════════════════════╝"
    echo ""
    
    # Check if server is reachable
    log_info "Checking connection to ${UNRAID_SERVER}..."
    if ! ping -c 1 -W 2 ${UNRAID_SERVER} > /dev/null 2>&1; then
        log_error "Cannot reach ${UNRAID_SERVER}"
        exit 1
    fi
    log_success "Server reachable"
    
    # Verify SSH key authentication
    log_info "Verifying SSH access..."
    if ! ssh -o BatchMode=yes -o ConnectTimeout=5 "${UNRAID_USER}@${UNRAID_SERVER}" "echo 'SSH OK'" &>/dev/null; then
        log_error "SSH key authentication not working"
        echo "Run: ./scripts/setup-ssh-key.sh"
        exit 1
    fi
    log_success "SSH authentication verified"
    
    # Setup SSH connection sharing
    setup_ssh
    
    # Check if archive exists, auto-build if not
    if [ ! -d "archive" ] || [ -z "$(ls -A archive/*.txz 2>/dev/null)" ]; then
        log_warning "No package found. Running build..."
        ./build.sh --fast || {
            log_error "Build failed"
            exit 1
        }
    fi
    
    # Get latest package
    PACKAGE=$(ls -t archive/*.txz | head -1)
    MD5_FILE="${PACKAGE%.txz}.md5"
    PLG_FILE="custom.smb.shares.plg"
    
    log_info "Deploying: $(basename $PACKAGE)"
    
    # Ensure config directory exists
    ssh $SSH_OPTS "${UNRAID_USER}@${UNRAID_SERVER}" "mkdir -p ${CONFIG_DIR}"
    
    # Deploy package
    log_info "Copying package to server..."
    scp $SSH_OPTS "$PACKAGE" "${UNRAID_USER}@${UNRAID_SERVER}:${CONFIG_DIR}/" || {
        log_error "Failed to copy package"
        exit 1
    }
    log_success "Package copied"
    
    # Deploy MD5
    if [ -f "$MD5_FILE" ]; then
        log_info "Copying MD5 checksum..."
        scp $SSH_OPTS "$MD5_FILE" "${UNRAID_USER}@${UNRAID_SERVER}:${CONFIG_DIR}/"
        log_success "MD5 copied"
    fi
    
    # Deploy .plg file
    if [ -f "$PLG_FILE" ]; then
        log_info "Copying .plg manifest..."
        scp $SSH_OPTS "$PLG_FILE" "${UNRAID_USER}@${UNRAID_SERVER}:${CONFIG_DIR}/"
        log_success ".plg manifest copied"
    fi
    
    # Clean old installation and extract new package
    log_info "Installing plugin..."
    ssh $SSH_OPTS "${UNRAID_USER}@${UNRAID_SERVER}" "
        # Remove old plugin files
        rm -rf ${PLUGIN_DIR}/* 2>/dev/null || true
        
        # Extract package (tar has --owner/--group=root from build)
        cd ${CONFIG_DIR} && tar -xJf $(basename $PACKAGE) -C /
        
        # Fix permissions (in case macOS metadata leaked through)
        chmod -R 755 ${PLUGIN_DIR}
        find ${PLUGIN_DIR} -type f -name '*.php' -exec chmod 644 {} \;
        find ${PLUGIN_DIR} -type f -name '*.page' -exec chmod 644 {} \;
        find ${PLUGIN_DIR} -type f -name '*.js' -exec chmod 644 {} \;
        find ${PLUGIN_DIR} -type f -name '*.css' -exec chmod 644 {} \;
        
        # Initialize shares.json if it doesn't exist
        if [ ! -f ${CONFIG_DIR}/shares.json ]; then
            echo '[]' > ${CONFIG_DIR}/shares.json
        fi
    " || {
        log_error "Failed to install package"
        exit 1
    }
    log_success "Package installed"
    
    # Verify installation
    log_info "Verifying installation..."
    VERIFY_OUTPUT=$(ssh $SSH_OPTS "${UNRAID_USER}@${UNRAID_SERVER}" "
        # Check main page exists
        if [ ! -f ${PLUGIN_DIR}/SMBShares.page ]; then
            echo 'ERROR: SMBShares.page not found'
            exit 1
        fi
        
        # Check file size
        PAGE_SIZE=\$(wc -c < ${PLUGIN_DIR}/SMBShares.page)
        if [ \$PAGE_SIZE -lt 500 ]; then
            echo \"ERROR: SMBShares.page too small (\$PAGE_SIZE bytes)\"
            exit 1
        fi
        
        echo 'Installation verified'
        echo \"Main page: \$PAGE_SIZE bytes\"
        echo \"Files: \$(find ${PLUGIN_DIR} -type f | wc -l)\"
    " 2>&1) || {
        log_error "Verification failed"
        echo "$VERIFY_OUTPUT"
        exit 1
    }
    echo "$VERIFY_OUTPUT"
    log_success "Installation verified"
    
    # Summary
    echo ""
    echo "╔════════════════════════════════════════════════════════════╗"
    echo "║  Deployment Complete!                                      ║"
    echo "╚════════════════════════════════════════════════════════════╝"
    echo ""
    echo "Server:  ${UNRAID_SERVER}"
    echo "Package: $(basename $PACKAGE)"
    [ -f "$MD5_FILE" ] && echo "MD5:     $(cat $MD5_FILE)"
    echo ""
    echo "Access plugin at:"
    echo "  http://${UNRAID_SERVER}/SMBShares"
    echo ""
}

# Run main function
main "$@"
