<?php

use PHPUnit\Framework\TestSuite;

require_once __DIR__ . '/../helpers/ChrootTestEnvironment.php';

/**
 * Integration Test Suite
 * 
 * Sets up shared test environment once for all integration tests
 */
class IntegrationTestSuite extends TestSuite
{
    public static function suite()
    {
        $suite = new self('Integration Tests');
        
        // Add all integration test files
        $suite->addTestFile(__DIR__ . '/APIEndpointsTest.php');
        $suite->addTestFile(__DIR__ . '/CSRFVerificationTest.php');
        $suite->addTestFile(__DIR__ . '/ErrorHandlingTest.php');
        $suite->addTestFile(__DIR__ . '/PHPScriptsIntegrationTest.php');
        $suite->addTestFile(__DIR__ . '/SambaInteractionTest.php');
        $suite->addTestFile(__DIR__ . '/ShareCRUDTest.php');
        
        return $suite;
    }
    
    protected function setUp(): void
    {
        // Setup shared environment once
        $configDir = ChrootTestEnvironment::setup();
        if (!defined('CONFIG_BASE')) {
            define('CONFIG_BASE', $configDir);
        }
    }
    
    protected function tearDown(): void
    {
        // Don't teardown - let OS clean up /tmp on reboot
    }
}
