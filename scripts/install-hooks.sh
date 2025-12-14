#!/bin/bash
# Install Git hooks for automated quality checks

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
GIT_DIR="$(git rev-parse --git-dir 2>/dev/null)"

if [ -z "$GIT_DIR" ]; then
    echo "Error: Not in a git repository"
    exit 1
fi

echo "Installing Git hooks..."

# Install pre-commit hook
cp "$SCRIPT_DIR/pre-commit" "$GIT_DIR/hooks/pre-commit"
chmod +x "$GIT_DIR/hooks/pre-commit"

echo "✓ Git hooks installed successfully!"
echo ""
echo "The following checks will run before each commit:"
echo "  • Code style (PSR-12)"
echo "  • Static analysis (PHPStan level 8)"
echo "  • Unit tests"
echo ""
echo "To skip hooks temporarily: git commit --no-verify"
