<?php

/**
 * Scans code for external dependencies and generates injection config
 */
class DependencyScanner
{
    private static $knownDependencies = [
        // jQuery UI
        'jqueryui' => [
            'js' => 'https://code.jquery.com/ui/1.13.2/jquery-ui.min.js',
            'css' => 'https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.min.css',
            'patterns' => ['\.dialog\(', 'dialogStyle\(', 'jquery-ui', 'ui-dialog']
        ],
        
        // jQuery plugins
        'tablesorter' => [
            'js' => 'https://cdnjs.cloudflare.com/ajax/libs/jquery.tablesorter/2.31.3/js/jquery.tablesorter.min.js',
            'css' => 'https://cdnjs.cloudflare.com/ajax/libs/jquery.tablesorter/2.31.3/css/theme.default.min.css',
            'patterns' => ['\.tablesorter\(', 'tablesorter']
        ],
        
        // fileTree (Dynamix plugin) - served locally
        'filetree' => [
            'js' => '/test-assets/jquery.filetree.js',
            'css' => '/test-assets/jquery.filetree.css',
            'patterns' => ['\.fileTree\(', 'fileTreeAttach\(', 'jquery\.filetree', 'togglePathBrowser']
        ],
        
        // SweetAlert
        'sweetalert' => [
            'js' => 'https://cdnjs.cloudflare.com/ajax/libs/sweetalert/1.1.3/sweetalert.min.js',
            'css' => 'https://cdnjs.cloudflare.com/ajax/libs/sweetalert/1.1.3/sweetalert.min.css',
            'patterns' => ['swal\(', 'sweetalert', 'window\.swal']
        ],
        
        // Font Awesome
        'fontawesome' => [
            'css' => 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
            'patterns' => ['fa-', 'class="fa ', '<i class="fa']
        ],
        
        // jQuery (always included)
        'jquery' => [
            'js' => 'https://code.jquery.com/jquery-3.6.0.min.js',
            'patterns' => ['\$\(', 'jQuery']
        ]
    ];
    
    /**
     * Parse script/link tags from page files
     */
    private static function parsePageTags(string $content): array
    {
        $dependencies = [];
        
        // Find script tags referencing Dynamix plugins
        if (preg_match_all('/<script[^>]+src=["\']([^"\']+dynamix[^"\']+)["\']/', $content, $matches)) {
            foreach ($matches[1] as $src) {
                if (strpos($src, 'jquery.filetree.js') !== false) {
                    $dependencies[] = 'filetree';
                }
            }
        }
        
        // Find link tags referencing Dynamix styles
        if (preg_match_all('/<link[^>]+href=["\']([^"\']+dynamix[^"\']+)["\']/', $content, $matches)) {
            foreach ($matches[1] as $href) {
                if (strpos($href, 'jquery.filetree.css') !== false) {
                    $dependencies[] = 'filetree';
                }
            }
        }
        
        return array_unique($dependencies);
    }
    
    /**
     * Scan files and detect required dependencies
     */
    public static function scan(array $files): array
    {
        $required = [];
        
        foreach ($files as $file) {
            if (!file_exists($file)) {
                continue;
            }
            
            $content = file_get_contents($file);
            
            // First, parse actual script/link tags
            $tagDeps = self::parsePageTags($content);
            foreach ($tagDeps as $dep) {
                if (isset(self::$knownDependencies[$dep])) {
                    $required[$dep] = self::$knownDependencies[$dep];
                }
            }
            
            // Then scan for usage patterns
            foreach (self::$knownDependencies as $name => $config) {
                if (isset($required[$name])) {
                    continue; // Already detected
                }
                
                foreach ($config['patterns'] as $pattern) {
                    if (preg_match('/' . $pattern . '/', $content)) {
                        $required[$name] = $config;
                        break;
                    }
                }
            }
        }
        
        return $required;
    }
    
    /**
     * Generate HTML tags for dependencies
     */
    public static function generateTags(array $dependencies): array
    {
        $tags = ['css' => [], 'js' => []];
        
        // jQuery must be first
        if (isset($dependencies['jquery'])) {
            if (isset($dependencies['jquery']['js'])) {
                $tags['js'][] = '<script src="' . $dependencies['jquery']['js'] . '"></script>';
            }
            unset($dependencies['jquery']);
        }
        
        // Then CSS
        foreach ($dependencies as $name => $config) {
            if (isset($config['css'])) {
                $tags['css'][] = '<link rel="stylesheet" href="' . $config['css'] . '">';
            }
        }
        
        // Then JS
        foreach ($dependencies as $name => $config) {
            if (isset($config['js'])) {
                $tags['js'][] = '<script src="' . $config['js'] . '"></script>';
            }
        }
        
        return $tags;
    }
    
    /**
     * Scan plugin directory and generate dependency config
     */
    public static function scanPlugin(string $pluginDir): array
    {
        $files = [];
        
        // Scan .page files
        foreach (glob($pluginDir . '/*.page') as $file) {
            $files[] = $file;
        }
        
        // Scan PHP files
        foreach (glob($pluginDir . '/*.php') as $file) {
            $files[] = $file;
        }
        
        // Scan JS files
        if (is_dir($pluginDir . '/js')) {
            foreach (glob($pluginDir . '/js/*.js') as $file) {
                $files[] = $file;
            }
        }
        
        return self::scan($files);
    }
    
    /**
     * Generate dependency config file
     */
    public static function generateConfig(string $pluginDir, string $outputFile): void
    {
        $dependencies = self::scanPlugin($pluginDir);
        
        $config = [
            'generated' => date('Y-m-d H:i:s'),
            'plugin' => basename($pluginDir),
            'dependencies' => array_keys($dependencies),
            'tags' => self::generateTags($dependencies)
        ];
        
        file_put_contents($outputFile, json_encode($config, JSON_PRETTY_PRINT));
        
        echo "Generated dependency config: $outputFile\n";
        echo "Dependencies detected: " . implode(', ', $config['dependencies']) . "\n";
    }
    
    /**
     * Scan multiple paths and generate config
     */
    public static function scanAndGenerate(array $paths, string $outputFile): void
    {
        $allFiles = [];
        foreach ($paths as $path) {
            if (is_dir($path)) {
                $allFiles = array_merge($allFiles, glob($path . '/*.page'));
                $allFiles = array_merge($allFiles, glob($path . '/*.php'));
                $allFiles = array_merge($allFiles, glob($path . '/js/*.js'));
            } elseif (file_exists($path)) {
                $allFiles[] = $path;
            }
        }
        
        $dependencies = self::scan($allFiles);
        
        $config = [
            'generated' => date('Y-m-d H:i:s'),
            'paths' => $paths,
            'dependencies' => array_keys($dependencies),
            'tags' => self::generateTags($dependencies)
        ];
        
        file_put_contents($outputFile, json_encode($config, JSON_PRETTY_PRINT));
    }
}

// CLI interface
if (php_sapi_name() === 'cli' && isset($argv[1], $argv[2])) {
    DependencyScanner::generateConfig($argv[1], $argv[2]);
}
