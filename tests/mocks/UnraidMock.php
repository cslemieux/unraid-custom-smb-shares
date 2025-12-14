<?php
class UnraidMock {
    public static function mockExec($command, &$output = null, &$return_var = null) {
        $output = [];
        $return_var = 0;
        
        if (strpos($command, 'testparm') !== false) {
            $output = ['Load smb config files from /etc/samba/smb.conf', 'Loaded services file OK.'];
        } elseif (strpos($command, 'smbcontrol') !== false) {
            $output = ['smbd reloaded'];
        }
        
        return implode("\n", $output);
    }
    
    public static function mockFileGetContents($filename) {
        if (strpos($filename, 'shares.json') !== false) {
            return json_encode([]);
        }
        return '';
    }
    
    public static function mockFilePutContents($filename, $data) {
        return strlen($data);
    }
}
