#!/usr/bin/env node

const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

const errors = [];
const warnings = [];

// Find all .page files
function findPageFiles(dir) {
    const files = [];
    const items = fs.readdirSync(dir, { withFileTypes: true });
    
    for (const item of items) {
        const fullPath = path.join(dir, item.name);
        if (item.isDirectory()) {
            files.push(...findPageFiles(fullPath));
        } else if (item.name.endsWith('.page')) {
            files.push(fullPath);
        }
    }
    return files;
}

// Extract and validate JavaScript from .page file
function validatePageFile(filePath) {
    console.log(`\nValidating: ${filePath}`);
    
    const content = fs.readFileSync(filePath, 'utf8');
    const scriptMatch = content.match(/<script>([\s\S]*?)<\/script>/);
    
    if (!scriptMatch) {
        warnings.push(`${filePath}: No <script> block found`);
        return;
    }
    
    const jsContent = scriptMatch[1];
    const tempFile = `/tmp/validate-${Date.now()}.js`;
    
    try {
        fs.writeFileSync(tempFile, jsContent);
        execSync(`node --check ${tempFile}`, { stdio: 'pipe' });
        console.log('  ✓ JavaScript syntax valid');
    } catch (error) {
        errors.push(`${filePath}: JavaScript syntax error\n${error.stderr.toString()}`);
        console.log('  ✗ JavaScript syntax INVALID');
    } finally {
        if (fs.existsSync(tempFile)) {
            fs.unlinkSync(tempFile);
        }
    }
}

// Find all .php files
function findPhpFiles(dir) {
    const files = [];
    const items = fs.readdirSync(dir, { withFileTypes: true });
    
    for (const item of items) {
        const fullPath = path.join(dir, item.name);
        if (item.isDirectory() && !item.name.startsWith('.')) {
            files.push(...findPhpFiles(fullPath));
        } else if (item.name.endsWith('.php')) {
            files.push(fullPath);
        }
    }
    return files;
}

// Validate PHP syntax
function validatePhpFile(filePath) {
    console.log(`\nValidating: ${filePath}`);
    
    try {
        execSync(`php -l ${filePath}`, { stdio: 'pipe' });
        console.log('  ✓ PHP syntax valid');
    } catch (error) {
        errors.push(`${filePath}: PHP syntax error\n${error.stdout.toString()}`);
        console.log('  ✗ PHP syntax INVALID');
    }
}

// Find all .js files
function findJsFiles(dir) {
    const files = [];
    const items = fs.readdirSync(dir, { withFileTypes: true });
    
    for (const item of items) {
        const fullPath = path.join(dir, item.name);
        if (item.isDirectory() && !item.name.startsWith('.')) {
            files.push(...findJsFiles(fullPath));
        } else if (item.name.endsWith('.js')) {
            files.push(fullPath);
        }
    }
    return files;
}

// Validate standalone JavaScript file
function validateJsFile(filePath) {
    console.log(`\nValidating: ${filePath}`);
    
    try {
        execSync(`node --check ${filePath}`, { stdio: 'pipe' });
        console.log('  ✓ JavaScript syntax valid');
    } catch (error) {
        errors.push(`${filePath}: JavaScript syntax error\n${error.stderr.toString()}`);
        console.log('  ✗ JavaScript syntax INVALID');
    }
}

// Main
console.log('=== Source Code Validation ===\n');

const sourceDir = path.join(__dirname, '../source/usr/local/emhttp/plugins/custom.smb.shares');

// Validate .page files
console.log('\n--- Validating .page files ---');
const pageFiles = findPageFiles(sourceDir);
pageFiles.forEach(validatePageFile);

// Validate .php files
console.log('\n--- Validating .php files ---');
const phpFiles = findPhpFiles(sourceDir);
phpFiles.forEach(validatePhpFile);

// Validate .js files
console.log('\n--- Validating .js files ---');
const jsFiles = findJsFiles(sourceDir);
jsFiles.forEach(validateJsFile);

// Report
console.log('\n=== Validation Summary ===');
console.log(`Files checked: ${pageFiles.length + phpFiles.length + jsFiles.length}`);
console.log(`Errors: ${errors.length}`);
console.log(`Warnings: ${warnings.length}`);

if (warnings.length > 0) {
    console.log('\nWarnings:');
    warnings.forEach(w => console.log(`  ⚠ ${w}`));
}

if (errors.length > 0) {
    console.log('\nErrors:');
    errors.forEach(e => console.log(`  ✗ ${e}`));
    process.exit(1);
}

console.log('\n✓ All validations passed!');
