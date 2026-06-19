<?php
function base32_decode_custom(string $b32): string {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $b32 = strtoupper(preg_replace('/[^A-Z2-7]/i', '', $b32));
    $bits = '';
    for ($i = 0; $i < strlen($b32); $i++) {
        $val = strpos($alphabet, $b32[$i]);
        if ($val === false) continue;
        $bits .= str_pad(decbin($val), 5, '0', STR_PAD_LEFT);
    }
    $out = '';
    for ($i = 0; $i + 8 <= strlen($bits); $i += 8) $out .= chr(bindec(substr($bits, $i, 8)));
    return $out;
}
function hotp(string $secret, int $counter, int $digits=6): string {
    $binCounter = pack('N*',0) . pack('N*',$counter);
    $hash = hash_hmac('sha1', $binCounter, $secret, true);
    $offset = ord(substr($hash,-1)) & 0x0F;
    $truncated = ((ord($hash[$offset]) & 0x7F) << 24)
               | ((ord($hash[$offset+1]) & 0xFF) << 16)
               | ((ord($hash[$offset+2]) & 0xFF) << 8)
               | (ord($hash[$offset+3]) & 0xFF);
    $code = $truncated % (10 ** $digits);
    return str_pad((string)$code, $digits, '0', STR_PAD_LEFT);
}
function verify_totp(string $secretB32, string $code, int $window=1, int $period=30): bool {
    $secret = base32_decode_custom($secretB32);
    if ($secret === '' || !preg_match('/^\d{6}$/', $code)) return false;
    $slice = (int) floor(time() / $period);
    for ($i=-$window; $i<=$window; $i++) {
        if (hash_equals(hotp($secret, $slice + $i, 6), $code)) return true;
    }
    return false;
}
