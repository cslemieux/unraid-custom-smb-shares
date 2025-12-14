#!/bin/bash
# Automated release script - creates tagged release with package
set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log_info() { echo -e "${BLUE}ℹ${NC} $1"; }
log_success() { echo -e "${GREEN}✓${NC} $1"; }
log_warning() { echo -e "${YELLOW}⚠${NC} $1"; }
log_error() { echo -e "${RED}✗${NC} $1"; }

# Check if version provided
if [ -z "$1" ]; then
    log_error "Usage: $0 <version> [release-notes]"
    echo "Example: $0 2025.01.19 'Bug fixes and improvements'"
    exit 1
fi

VERSION=$1
NOTES=${2:-"Release $VERSION"}
PLUGIN_NAME="custom.smb.shares"

echo "╔════════════════════════════════════════════════════════════╗"
echo "║  Release Automation                                        ║"
echo "║  Version: $VERSION                                    ║"
echo "╚════════════════════════════════════════════════════════════╝"
echo ""

# Check git status
if [ -n "$(git status --porcelain)" ]; then
    log_error "Working directory not clean. Commit or stash changes first."
    exit 1
fi
log_success "Working directory clean"

# Check if on main branch
BRANCH=$(git rev-parse --abbrev-ref HEAD)
if [ "$BRANCH" != "main" ]; then
    log_warning "Not on main branch (currently on $BRANCH)"
    read -p "Continue anyway? (y/N) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        exit 1
    fi
fi

# Run all checks
log_info "Running CI checks..."
if ! ./scripts/ci-local.sh; then
    log_error "CI checks failed. Fix issues before releasing."
    exit 1
fi

# Update version in files
log_info "Updating version in files..."
sed -i.bak "s/VERSION=\"[0-9.]*\"/VERSION=\"$VERSION\"/" build.sh
rm build.sh.bak
log_success "Version updated in build.sh"

# Build package
log_info "Building release package..."
if ! ./build.sh; then
    log_error "Build failed"
    exit 1
fi

# Get MD5
MD5=$(cat archive/${PLUGIN_NAME}-${VERSION}.md5)
log_success "Package built (MD5: $MD5)"

# Update .plg file
log_info "Updating .plg file..."
PLG_FILE="${PLUGIN_NAME}.plg"
if [ -f "$PLG_FILE" ]; then
    # Update version
    sed -i.bak "s/<!ENTITY version   \"[^\"]*\">/<!ENTITY version   \"$VERSION\">/" "$PLG_FILE"
    # Update MD5
    sed -i.bak "s/<!ENTITY md5       \"[^\"]*\">/<!ENTITY md5       \"$MD5\">/" "$PLG_FILE"
    rm ${PLG_FILE}.bak
    log_success ".plg file updated"
else
    log_warning ".plg file not found, skipping"
fi

# Commit changes
log_info "Committing release changes..."
git add build.sh "$PLG_FILE" 2>/dev/null || true
git commit -m "Release $VERSION" -m "$NOTES"
log_success "Changes committed"

# Create tag
log_info "Creating git tag..."
git tag -a "v$VERSION" -m "Release $VERSION" -m "$NOTES"
log_success "Tag v$VERSION created"

# Summary
echo ""
echo "╔════════════════════════════════════════════════════════════╗"
echo "║  Release Ready!                                            ║"
echo "╚════════════════════════════════════════════════════════════╝"
echo ""
echo "Version:  $VERSION"
echo "Package:  archive/${PLUGIN_NAME}-${VERSION}.txz"
echo "MD5:      $MD5"
echo "Tag:      v$VERSION"
echo ""
echo "Next steps:"
echo "  1. Review changes: git show v$VERSION"
echo "  2. Push to GitHub: git push && git push --tags"
echo "  3. Create GitHub release with package"
echo "  4. Update Community Applications"
echo ""
