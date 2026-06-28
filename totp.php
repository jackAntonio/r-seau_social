<?php
// Implémentation TOTP (RFC 6238) sans dépendance externe

class TOTP {
    private const CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    private const DIGITS = 6;
    private const PERIOD = 30;

    // Générer une clé secrète aléatoire base32
    public static function generate_secret(int $length = 16): string {
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= self::CHARS[random_int(0, 31)];
        }
        return $secret;
    }

    // Décoder base32 → bytes
    private static function base32_decode(string $b32): string {
        $b32    = strtoupper($b32);
        $map    = array_flip(str_split(self::CHARS));
        $bits   = '';
        foreach (str_split($b32) as $c) {
            if (!isset($map[$c])) continue;
            $bits .= str_pad(decbin($map[$c]), 5, '0', STR_PAD_LEFT);
        }
        $bytes = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) $bytes .= chr(bindec($byte));
        }
        return $bytes;
    }

    // Calculer le code TOTP pour un instant donné
    public static function get_code(string $secret, int $timestamp = 0): string {
        if (!$timestamp) $timestamp = time();
        $counter = pack('N*', 0) . pack('N*', intval($timestamp / self::PERIOD));
        $key     = self::base32_decode($secret);
        $hash    = hash_hmac('sha1', $counter, $key, true);
        $offset  = ord($hash[19]) & 0x0F;
        $code    = (
            ((ord($hash[$offset])     & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8)  |
            (ord($hash[$offset + 3])  & 0xFF)
        ) % (10 ** self::DIGITS);
        return str_pad((string)$code, self::DIGITS, '0', STR_PAD_LEFT);
    }

    // Vérifier un code (fenêtre ±1 période pour la tolérance d'horloge)
    public static function verify(string $secret, string $code, int $window = 1): bool {
        $now = time();
        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals(self::get_code($secret, $now + $i * self::PERIOD), $code)) {
                return true;
            }
        }
        return false;
    }

    // URL pour QR code (Google Authenticator, Authy, etc.)
    public static function otpauth_url(string $secret, string $label, string $issuer = 'ReseauSocial'): string {
        return 'otpauth://totp/' . rawurlencode($label)
            . '?secret=' . $secret
            . '&issuer=' . rawurlencode($issuer)
            . '&algorithm=SHA1&digits=6&period=30';
    }

    // URL QR code via API google charts (ne transmet pas le secret en clair en prod - préférer qrcode.js local)
    public static function qr_url(string $otp_url): string {
        return 'https://chart.googleapis.com/chart?chs=200x200&cht=qr&chl=' . rawurlencode($otp_url);
    }
}
