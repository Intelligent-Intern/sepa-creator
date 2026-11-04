<?php
declare(strict_types=1);
namespace IntelligentIntern\SepaCreator;
use IntelligentIntern\SepaCreator\Internal\Check;
/** SEPA creditor identifier checksum excludes the three-character business code. */
final readonly class CreditorIdentifier implements \Stringable
{
    public string $value;
    public function __construct(string $value) {
        $value=strtoupper(str_replace(' ','',$value));
        if (!preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{3}[A-Z0-9]{1,28}$/D',$value)
            || (int)substr($value,2,2)<2 || (int)substr($value,2,2)>98
            || Check::mod97(substr($value,7).substr($value,0,4))!==1) throw new \InvalidArgumentException('Invalid SEPA creditor identifier checksum or syntax');
        $this->value=$value;
    }
    public function __toString(): string {return $this->value;}
}
