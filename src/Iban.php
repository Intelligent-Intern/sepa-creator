<?php
declare(strict_types=1);
namespace IntelligentIntern\SepaCreator;
use IntelligentIntern\SepaCreator\Internal\Check;
use IntelligentIntern\SepaCreator\Internal\Resources;

final readonly class Iban
{
    public string $value;
    public function __construct(string $value)
    {
        $value = strtoupper(str_replace(' ', '', $value));
        if (!preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$/D', $value)
            || (int) substr($value, 2, 2) < 2 || (int) substr($value, 2, 2) > 98) {
            throw new \InvalidArgumentException('iban: invalid syntax');
        }
        $registry = json_decode(file_get_contents(Resources::path('iban-formats.json')), true, 32, JSON_THROW_ON_ERROR);
        $format = $registry[substr($value, 0, 2)] ?? null;
        if ($format === null || strlen($value) !== $format['length']
            || !preg_match('/\A' . $format['bbanPattern'] . '\z/D', substr($value, 4))) {
            throw new \InvalidArgumentException('iban: unknown country format, incorrect length or BBAN structure');
        }
        if (Check::mod97(substr($value, 4) . substr($value, 0, 4)) !== 1) {
            throw new \InvalidArgumentException('iban: checksum failed');
        }
        $this->value = $value;
    }
}
