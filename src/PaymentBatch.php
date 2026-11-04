<?php
declare(strict_types=1);
namespace IntelligentIntern\SepaCreator;
use IntelligentIntern\SepaCreator\Internal\Check;

/** One debtor account and execution date per file. Order and identifiers are caller-controlled. */
final readonly class PaymentBatch
{
    public string $messageId;
    public string $paymentInfoId;
    public string $requestedExecutionDate;
    /** @var list<CreditTransfer> */
    public array $transfers;
    public string $initiatingParty;
    public int $totalCents;
    public function __construct(string $messageId, string $paymentInfoId, public Party $debtor,
        string $requestedExecutionDate, array $transfers, public \DateTimeImmutable $createdAt,
        public bool $batchBooking = true, ?string $initiatingParty = null)
    {
        if (PHP_INT_SIZE < 8) throw new \LogicException('A 64-bit PHP runtime is required');
        if ((int) $createdAt->format('Y') < 1 || (int) $createdAt->format('Y') > 9999 || abs($createdAt->getOffset()) > 50400) {
            throw new \InvalidArgumentException('createdAt: unsupported calendar year or timezone offset');
        }
        $this->messageId = Check::identifier($messageId, 'messageId');
        $this->paymentInfoId = Check::identifier($paymentInfoId, 'paymentInfoId');
        $this->requestedExecutionDate = Check::date($requestedExecutionDate);
        if ($requestedExecutionDate < $createdAt->format('Y-m-d')) throw new \InvalidArgumentException('Execution date precedes creation date');
        if (count($transfers) < 1 || count($transfers) > 10000) throw new \InvalidArgumentException('Batch must contain 1 to 10000 transfers');
        $total = 0; $ids = [];
        foreach ($transfers as $transfer) {
            if (!$transfer instanceof CreditTransfer) throw new \InvalidArgumentException('transfers: expected CreditTransfer objects');
            if (isset($ids[$transfer->endToEndId])) throw new \InvalidArgumentException('Duplicate endToEndId in batch');
            $ids[$transfer->endToEndId] = true;
            $total += $transfer->amountCents;
        }
        $this->totalCents = $total;
        $this->transfers = array_values($transfers);
        $this->initiatingParty = Check::text($initiatingParty ?? $debtor->name, 70, 'initiatingParty');
    }
}
