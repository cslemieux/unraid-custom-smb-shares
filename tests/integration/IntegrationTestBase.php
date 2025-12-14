<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../helpers/ChrootTestEnvironment.php';

abstract class IntegrationTestBase extends TestCase
{
    protected static $configDir;
    private static $initialized = false;
    
    public static function setUpBeforeClass(): void
    {
        if (!self::$initialized) {
            self::$configDir = ChrootTestEnvironment::setup();
            if (!defined('CONFIG_BASE')) {
                define('CONFIG_BASE', self::$configDir);
            }
            require_once __DIR__ . '/../../source/usr/local/emhttp/plugins/custom.smb.shares/include/lib.php';
            self::$initialized = true;
        }
    }
}
