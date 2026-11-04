<?php
declare(strict_types=1);
namespace IntelligentIntern\SepaCreator;
use IntelligentIntern\SepaCreator\Internal\Check;
use IntelligentIntern\SepaCreator\Internal\Resources;

final class SepaCreator
{
    public const FORMAT = 'pain.001.001.09';
    public const NAMESPACE = 'urn:iso:std:iso:20022:tech:xsd:' . self::FORMAT;
    // EPC409-09 v8.0 (2025-12-24), IBAN prefixes including Gibraltar.
    private const SEPA_PREFIXES = 'AD AL AT BE BG CH CY CZ DE DK EE ES FI FR GB GI GR HR HU IE IS IT LI LT LU LV MC MD ME MK MT NL NO PL PT RO RS SE SI SK SM VA';
    public function __construct(private readonly CharacterSet $characterSet = CharacterSet::SepaBasic) {}

    public function create(PaymentBatch $batch): PaymentFile
    {
        foreach ([$batch->debtor, ...array_map(static fn(CreditTransfer $t): Party => $t->creditor, $batch->transfers)] as $party) {
            if (!in_array(substr($party->iban->value, 0, 2), explode(' ', self::SEPA_PREFIXES), true)) {
                throw new \InvalidArgumentException('Account IBAN is outside the pinned SEPA geographical scope');
            }
        }
        $doc = new \DOMDocument('1.0', 'UTF-8'); $doc->formatOutput = true;
        $root = $doc->createElementNS(self::NAMESPACE, 'Document'); $doc->appendChild($root);
        $message = $this->node($root, 'CstmrCdtTrfInitn');
        $header = $this->node($message, 'GrpHdr');
        $this->node($header, 'MsgId', $batch->messageId);
        $this->node($header, 'CreDtTm', $batch->createdAt->format('Y-m-d\TH:i:sP'));
        $count = (string) count($batch->transfers); $sum = Check::decimal($batch->totalCents);
        $this->node($header, 'NbOfTxs', $count); $this->node($header, 'CtrlSum', $sum);
        $this->text($this->node($header, 'InitgPty'), 'Nm', $batch->initiatingParty);
        $payment = $this->node($message, 'PmtInf');
        $this->node($payment, 'PmtInfId', $batch->paymentInfoId); $this->node($payment, 'PmtMtd', 'TRF');
        $this->node($payment, 'BtchBookg', $batch->batchBooking ? 'true' : 'false');
        $this->node($payment, 'NbOfTxs', $count); $this->node($payment, 'CtrlSum', $sum);
        $this->node($this->node($this->node($payment, 'PmtTpInf'), 'SvcLvl'), 'Cd', 'SEPA');
        $this->node($this->node($payment, 'ReqdExctnDt'), 'Dt', $batch->requestedExecutionDate);
        $this->party($this->node($payment, 'Dbtr'), $batch->debtor);
        $this->node($this->node($this->node($payment, 'DbtrAcct'), 'Id'), 'IBAN', $batch->debtor->iban->value);
        $this->agent($this->node($payment, 'DbtrAgt'), $batch->debtor->bic);
        $this->node($payment, 'ChrgBr', 'SLEV');
        foreach ($batch->transfers as $transfer) {
            $tx = $this->node($payment, 'CdtTrfTxInf');
            $this->node($this->node($tx, 'PmtId'), 'EndToEndId', $transfer->endToEndId);
            $this->node($this->node($tx, 'Amt'), 'InstdAmt', Check::decimal($transfer->amountCents))->setAttribute('Ccy', 'EUR');
            if ($transfer->creditor->bic !== null) $this->agent($this->node($tx, 'CdtrAgt'), $transfer->creditor->bic);
            $this->party($this->node($tx, 'Cdtr'), $transfer->creditor);
            $this->node($this->node($this->node($tx, 'CdtrAcct'), 'Id'), 'IBAN', $transfer->creditor->iban->value);
            if ($transfer->remittance !== null) $this->text($this->node($tx, 'RmtInf'), 'Ustrd', $transfer->remittance);
            if ($transfer->creditorReference !== null) {
                $ref = $this->node($this->node($this->node($tx, 'RmtInf'), 'Strd'), 'CdtrRefInf');
                $type = $this->node($ref, 'Tp');
                $this->node($this->node($type, 'CdOrPrtry'), 'Cd', 'SCOR'); $this->node($type, 'Issr', 'ISO');
                $this->node($ref, 'Ref', $transfer->creditorReference);
            }
        }
        $schema = Resources::path('pain.001.001.09.xsd');
        $previous = libxml_use_internal_errors(true); libxml_clear_errors();
        try {
            if (!$doc->schemaValidate($schema)) {
                $errors = array_map(static fn($e) => trim($e->message), libxml_get_errors());
                throw new \RuntimeException('Generated document failed ISO schema validation: ' . implode('; ', $errors));
            }
        } finally {libxml_clear_errors(); libxml_use_internal_errors($previous);}
        return new PaymentFile($doc->saveXML(), $batch->messageId, (int) $count, $sum, hash_file('sha256', $schema));
    }
    private function node(\DOMElement $parent, string $name, ?string $value = null): \DOMElement
    {
        $node = $parent->ownerDocument->createElementNS(self::NAMESPACE, $name);
        if ($value !== null) $node->appendChild($parent->ownerDocument->createTextNode($value));
        $parent->appendChild($node); return $node;
    }
    private function text(\DOMElement $parent, string $name, string $value): \DOMElement
    {
        $this->characterSet->check($value); return $this->node($parent, $name, $value);
    }
    private function party(\DOMElement $node, Party $party): void
    {
        $this->text($node, 'Nm', $party->name);
        if ($party->address === null) return;
        $address = $this->node($node, 'PstlAdr'); $a = $party->address;
        foreach (['StrtNm' => $a->street, 'BldgNb' => $a->buildingNumber, 'PstCd' => $a->postCode,
            'TwnNm' => $a->town, 'Ctry' => $a->country] as $name => $value) {
            if ($value !== null) $this->text($address, $name, $value);
        }
    }
    private function agent(\DOMElement $node, ?string $bic): void
    {
        $identification = $this->node($node, 'FinInstnId');
        if ($bic !== null) $this->node($identification, 'BICFI', $bic);
        else $this->node($this->node($identification, 'Othr'), 'Id', 'NOTPROVIDED');
    }
}
