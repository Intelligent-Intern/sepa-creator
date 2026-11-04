<?php
declare(strict_types=1);
namespace IntelligentIntern\SepaCreator;
use IntelligentIntern\SepaCreator\Internal\Check;
final readonly class Mandate
{
    public string $id;
    public string $signedOn;
    public ?string $originalMandateId;
    public ?Iban $originalDebtorIban;
    public ?string $finalCollectionDate;
    public function __construct(string $id,string $signedOn,?string $originalMandateId=null,string|Iban|null $originalDebtorIban=null,public bool $amendedDebtorAccount=false,?string $finalCollectionDate=null) {
        $this->id=Check::identifier($id,'mandate.id'); $this->signedOn=Check::date($signedOn);
        $this->originalMandateId=$originalMandateId===null?null:Check::identifier($originalMandateId,'originalMandateId');
        $this->originalDebtorIban=is_string($originalDebtorIban)?new Iban($originalDebtorIban):$originalDebtorIban;
        $this->finalCollectionDate=$finalCollectionDate===null?null:Check::date($finalCollectionDate);
        if ($this->originalMandateId===$this->id) throw new \InvalidArgumentException('Original and current mandate IDs must differ');
        if ($this->originalDebtorIban!==null&&$amendedDebtorAccount) throw new \InvalidArgumentException('Choose original debtor IBAN or SMNDA amendment');
        if ($this->finalCollectionDate!==null&&$this->finalCollectionDate<$this->signedOn) throw new \InvalidArgumentException('Final collection precedes mandate signature');
    }
}
