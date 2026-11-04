<?php
declare(strict_types=1);
namespace IntelligentIntern\SepaCreator;
use IntelligentIntern\SepaCreator\Internal\Check;

final readonly class Party
{
    public string $name;
    public Iban $iban;
    public ?string $bic;
    public function __construct(string $name, string|Iban $iban, ?string $bic = null, public ?StructuredAddress $address = null)
    {
        $this->name = Check::text($name, 70, 'party.name');
        $this->iban = is_string($iban) ? new Iban($iban) : $iban;
        if ($bic !== null) {
            $bic = strtoupper($bic);
            if (!preg_match('/^[A-Z]{4}[A-Z]{2}[A-Z0-9]{2}(?:[A-Z0-9]{3})?$/D', $bic)) {
                throw new \InvalidArgumentException('bic: expected an 8- or 11-character BIC');
            }
        }
        $this->bic = $bic;
    }
}
