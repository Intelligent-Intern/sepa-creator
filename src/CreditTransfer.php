<?php
declare(strict_types=1);
namespace IntelligentIntern\SepaCreator;
use IntelligentIntern\SepaCreator\Internal\Check;

final readonly class CreditTransfer
{
    public string $endToEndId;
    public int $amountCents;
    public ?string $remittance;
    public ?string $creditorReference;
    public function __construct(string $endToEndId, public Party $creditor, mixed $amount, ?string $remittance = null, ?string $creditorReference = null)
    {
        $this->endToEndId = Check::identifier($endToEndId, 'endToEndId');
        $this->amountCents = Check::cents($amount);
        if ($remittance !== null && $creditorReference !== null) {
            throw new \InvalidArgumentException('Choose either remittance text or a structured RF creditor reference');
        }
        $this->remittance = $remittance === null ? null : Check::text($remittance, 140, 'remittance');
        $this->creditorReference = $creditorReference === null ? null : Check::reference($creditorReference);
    }
}
