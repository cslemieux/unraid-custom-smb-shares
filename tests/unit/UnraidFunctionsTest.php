<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Unraid helper function emulation
 *
 * Tests the functions in tests/harness/UnraidFunctions.php
 */
class UnraidFunctionsTest extends TestCase
{
    protected function setUp(): void
    {
        // Load the functions if not already loaded
        require_once dirname(__DIR__) . '/harness/UnraidFunctions.php';
        
        // Reset global display settings to defaults
        $GLOBALS['display'] = [
            'scale'    => -1,
            'number'   => '.,',
            'unit'     => 'C',
            'date'     => '%c',
            'time'     => '%R',
            'critical' => 90,
            'warning'  => 70,
            'raw'      => false,
        ];
        
        $GLOBALS['language'] = [
            'prefix_SI'  => 'K M G T P E Z Y',
            'prefix_IEC' => 'Ki Mi Gi Ti Pi Ei Zi Yi',
        ];
    }

    // ========================================================================
    // TRANSLATION FUNCTIONS
    // ========================================================================

    public function testTranslationFunctionReturnsInput(): void
    {
        $this->assertEquals('Hello World', _('Hello World'));
        $this->assertEquals('Test String', _('Test String'));
    }

    public function testVarWithArrayKey(): void
    {
        $arr = ['key' => 'value', 'num' => 42];
        $this->assertEquals('value', _var($arr, 'key'));
        $this->assertEquals(42, _var($arr, 'num'));
    }

    public function testVarWithMissingKey(): void
    {
        $arr = ['key' => 'value'];
        $this->assertEquals('', _var($arr, 'missing'));
        $this->assertEquals('default', _var($arr, 'missing', 'default'));
    }

    public function testVarWithNullKey(): void
    {
        $value = 'test';
        $this->assertEquals('test', _var($value));
        
        $null = null;
        $this->assertEquals('default', _var($null, null, 'default'));
    }

    // ========================================================================
    // DISPLAY HELPERS - my_scale()
    // ========================================================================

    public function testMyScaleBytes(): void
    {
        $unit = '';
        $result = my_scale(500, $unit);
        $this->assertEquals('500', $result);
        $this->assertEquals('B', $unit);
    }

    public function testMyScaleKilobytes(): void
    {
        $unit = '';
        $result = my_scale(1500, $unit);
        $this->assertStringContainsString('1.5', $result);
        $this->assertEquals('KB', $unit);
    }

    public function testMyScaleMegabytes(): void
    {
        $unit = '';
        $result = my_scale(1500000, $unit);
        $this->assertStringContainsString('1.5', $result);
        $this->assertEquals('MB', $unit);
    }

    public function testMyScaleGigabytes(): void
    {
        $unit = '';
        $result = my_scale(1500000000, $unit);
        $this->assertStringContainsString('1.5', $result);
        $this->assertEquals('GB', $unit);
    }

    public function testMyScaleTerabytes(): void
    {
        $unit = '';
        $result = my_scale(1500000000000, $unit);
        $this->assertStringContainsString('1.5', $result);
        $this->assertEquals('TB', $unit);
    }

    public function testMyScaleWithDecimals(): void
    {
        $unit = '';
        $result = my_scale(1234567890, $unit, 2);
        $this->assertEquals('1.23', $result);
        $this->assertEquals('GB', $unit);
    }

    public function testMyScaleWithIECUnits(): void
    {
        $unit = '';
        // 1024 bytes = 1 KiB
        $result = my_scale(1024, $unit, null, null, 1024);
        $this->assertEquals('1', $result);
        $this->assertEquals('KiB', $unit);
    }

    public function testMyScaleZero(): void
    {
        $unit = '';
        $result = my_scale(0, $unit);
        $this->assertEquals('0', $result);
        $this->assertEquals('B', $unit);
    }

    // ========================================================================
    // DISPLAY HELPERS - my_number()
    // ========================================================================

    public function testMyNumberSmall(): void
    {
        // Numbers under 10000 don't get thousands separator
        $this->assertEquals('1234', my_number(1234));
        $this->assertEquals('9999', my_number(9999));
    }

    public function testMyNumberLarge(): void
    {
        // Numbers 10000+ get thousands separator
        $this->assertEquals('10,000', my_number(10000));
        $this->assertEquals('1,234,567', my_number(1234567));
    }

    // ========================================================================
    // DISPLAY HELPERS - my_temp()
    // ========================================================================

    public function testMyTempCelsius(): void
    {
        $GLOBALS['display']['unit'] = 'C';
        $result = my_temp(25);
        $this->assertStringContainsString('25', $result);
        $this->assertStringContainsString('C', $result);
    }

    public function testMyTempFahrenheit(): void
    {
        $GLOBALS['display']['unit'] = 'F';
        $result = my_temp(25);
        // 25°C = 77°F
        $this->assertStringContainsString('77', $result);
        $this->assertStringContainsString('F', $result);
    }

    public function testMyTempNonNumeric(): void
    {
        $this->assertEquals('N/A', my_temp('N/A'));
        $this->assertEquals('*', my_temp('*'));
    }

    // ========================================================================
    // DISPLAY HELPERS - my_disk()
    // ========================================================================

    public function testMyDiskFormatted(): void
    {
        $GLOBALS['display']['raw'] = false;
        $this->assertEquals('Disk 1', my_disk('disk1'));
        $this->assertEquals('Disk 10', my_disk('disk10'));
        $this->assertEquals('Cache', my_disk('cache'));
    }

    public function testMyDiskRaw(): void
    {
        $GLOBALS['display']['raw'] = true;
        $this->assertEquals('disk1', my_disk('disk1'));
        $this->assertEquals('disk10', my_disk('disk10'));
    }

    public function testMyDiskForceRaw(): void
    {
        $GLOBALS['display']['raw'] = false;
        $this->assertEquals('disk1', my_disk('disk1', true));
    }

    // ========================================================================
    // TEMPERATURE CONVERSION
    // ========================================================================

    public function testCelsiusConversion(): void
    {
        $this->assertEquals(0, celsius(32));   // 32°F = 0°C
        $this->assertEquals(100, celsius(212)); // 212°F = 100°C
        $this->assertEquals(25, celsius(77));   // 77°F ≈ 25°C
    }

    public function testFahrenheitConversion(): void
    {
        $this->assertEquals(32, fahrenheit(0));   // 0°C = 32°F
        $this->assertEquals(212, fahrenheit(100)); // 100°C = 212°F
        $this->assertEquals(77, fahrenheit(25));   // 25°C = 77°F
    }

    // ========================================================================
    // FORM HELPERS - mk_option()
    // ========================================================================

    public function testMkOptionSelected(): void
    {
        $result = mk_option('value1', 'value1', 'Label 1');
        $this->assertStringContainsString('value1', $result);
        $this->assertStringContainsString('selected', $result);
        $this->assertStringContainsString('Label 1', $result);
        $this->assertStringContainsString('<option', $result);
    }

    public function testMkOptionNotSelected(): void
    {
        $result = mk_option('value1', 'value2', 'Label 2');
        $this->assertStringContainsString('value2', $result);
        $this->assertStringNotContainsString('selected', $result);
    }

    public function testMkOptionWithExtra(): void
    {
        $result = mk_option('', 'disabled', 'Disabled Option', 'disabled');
        $this->assertStringContainsString('disabled', $result);
    }

    // ========================================================================
    // CONFIGURATION MANAGEMENT - my_parse_ini_string()
    // ========================================================================

    public function testMyParseIniString(): void
    {
        $ini = "KEY1=value1\nKEY2=value2";
        $result = my_parse_ini_string($ini);
        
        $this->assertIsArray($result);
        $this->assertEquals('value1', $result['KEY1']);
        $this->assertEquals('value2', $result['KEY2']);
    }

    public function testMyParseIniStringWithComments(): void
    {
        $ini = "# This is a comment\nKEY=value\n# Another comment";
        $result = my_parse_ini_string($ini);
        
        $this->assertIsArray($result);
        $this->assertEquals('value', $result['KEY']);
        $this->assertArrayNotHasKey('#', $result);
    }

    public function testMyParseIniStringWithHtmlTags(): void
    {
        $ini = "KEY=<b>value</b>";
        $result = my_parse_ini_string($ini);
        
        $this->assertIsArray($result);
        // HTML tags should be stripped
        $this->assertEquals('value', $result['KEY']);
    }

    // ========================================================================
    // UTILITY FUNCTIONS - compress()
    // ========================================================================

    public function testCompressAndDecompress(): void
    {
        $original = 'This is a test string that should be compressed and decompressed.';
        
        $compressed = compress($original);
        $this->assertNotEquals($original, $compressed);
        
        $decompressed = compress($compressed, true);
        $this->assertEquals($original, $decompressed);
    }

    // ========================================================================
    // UTILITY FUNCTIONS - my_explode()
    // ========================================================================

    public function testMyExplode(): void
    {
        $result = my_explode(',', 'a,b,c');
        $this->assertEquals(['a', 'b', 'c'], $result);
    }

    public function testMyExplodeWithLimit(): void
    {
        $result = my_explode(',', 'a,b', 3, 'default');
        $this->assertCount(3, $result);
        $this->assertEquals('a', $result[0]);
        $this->assertEquals('b', $result[1]);
        $this->assertEquals('default', $result[2]);
    }

    // ========================================================================
    // UTILITY FUNCTIONS - my_preg_split()
    // ========================================================================

    public function testMyPregSplit(): void
    {
        $result = my_preg_split('/\s+/', 'hello   world  test');
        $this->assertEquals(['hello', 'world', 'test'], $result);
    }

    // ========================================================================
    // CORE RENDERING - parse_text()
    // ========================================================================

    public function testParseTextTranslationMarkers(): void
    {
        $input = 'Hello _(World)_!';
        $result = parse_text($input);
        $this->assertEquals('Hello World!', $result);
    }

    public function testParseTextMultipleMarkers(): void
    {
        $input = '_(First)_ and _(Second)_';
        $result = parse_text($input);
        $this->assertEquals('First and Second', $result);
    }

    public function testParseTextNoMarkers(): void
    {
        $input = 'Plain text without markers';
        $result = parse_text($input);
        $this->assertEquals('Plain text without markers', $result);
    }

    // ========================================================================
    // FILE OPERATIONS - file_put_contents_atomic()
    // ========================================================================

    public function testFilePutContentsAtomic(): void
    {
        $tempFile = sys_get_temp_dir() . '/test_atomic_' . uniqid() . '.txt';
        $content = 'Test content for atomic write';
        
        try {
            $result = file_put_contents_atomic($tempFile, $content);
            
            $this->assertEquals(strlen($content), $result);
            $this->assertFileExists($tempFile);
            $this->assertEquals($content, file_get_contents($tempFile));
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }
}
