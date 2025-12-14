<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Test permission checkbox ↔ octal conversion logic
 * Tests JavaScript logic via equivalent PHP implementation
 */
class PermissionsConversionTest extends TestCase
{
    /**
     * Calculate octal from permission bits
     */
    private function calculateOctal(array $perms): string
    {
        $owner = ($perms['owner_read'] ? 4 : 0) + ($perms['owner_write'] ? 2 : 0) + ($perms['owner_execute'] ? 1 : 0);
        $group = ($perms['group_read'] ? 4 : 0) + ($perms['group_write'] ? 2 : 0) + ($perms['group_execute'] ? 1 : 0);
        $others = ($perms['others_read'] ? 4 : 0) + ($perms['others_write'] ? 2 : 0) + ($perms['others_execute'] ? 1 : 0);
        
        return '0' . $owner . $group . $others;
    }
    
    /**
     * Parse octal to permission bits
     */
    private function parseOctal(string $octal): array
    {
        $digits = substr($octal, 1);
        $owner = (int)$digits[0];
        $group = (int)$digits[1];
        $others = (int)$digits[2];
        
        return [
            'owner_read' => ($owner & 4) !== 0,
            'owner_write' => ($owner & 2) !== 0,
            'owner_execute' => ($owner & 1) !== 0,
            'group_read' => ($group & 4) !== 0,
            'group_write' => ($group & 2) !== 0,
            'group_execute' => ($group & 1) !== 0,
            'others_read' => ($others & 4) !== 0,
            'others_write' => ($others & 2) !== 0,
            'others_execute' => ($others & 1) !== 0,
        ];
    }
    
    public function testStandardFilePermissions0644()
    {
        $perms = [
            'owner_read' => true, 'owner_write' => true, 'owner_execute' => false,
            'group_read' => true, 'group_write' => false, 'group_execute' => false,
            'others_read' => true, 'others_write' => false, 'others_execute' => false,
        ];
        
        $this->assertEquals('0644', $this->calculateOctal($perms));
    }
    
    public function testGroupWritableFilePermissions0664()
    {
        $perms = [
            'owner_read' => true, 'owner_write' => true, 'owner_execute' => false,
            'group_read' => true, 'group_write' => true, 'group_execute' => false,
            'others_read' => true, 'others_write' => false, 'others_execute' => false,
        ];
        
        $this->assertEquals('0664', $this->calculateOctal($perms));
    }
    
    public function testStandardDirectoryPermissions0755()
    {
        $perms = [
            'owner_read' => true, 'owner_write' => true, 'owner_execute' => true,
            'group_read' => true, 'group_write' => false, 'group_execute' => true,
            'others_read' => true, 'others_write' => false, 'others_execute' => true,
        ];
        
        $this->assertEquals('0755', $this->calculateOctal($perms));
    }
    
    public function testGroupWritableDirectoryPermissions0775()
    {
        $perms = [
            'owner_read' => true, 'owner_write' => true, 'owner_execute' => true,
            'group_read' => true, 'group_write' => true, 'group_execute' => true,
            'others_read' => true, 'others_write' => false, 'others_execute' => true,
        ];
        
        $this->assertEquals('0775', $this->calculateOctal($perms));
    }
    
    public function testOwnerOnlyPermissions0600()
    {
        $perms = [
            'owner_read' => true, 'owner_write' => true, 'owner_execute' => false,
            'group_read' => false, 'group_write' => false, 'group_execute' => false,
            'others_read' => false, 'others_write' => false, 'others_execute' => false,
        ];
        
        $this->assertEquals('0600', $this->calculateOctal($perms));
    }
    
    public function testAllWritablePermissions0777()
    {
        $perms = [
            'owner_read' => true, 'owner_write' => true, 'owner_execute' => true,
            'group_read' => true, 'group_write' => true, 'group_execute' => true,
            'others_read' => true, 'others_write' => true, 'others_execute' => true,
        ];
        
        $this->assertEquals('0777', $this->calculateOctal($perms));
    }
    
    public function testNoPermissions0000()
    {
        $perms = [
            'owner_read' => false, 'owner_write' => false, 'owner_execute' => false,
            'group_read' => false, 'group_write' => false, 'group_execute' => false,
            'others_read' => false, 'others_write' => false, 'others_execute' => false,
        ];
        
        $this->assertEquals('0000', $this->calculateOctal($perms));
    }
    
    public function testParseOctal0644()
    {
        $perms = $this->parseOctal('0644');
        
        $this->assertTrue($perms['owner_read']);
        $this->assertTrue($perms['owner_write']);
        $this->assertFalse($perms['owner_execute']);
        
        $this->assertTrue($perms['group_read']);
        $this->assertFalse($perms['group_write']);
        $this->assertFalse($perms['group_execute']);
        
        $this->assertTrue($perms['others_read']);
        $this->assertFalse($perms['others_write']);
        $this->assertFalse($perms['others_execute']);
    }
    
    public function testParseOctal0755()
    {
        $perms = $this->parseOctal('0755');
        
        $this->assertTrue($perms['owner_read']);
        $this->assertTrue($perms['owner_write']);
        $this->assertTrue($perms['owner_execute']);
        
        $this->assertTrue($perms['group_read']);
        $this->assertFalse($perms['group_write']);
        $this->assertTrue($perms['group_execute']);
        
        $this->assertTrue($perms['others_read']);
        $this->assertFalse($perms['others_write']);
        $this->assertTrue($perms['others_execute']);
    }
    
    public function testRoundTripConversion()
    {
        $testCases = ['0644', '0664', '0666', '0600', '0755', '0775', '0777', '0700', '0000'];
        
        foreach ($testCases as $octal) {
            $perms = $this->parseOctal($octal);
            $result = $this->calculateOctal($perms);
            $this->assertEquals($octal, $result, "Round-trip failed for $octal");
        }
    }
}
