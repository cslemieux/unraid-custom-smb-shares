<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../source/usr/local/emhttp/plugins/custom.smb.shares/include/lib.php';

/**
 * Test sanitizeShareData() function
 */
class SanitizationTest extends TestCase
{
    public function testTrimsWhitespace()
    {
        $input = [
            'name' => '  TestShare  ',
            'path' => '  /mnt/user/test  ',
            'comment' => '  Test comment  '
        ];

        $result = sanitizeShareData($input);

        $this->assertEquals('TestShare', $result['name']);
        $this->assertEquals('/mnt/user/test', $result['path']);
        $this->assertEquals('Test comment', $result['comment']);
    }

    public function testFiltersEmptyStrings()
    {
        $input = [
            'name' => 'TestShare',
            'path' => '/mnt/user/test',
            'comment' => '',
            'empty_field' => '   '
        ];

        $result = sanitizeShareData($input);

        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('path', $result);
        $this->assertArrayNotHasKey('comment', $result);
        $this->assertArrayNotHasKey('empty_field', $result);
    }

    public function testPreservesValidData()
    {
        $input = [
            'name' => 'ValidShare',
            'path' => '/mnt/user/valid',
            'browseable' => 'yes',
            'read_only' => 'no'
        ];

        $result = sanitizeShareData($input);

        $this->assertEquals($input, $result);
    }
}
