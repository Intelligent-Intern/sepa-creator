<?php
declare(strict_types=1);
namespace IntelligentIntern\SepaCreator;
/** A collection amount is always an exact positive decimal string. */
final readonly class DirectDebit
{
    public string $endToEndId;
    public int $amountCents;
    public ?string $remittance;
    public ?string $creditorReference;
    public function __construct(string $endToEndId,public Party $debtor,mixed $amount,public Mandate $mandate,?string $remittance=null,?string $creditorReference=null,public ?TransactionOptions $options=null) {
        $common=new CreditTransfer($endToEndId,$debtor,$amount,$remittance,$creditorReference,$options);
        $this->endToEndId=$common->endToEndId;$this->amountCents=$common->amountCents;
        $this->remittance=$common->remittance;$this->creditorReference=$common->creditorReference;
    }
}
