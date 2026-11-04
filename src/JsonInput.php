<?php
declare(strict_types=1);
namespace IntelligentIntern\SepaCreator;

/** Strict transport adapter: unknown keys and numeric JSON amounts are rejected. */
final class JsonInput
{
    public static function batch(array $input): PaymentBatch
    {
        self::keys($input, ['messageId','paymentInfoId','debtor','requestedExecutionDate','createdAt','transfers','batchBooking','initiatingParty','characterSet']);
        $created = self::string($input, 'createdAt');
        if (!preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}(?:Z|[+-](?:0[0-9]|1[0-3]):[0-5][0-9]|[+-]14:00)$/D', $created)) {
            throw new \InvalidArgumentException('createdAt: expected ISO date-time with explicit timezone and seconds');
        }
        try {$date = new \DateTimeImmutable($created);} catch (\Exception $e) {throw new \InvalidArgumentException('createdAt: invalid date-time', 0, $e);}
        $errors = \DateTimeImmutable::getLastErrors();
        if ($errors && ($errors['warning_count'] || $errors['error_count'])) throw new \InvalidArgumentException('createdAt: invalid calendar date-time');
        $transfers = $input['transfers'] ?? null;
        if (!is_array($transfers) || !array_is_list($transfers)) throw new \InvalidArgumentException('transfers: expected a JSON list');
        if (isset($input['batchBooking']) && !is_bool($input['batchBooking'])) throw new \InvalidArgumentException('batchBooking: expected boolean');
        return new PaymentBatch(self::string($input, 'messageId'), self::string($input, 'paymentInfoId'),
            self::party(self::object($input, 'debtor')), self::string($input, 'requestedExecutionDate'),
            array_map(static function ($t) {
                if (!is_array($t)) throw new \InvalidArgumentException('transfers: expected objects');
                self::keys($t, ['endToEndId','creditor','amount','remittance','creditorReference']);
                return new CreditTransfer(self::string($t, 'endToEndId'), self::party(self::object($t, 'creditor')),
                    self::string($t, 'amount'), self::optional($t, 'remittance'), self::optional($t, 'creditorReference'));
            }, $transfers), $date, $input['batchBooking'] ?? true, self::optional($input, 'initiatingParty'));
    }
    public static function characterSet(array $input): CharacterSet
    {
        $value = $input['characterSet'] ?? 'basic';
        if (!is_string($value) || CharacterSet::tryFrom($value) === null) throw new \InvalidArgumentException('characterSet: expected basic or utf8');
        return CharacterSet::from($value);
    }
    private static function party(array $value): Party
    {
        self::keys($value, ['name','iban','bic','address']); $address = null;
        if (isset($value['address'])) {
            $a = self::object($value, 'address'); self::keys($a, ['town','country','street','buildingNumber','postCode']);
            $address = new StructuredAddress(self::string($a, 'town'), self::string($a, 'country'),
                self::optional($a, 'street'), self::optional($a, 'buildingNumber'), self::optional($a, 'postCode'));
        }
        return new Party(self::string($value, 'name'), self::string($value, 'iban'), self::optional($value, 'bic'), $address);
    }
    private static function keys(array $value, array $allowed): void
    {
        if (array_diff(array_keys($value), $allowed)) throw new \InvalidArgumentException('Unknown input field');
    }
    private static function object(array $value, string $key): array
    {
        if (!isset($value[$key]) || !is_array($value[$key])) throw new \InvalidArgumentException($key . ': expected object');
        return $value[$key];
    }
    private static function string(array $value, string $key): string
    {
        if (!isset($value[$key]) || !is_string($value[$key])) throw new \InvalidArgumentException($key . ': expected string');
        return $value[$key];
    }
    private static function optional(array $value, string $key): ?string
    {
        return !isset($value[$key]) ? null : self::string($value, $key);
    }
}
