<?php
declare(strict_types=1);
namespace IntelligentIntern\SepaCreator;
use IntelligentIntern\SepaCreator\Internal\Check;
/** One origin account, due date, and (for DD) scheme/sequence per group. */
final readonly class PaymentGroup
{
    public string $id;
    public string $dueDate;
    public array $transactions;
    public int $totalCents;
    public ?CreditorIdentifier $creditorIdentifier;
    public function __construct(string $id,public Party $origin,string $dueDate,array $transactions,public bool $batchBooking=true,
        string|CreditorIdentifier|null $creditorIdentifier=null,public string $scheme='CORE',public string $sequenceType='RCUR',
        public ?string $categoryPurposeCode=null,public ?string $instructionPriority=null)
    {
        $this->id=Check::identifier($id,'paymentInfoId');$this->dueDate=Check::date($dueDate);
        if (!array_is_list($transactions)||count($transactions)<1||count($transactions)>10000) throw new \InvalidArgumentException('Group requires a list of 1 to 10000 transactions');
        $this->creditorIdentifier=is_string($creditorIdentifier)?new CreditorIdentifier($creditorIdentifier):$creditorIdentifier;
        if (!in_array($scheme,['CORE','B2B','COR1'],true)||!in_array($sequenceType,['FRST','RCUR','OOFF','FNAL'],true)) throw new \InvalidArgumentException('Unsupported direct-debit scheme or sequence');
        if ($categoryPurposeCode!==null) new TransactionOptions(categoryPurposeCode:$categoryPurposeCode);
        if ($instructionPriority!==null&&!in_array($instructionPriority,['NORM','HIGH'],true)) throw new \InvalidArgumentException('Invalid instruction priority');
        $total=0;$ids=[];
        foreach ($transactions as $t) {
            if (!($t instanceof CreditTransfer||$t instanceof DirectDebit)||(($t instanceof DirectDebit)!==($this->creditorIdentifier!==null))) throw new \InvalidArgumentException('Group transaction family must match creditor identifier presence');
            if (isset($ids[$t->endToEndId])) throw new \InvalidArgumentException('Duplicate endToEndId');
            $ids[$t->endToEndId]=true;$total+=$t->amountCents;
        }
        $this->transactions=$transactions;$this->totalCents=$total;
    }
    public function isDirectDebit(): bool {return $this->creditorIdentifier!==null;}
}
