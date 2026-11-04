<?php
declare(strict_types=1);
namespace IntelligentIntern\SepaCreator;
use IntelligentIntern\SepaCreator\Internal\Check;
final readonly class TransactionOptions
{
    public ?string $instructionId;
    public function __construct(?string $instructionId=null, public ?string $purposeCode=null, public ?string $categoryPurposeCode=null, public ?string $uetr=null)
    {
        $this->instructionId=$instructionId===null?null:Check::identifier($instructionId,'instructionId');
        foreach ([$purposeCode,$categoryPurposeCode] as $code) if ($code!==null&&!preg_match('/^[A-Z0-9]{4}$/D',$code)) throw new \InvalidArgumentException('Purpose codes must contain four uppercase letters/digits');
        if ($uetr!==null&&!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D',$uetr)) throw new \InvalidArgumentException('UETR must be a lowercase UUIDv4');
    }
}
