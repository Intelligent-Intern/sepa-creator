<?php
declare(strict_types=1);
namespace IntelligentIntern\SepaCreator\Internal;

final class Check
{
    public static function text(mixed $value, int $max, string $field): string
    {
        if (!is_string($value) || $value === '' || trim($value) !== $value
            || !preg_match('//u', $value) || preg_match('/[\p{Cc}\x{FFFE}\x{FFFF}]/u', $value)
            || preg_match_all('/./us', $value) > $max) {
            throw new \InvalidArgumentException($field . ': expected nonempty UTF-8 text without controls or surrounding whitespace, maximum ' . $max . ' characters');
        }
        return $value;
    }
    public static function identifier(mixed $value, string $field): string
    {
        $value = self::text($value, 35, $field);
        self::basic($value);
        if (str_starts_with($value, '/') || str_ends_with($value, '/') || str_contains($value, '//')) {
            throw new \InvalidArgumentException($field . ': leading/trailing or consecutive slashes are prohibited');
        }
        return $value;
    }
    public static function basic(string $value): void
    {
        if (!preg_match("~\\A[A-Za-z0-9/?:().,'+ \\-]*\\z~D", $value)) {
            throw new \InvalidArgumentException('Text contains characters outside the SEPA basic set; convert explicitly or select the bank-agreed UTF-8 policy');
        }
    }
    public static function cents(mixed $amount): int
    {
        // mixed is deliberate: non-strict PHP callers must not silently coerce floats.
        if (!is_string($amount) || !preg_match('/\A(0|[1-9][0-9]{0,8})(?:\.([0-9]{1,2}))?\z/D', $amount, $m)) {
            throw new \InvalidArgumentException('amount: use a decimal string with at most two fractional digits');
        }
        $cents = (int) $m[1] * 100 + (int) str_pad($m[2] ?? '', 2, '0');
        if ($cents < 1 || $cents > 99999999999) {
            throw new \InvalidArgumentException('amount: must be between 0.01 and 999999999.99 EUR');
        }
        return $cents;
    }
    public static function decimal(int $cents): string
    {
        return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
    public static function mod97(string $alphanumeric): int
    {
        $remainder = 0;
        foreach (str_split($alphanumeric) as $char) {
            $ord = ord($char); $n = $ord >= 48 && $ord <= 57 ? $ord - 48 : $ord - 55;
            $remainder = ($remainder * ($n < 10 ? 10 : 100) + $n) % 97;
        }
        return $remainder;
    }
    public static function date(mixed $date): string
    {
        if (!is_string($date) || !preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $date) || substr($date, 0, 4) === '0000') {
            throw new \InvalidArgumentException('requestedExecutionDate: expected YYYY-MM-DD');
        }
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$parsed || $parsed->format('Y-m-d') !== $date) throw new \InvalidArgumentException('requestedExecutionDate: invalid calendar date');
        return $date;
    }
    public static function reference(string $value): string
    {
        $value = strtoupper(str_replace(' ', '', $value));
        if (!preg_match('/^RF[0-9]{2}[A-Z0-9]{1,21}$/D', $value)
            || (int) substr($value, 2, 2) < 2 || (int) substr($value, 2, 2) > 98
            || self::mod97(substr($value, 4) . substr($value, 0, 4)) !== 1) {
            throw new \InvalidArgumentException('creditorReference: invalid ISO 11649 RF reference');
        }
        return $value;
    }
}
