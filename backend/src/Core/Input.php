<?php
declare(strict_types=1);
namespace App\Core;
final class Input
{
    public static function body(int $limit=65536): array
    {
        if (!str_starts_with(strtolower($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json')) throw new HttpException(415,'JSON_REQUIRED','Envie application/json.');
        $raw=file_get_contents('php://input', length: $limit+1);
        if (strlen($raw)>$limit) throw new HttpException(413,'PAYLOAD_TOO_LARGE','Limite do corpo da requisi??o excedido.');
        try { $data=json_decode($raw,true,32,JSON_THROW_ON_ERROR); } catch (\JsonException) { self::fail('JSON inválido'); }
        if (!is_array($data) || array_is_list($data)) self::fail('Objeto JSON obrigatório');
        return $data;
    }
    public static function fail(string $field): never { throw new HttpException(422,'VALIDATION_ERROR','Valor inválido: '.$field.'.'); }
    public static function text(array $data,string $field,int $max=4000,bool $optional=false): string
    {
        $v=$data[$field]??($optional?'':null);
        if (!is_string($v) || (!$optional && trim($v)==='') || mb_strlen($v)>$max) self::fail($field);
        return trim($v);
    }
    public static function id(array $data,string $field): int
    {
        $v=$data[$field]??null;
        if (!is_int($v) || $v<1) self::fail($field);
        return $v;
    }
    public static function choice(array $data,string $field,array $values): string
    {
        $v=$data[$field]??null; if (!in_array($v,$values,true)) self::fail($field); return $v;
    }
    public static function date(array $data,string $field): string
    {
        $v=self::text($data,$field,10); $date=\DateTimeImmutable::createFromFormat('!Y-m-d',$v);
        if (!$date || $date->format('Y-m-d')!==$v) self::fail($field); return $v;
    }
    public static function timestamp(array $data,string $field): string
    {
        $v=self::text($data,$field,19); $date=\DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$v);
        if (!$date || $date->format('Y-m-d H:i:s')!==$v || $v>date('Y-m-d H:i:s')) self::fail($field); return $v;
    }
    public static function number(mixed $v,string $field): float
    {
        if ((!is_float($v) && !is_int($v)) || !is_finite((float)$v) || abs($v)>1.0e12) self::fail($field); return (float)$v;
    }
    public static function password(array $data): string
    {
        $value=$data['password']??null;
        if(!is_string($value) || strlen($value)<12 || strlen($value)>72) self::fail('senha de 12 a 72 bytes');
        return $value;
    }
}
