#!/bin/bash
# Find dead/orphaned files in the plugin

PLUGIN_DIR="source/usr/local/emhttp/plugins/custom.smb.shares"

echo "=== Dead Code Analysis ==="
echo ""

# 1. Find .page files that are just redirects (not real pages)
echo "## Redirect-only .page files (candidates for removal):"
for f in "$PLUGIN_DIR"/*.page; do
    if grep -q "header('Location:" "$f" 2>/dev/null; then
        echo "  - $(basename "$f") (redirect to: $(grep -o "Location: [^']*" "$f" | head -1))"
    fi
done
echo ""

# 2. Find PHP files not referenced anywhere
echo "## Unreferenced PHP files:"
for f in "$PLUGIN_DIR"/*.php; do
    basename=$(basename "$f")
    # Skip lib.php and files in include/
    if [[ "$basename" == "lib.php" ]]; then continue; fi
    
    # Check if referenced in any .page, .php, or .js file
    refs=$(grep -rln "$basename" "$PLUGIN_DIR" --include="*.page" --include="*.php" --include="*.js" 2>/dev/null | grep -v "^$f$" | wc -l)
    
    if [[ $refs -eq 0 ]]; then
        # Also check if it's an API endpoint (called via AJAX)
        if ! grep -q "action ===" "$f" 2>/dev/null; then
            echo "  - $basename (no references found)"
        fi
    fi
done
echo ""

# 3. Find functions defined but never called
echo "## Potentially unused functions in lib.php:"
LIB_FILE="$PLUGIN_DIR/include/lib.php"
if [[ -f "$LIB_FILE" ]]; then
    # Extract function names
    grep -o "^function [a-zA-Z_]*" "$LIB_FILE" | sed 's/function //' | while read func; do
        # Count references (excluding the definition itself)
        refs=$(grep -rn "\b$func\b" "$PLUGIN_DIR" --include="*.php" --include="*.page" 2>/dev/null | grep -v "^function $func" | wc -l)
        if [[ $refs -le 1 ]]; then
            echo "  - $func() (only $refs reference(s))"
        fi
    done
fi
echo ""

# 4. Find JS functions defined but potentially unused
echo "## JS functions with inline onclick (scope issues):"
grep -rn "onclick=['\"]" "$PLUGIN_DIR"/*.page 2>/dev/null | grep -v "data-" | head -20
echo ""

# 5. Check for duplicate functionality
echo "## Potential duplicate files (similar names):"
ls "$PLUGIN_DIR"/*.page 2>/dev/null | xargs -n1 basename | sort | uniq -d
ls "$PLUGIN_DIR"/*.php 2>/dev/null | xargs -n1 basename | sort | uniq -d
echo ""

# 6. Files with old timestamps that might be stale
echo "## Files not modified in current development (before Dec 13):"
find "$PLUGIN_DIR" -maxdepth 1 \( -name "*.php" -o -name "*.page" \) -not -newermt "2024-12-13" -exec basename {} \;
echo ""

echo "=== Analysis Complete ==="
