<?php
declare(strict_types=1);
namespace App\Services;
final class Crypto
{
    private static function key(): string
    {
        $key=base64_decode($_ENV['APP_KEY']??'',true);
        if ($key===false || strlen($key)!==32) throw new \RuntimeException('APP_KEY must contain 32 base64-encoded random bytes.');
        return $key;
    }
    public static function encrypt(string $value): string
    {
        $iv=random_bytes(12); $tag='';
        $encrypted=openssl_encrypt($value,'aes-256-gcm',self::key(),OPENSSL_RAW_DATA,$iv,$tag);
        if ($encrypted===false) throw new \RuntimeException('Encryption failed');
        return base64_encode($iv.$tag.$encrypted);
    }
    public static function decrypt(string $value): string
    {
        $raw=base64_decode($value,true);
        if ($raw===false || strlen($raw)<28) throw new \RuntimeException('Invalid ciphertext');
        $plain=openssl_decrypt(substr($raw,28),'aes-256-gcm',self::key(),OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16));
        if ($plain===false) throw new \RuntimeException('Decryption failed'); return $plain;
    }
}
