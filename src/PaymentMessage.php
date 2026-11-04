<?php
declare(strict_types=1);
namespace IntelligentIntern\SepaCreator;
use IntelligentIntern\SepaCreator\Internal\Check;
/** Multiple groups of one payment family, with message-wide unique IDs. */
final readonly class PaymentMessage
{
    public string $messageId;
    public string $initiatingParty;
    public array $groups;
    public string $format;
    public int $transactionCount;
    public int $totalCents;
    public function __construct(string $messageId,string $initiatingParty,public \DateTimeImmutable $createdAt,array $groups,?string $format=null)
    {
        $this->messageId=Check::identifier($messageId,'messageId');$this->initiatingParty=Check::text($initiatingParty,70,'initiatingParty');
        if (PHP_INT_SIZE<8||(int)$createdAt->format('Y')<1||(int)$createdAt->format('Y')>9999||abs($createdAt->getOffset())>50400) throw new \InvalidArgumentException('Unsupported createdAt or runtime');
        if (!array_is_list($groups)||!$groups||count($groups)>10000) throw new \InvalidArgumentException('Message requires 1 to 10000 payment groups');
        $debit=$groups[0] instanceof PaymentGroup&&$groups[0]->isDirectDebit();
        $this->format=$format??($debit?'pain.008.001.08':SepaCreator::FORMAT);
        if (!in_array($this->format,$debit?Formats::directDebits():Formats::creditTransfers(),true)) throw new \InvalidArgumentException('Format and payment family do not match');
        $ids=[];$txIds=[];$uetrs=[];$count=0;$total=0;
        foreach ($groups as $g) {
            if (!$g instanceof PaymentGroup||$g->isDirectDebit()!==$debit) throw new \InvalidArgumentException('Cannot mix payment families');
            if (isset($ids[$g->id])) throw new \InvalidArgumentException('Duplicate payment group ID');$ids[$g->id]=true;
            if ($g->dueDate<$createdAt->format('Y-m-d')) throw new \InvalidArgumentException('Due date precedes message creation');
            foreach ($g->transactions as $t) {
                if (isset($txIds[$t->endToEndId])) throw new \InvalidArgumentException('Duplicate endToEndId across message');$txIds[$t->endToEndId]=true;
                $uetr=$t->options?->uetr;
                if ($uetr!==null) {
                    if (!Formats::supportsUetr($this->format)) throw new \InvalidArgumentException('Selected format cannot represent UETR');
                    if (isset($uetrs[$uetr])) throw new \InvalidArgumentException('Duplicate UETR');$uetrs[$uetr]=true;
                }
                if ($t instanceof DirectDebit&&($t->mandate->signedOn>$createdAt->format('Y-m-d')||$t->mandate->signedOn>$g->dueDate||($t->mandate->finalCollectionDate!==null&&$t->mandate->finalCollectionDate<$g->dueDate))) throw new \InvalidArgumentException('Mandate dates do not cover this collection');
                $count++;$total+=$t->amountCents;
            }
        }
        if ($count>10000) throw new \InvalidArgumentException('Message exceeds 10000 transactions');
        $this->groups=$groups;$this->transactionCount=$count;$this->totalCents=$total;
    }
    public static function fromBatch(PaymentBatch $batch,?string $format=null): self {
        return new self($batch->messageId,$batch->initiatingParty,$batch->createdAt,[new PaymentGroup($batch->paymentInfoId,$batch->debtor,$batch->requestedExecutionDate,$batch->transfers,$batch->batchBooking)],$format);
    }
}
