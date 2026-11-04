<?php
declare(strict_types=1);
namespace IntelligentIntern\SepaCreator\Internal;
use IntelligentIntern\SepaCreator\{CharacterSet,Formats,PaymentMessage,PaymentFile,DocumentValidator,Party,DirectDebit,BankProfile};
use Digitick\Sepa\{GroupHeader,PaymentInformation};
use Digitick\Sepa\Util\Sanitizer;
use Digitick\Sepa\TransferFile\{TransferFileInterface,CustomerCreditTransferFile,CustomerDirectDebitTransferFile};
use Digitick\Sepa\TransferInformation\{CustomerCreditTransferInformation,CustomerDirectDebitTransferInformation};
use Digitick\Sepa\DomBuilder\{BaseDomBuilder,CustomerCreditTransferDomBuilder,CustomerDirectDebitTransferDomBuilder};

final class Engine
{
    public function __construct(private readonly CharacterSet $characterSet,private readonly BankProfile $profile=new BankProfile()) {}
    public function create(PaymentMessage $message): PaymentFile {
        $old=Sanitizer::getSanitizer();Sanitizer::disableSanitizer();
        try {
            $header=new class($message->messageId,$message->initiatingParty,$message->createdAt) extends GroupHeader {
                public function __construct(string $id,string $name,\DateTimeImmutable $created) {parent::__construct($id,$name);$this->creationDateTime=$created;$this->setCreationDateTimeFormat('Y-m-d\TH:i:sP');}
            };
            $debit=$message->groups[0]->isDirectDebit();
            $source=$debit?new CustomerDirectDebitTransferFile($header):new CustomerCreditTransferFile($header);
            foreach ($message->groups as $g) {
                $p=new PaymentInformation($g->id,$g->origin->iban->value,$g->origin->bic,$g->origin->name);
                $p->setDueDate(new \DateTimeImmutable($g->dueDate));$p->setBatchBooking($g->batchBooking);
                if ($g->categoryPurposeCode!==null) $p->setCategoryPurposeCode($g->categoryPurposeCode);
                if ($g->instructionPriority!==null) $p->setInstructionPriority($g->instructionPriority);
                if ($debit) {$p->setCreditorId($g->creditorIdentifier->value);$p->setLocalInstrumentCode($g->scheme);$p->setSequenceType($g->sequenceType);}
                foreach ($g->transactions as $t) {
                    $party=$debit?$t->debtor:$t->creditor;
                    $tx=$debit?new CustomerDirectDebitTransferInformation($t->amountCents,$party->iban->value,$party->name,$t->endToEndId):new CustomerCreditTransferInformation($t->amountCents,$party->iban->value,$party->name,$t->endToEndId);
                    $tx->setUUID($t->options?->uetr);
                    if ($party->bic!==null) $tx->setBic($party->bic);
                    if ($t->remittance!==null) $tx->setRemittanceInformation($t->remittance);
                    if ($t->creditorReference!==null) {$tx->setCreditorReference($t->creditorReference);$tx->setCreditorReferenceType('SCOR');}
                    if ($t->options?->instructionId!==null) $tx->setInstructionId($t->options->instructionId);
                    if ($t->options?->purposeCode!==null) $tx->setPurposeCode($t->options->purposeCode);
                    if ($t->options?->categoryPurposeCode!==null) $tx->setCategoryPurposeCode($t->options->categoryPurposeCode);
                    if ($t instanceof DirectDebit) {
                        $tx->setMandateId($t->mandate->id);$tx->setMandateSignDate(new \DateTimeImmutable($t->mandate->signedOn));
                    }
                    $p->addTransfer($tx);
                }
                $source->addPaymentInformation($p);
            }
            $doc=$this->render($source,$message->format);$xp=Xml::xpath($doc);
            foreach ($message->groups as $i=>$g) {
                $p=$xp->query('/*/*/p:PmtInf')->item($i);
                $this->address($xp->query($debit?'p:Cdtr':'p:Dbtr',$p)->item(0),$g->origin,$message->format);
                if ($debit&&$g->categoryPurposeCode!==null) {$type=$xp->query('p:PmtTpInf',$p)->item(0);$this->node($this->node($type,'CtgyPurp'),'Cd',$g->categoryPurposeCode);}
                foreach ($g->transactions as $j=>$t) {
                    $tx=$xp->query($debit?'p:DrctDbtTxInf':'p:CdtTrfTxInf',$p)->item($j);
                    $this->address($xp->query($debit?'p:Dbtr':'p:Cdtr',$tx)->item(0),$debit?$t->debtor:$t->creditor,$message->format);
                    if ($t instanceof DirectDebit) {
                        $m=$xp->query('p:DrctDbtTx/p:MndtRltdInf',$tx)->item(0);$mandate=$t->mandate;
                        if ($mandate->originalMandateId!==null||$mandate->originalDebtorIban!==null||$mandate->amendedDebtorAccount) {
                            $this->node($m,'AmdmntInd','true');$details=$this->node($m,'AmdmntInfDtls');
                            if ($mandate->originalMandateId!==null) $this->node($details,'OrgnlMndtId',$mandate->originalMandateId);
                            if ($mandate->originalDebtorIban!==null||$mandate->amendedDebtorAccount) {
                                if ($mandate->amendedDebtorAccount && substr($message->format,9,3)!=='001') {
                                    if ($g->sequenceType!=='FRST') throw new \InvalidArgumentException('Legacy national SMNDA amendment requires FRST sequence');
                                    $this->node($this->node($this->node($this->node($details,'OrgnlDbtrAgt'),'FinInstnId'),'Othr'),'Id','SMNDA');
                                } else {
                                    $id=$this->node($this->node($details,'OrgnlDbtrAcct'),'Id');
                                    if ($mandate->originalDebtorIban!==null) $this->node($id,'IBAN',$mandate->originalDebtorIban->value);
                                    else $this->node($this->node($id,'Othr'),'Id','SMNDA');
                                }
                            }
                        }
                        if ($mandate->finalCollectionDate!==null) $this->node($m,'FnlColltnDt',$mandate->finalCollectionDate);
                        if ($t->options?->categoryPurposeCode!==null) {
                            $type=$doc->createElementNS($tx->namespaceURI,'PmtTpInf');$tx->insertBefore($type,$xp->query('p:InstdAmt',$tx)->item(0));
                            $this->node($this->node($type,'CtgyPurp'),'Cd',$t->options->categoryPurposeCode);
                        }
                        if ($t->options?->purposeCode!==null) {
                            $purpose=$doc->createElementNS($tx->namespaceURI,'Purp');$this->node($purpose,'Cd',$t->options->purposeCode);
                            $tx->insertBefore($purpose,$xp->query('p:RmtInf',$tx)->item(0));
                        }
                    }
                }
            }
            return $this->finish($doc,$message->format);
        } finally {Sanitizer::setSanitizer($old);}
    }
    public function native(TransferFileInterface $source,string $format): PaymentFile {return $this->finish($this->render($source,$format),$format);}
    private function render(TransferFileInterface $source,string $format): \DOMDocument {
        Formats::schema($format);$debit=in_array($format,Formats::directDebits(),true);
        if ((!$debit&&!in_array($format,Formats::creditTransfers(),true))||($debit&&!$source instanceof CustomerDirectDebitTransferFile)||(!$debit&&!$source instanceof CustomerCreditTransferFile)) throw new \InvalidArgumentException('Native source and payment format do not match');
        // Integer-only serialization also for control sums above the exact float range.
        $builder=$debit?new class($format,false) extends CustomerDirectDebitTransferDomBuilder {protected function intToCurrency(int $amount):string {return Check::decimal($amount);}}:new class($format,false) extends CustomerCreditTransferDomBuilder {protected function intToCurrency(int $amount):string {return Check::decimal($amount);}};
        $builder->setOmitGroupHeaderControlSum($this->profile->omitGroupHeaderControlSum);
        $builder->setOmitAgentElementIfBicMissing($this->profile->omitAgentIfBicMissing);
        $source->accept($builder);return Xml::parse($builder->asXml());
    }
    private function finish(\DOMDocument $doc,string $format): PaymentFile {
        $doc->formatOutput=true;$xml=$doc->saveXML();$report=(new DocumentValidator($this->characterSet))->validate($xml);$report->assertValid();$m=$report->metadata;
        return new PaymentFile($xml,$m['messageId'],$m['transactionCount'],$m['controlSum'],$m['schemaSha256'],$format,$m['paymentGroupCount']);
    }
    private function node(\DOMElement $parent,string $name,?string $value=null): \DOMElement {
        $n=$parent->ownerDocument->createElementNS($parent->namespaceURI,$name);if($value!==null)$n->appendChild($parent->ownerDocument->createTextNode($value));$parent->appendChild($n);return $n;
    }
    private function address(\DOMElement $party,Party $data,string $format):void {
        if ($data->address===null) return;
        if (substr($format,9,3)!=='001') throw new \InvalidArgumentException('Legacy national variants cannot represent StructuredAddress; use native address lines explicitly');
        $a=$data->address;$address=$party->ownerDocument->createElementNS($party->namespaceURI,'PstlAdr');
        foreach (['StrtNm'=>$a->street,'BldgNb'=>$a->buildingNumber,'PstCd'=>$a->postCode,'TwnNm'=>$a->town,'Ctry'=>$a->country] as $key=>$value) if ($value!==null)$this->node($address,$key,$value);
        $nm=$party->getElementsByTagNameNS($party->namespaceURI,'Nm')->item(0);$party->insertBefore($address,$nm->nextSibling);
    }
}
