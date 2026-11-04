<?php
declare(strict_types=1);
namespace IntelligentIntern\SepaCreator;
use IntelligentIntern\SepaCreator\Internal\{Check,Xml};
/** ISO structural validation plus a strict, EUR-only SEPA application policy. */
final class DocumentValidator
{
    private const SEPA='AD AL AT BE BG CH CY CZ DE DK EE ES FI FR GB GI GR HR HU IE IS IT LI LT LU LV MC MD ME MK MT NL NO PL PT RO RS SE SI SK SM VA';
    public function __construct(private readonly CharacterSet $characterSet=CharacterSet::SepaBasic) {}
    public function validate(string $xml): ValidationReport {
        try {$doc=Xml::parse($xml);$format=Xml::format($doc);} catch (\InvalidArgumentException $e) {return new ValidationReport('invalid',null,[['code'=>'xml','path'=>'/','message'=>$e->getMessage()]]);}
        if (!in_array($format,[...Formats::creditTransfers(),...Formats::directDebits()],true)) return new ValidationReport('unsupported',$format,[['code'=>'format','path'=>'/','message'=>'Unsupported payment initiation format']]);
        $schema=Formats::schema($format);$errors=Xml::schemaErrors($doc,$schema);
        if ($errors) return new ValidationReport('invalid',$format,$errors);
        $xp=Xml::xpath($doc);$findings=[];
        $test=static function(string $code,\DOMNode $node,callable $fn) use (&$findings):void {
            try {$fn();} catch (\InvalidArgumentException $e) {$findings[]=['code'=>$code,'path'=>$node->getNodePath(),'message'=>$e->getMessage()];}
        };
        $require=static function(bool $condition,string $message):void {if (!$condition) throw new \InvalidArgumentException($message);};
        $str=static fn(string $path,?\DOMNode $node=null):string=>$xp->evaluate('string('.$path.')',$node);
        foreach ($xp->query('//p:IBAN') as $node) $test('iban',$node,static function() use($node,$require) {
            $iban=new Iban($node->textContent);$require($iban->value===$node->textContent,'IBAN must use canonical uppercase electronic form');
            $require(in_array(substr($iban->value,0,2),explode(' ',self::SEPA),true),'Account IBAN is outside the pinned SEPA geographical scope');
        });
        foreach ($xp->query('//p:BIC|//p:BICFI') as $node) $test('bic',$node,fn()=>$require((bool)preg_match('/^[A-Z]{4}[A-Z]{2}[A-Z0-9]{2}(?:[A-Z0-9]{3})?$/D',$node->textContent),'Invalid BIC syntax'));
        foreach ($xp->query('//p:MsgId|//p:PmtInfId|//p:EndToEndId|//p:InstrId|//p:MndtId|//p:OrgnlMndtId') as $node) $test('identifier',$node,fn()=>Check::identifier($node->textContent,$node->localName));
        foreach ($xp->query('//p:Nm|//p:Ustrd|//p:StrtNm|//p:BldgNb|//p:PstCd|//p:TwnNm|//p:AdrLine|//p:Flr') as $node) $test('text',$node,function() use($node) {
            Check::text($node->textContent,$node->localName==='Ustrd'?140:($node->localName==='BldgNb'||$node->localName==='PstCd'?16:($node->localName==='TwnNm'?35:70)),$node->localName);
            $this->characterSet->check($node->textContent);
        });
        foreach ($xp->query('//p:ChrgBr') as $node) $test('charge',$node,fn()=>$require($node->textContent==='SLEV','Every charge bearer must be SLEV'));
        foreach ($xp->query('//p:SvcLvl/p:Cd') as $node) $test('service',$node,fn()=>$require($node->textContent==='SEPA','Every service level must be SEPA'));
        foreach ($xp->query('//p:CdtrRefInf[p:Tp/p:CdOrPrtry/p:Cd="SCOR"]/p:Ref') as $node) $test('reference',$node,fn()=>(static function()use($node,$require){$require(Check::reference($node->textContent)===$node->textContent,'RF reference must use canonical electronic form');})());
        $seen=[];
        foreach (['PmtInfId','EndToEndId','UETR'] as $field) foreach ($xp->query('//p:'.$field) as $node) {
            $key=$field.':'.$node->textContent;$test('duplicate',$node,fn()=>$require(!isset($seen[$key]),'Duplicate '.$field));$seen[$key]=true;
        }
        $created=$str('/*/*/p:GrpHdr/p:CreDtTm');$createdDate=substr($created,0,10);
        $test('date',$doc->documentElement,function()use($created,$createdDate,$require) {
            Check::date($createdDate);$require((bool)preg_match('/(?:Z|[+-][0-9]{2}:[0-9]{2})$/D',$created),'Creation timestamp must include timezone');
            $parsed=new \DateTimeImmutable($created);$require(abs($parsed->getOffset())<=50400,'Timezone offset exceeds 14 hours');
        });
        $total=0;$count=0;$groups=$xp->query('/*/*/p:PmtInf');$debit=str_starts_with($format,'pain.008.');
        foreach ($groups as $group) {
            $originRole=$debit?'Cdtr':'Dbtr';
            $test('origin',$group,function()use($str,$originRole,$group,$require) {
                Check::text($str('p:'.$originRole.'/p:Nm',$group),70,'origin.name');
                $require($str('p:'.$originRole.'Acct/p:Id/p:IBAN',$group)!=='','Origin account must identify an IBAN');
            });
            $groupTotal=0;$txs=$xp->query($debit?'p:DrctDbtTxInf':'p:CdtTrfTxInf',$group);
            $test('family',$group,fn()=>$require($str('p:PmtMtd',$group)===($debit?'DD':'TRF'),'Wrong payment method'));
            $test('service',$group,fn()=>$require($str('p:PmtTpInf/p:SvcLvl/p:Cd',$group)==='SEPA','Service level must be SEPA'));
            $test('charge',$group,fn()=>$require($str('p:ChrgBr',$group)==='SLEV','Charge bearer must be SLEV'));
            $due=$str($debit?'p:ReqdColltnDt':(substr($format,9,3)==='001'&&(int)substr($format,-2)>=8?'p:ReqdExctnDt/p:Dt':'p:ReqdExctnDt'),$group);
            $test('date',$group,function()use($due,$createdDate,$require){Check::date($due);$require($due>=$createdDate,'Due date precedes creation');});
            if ($debit) {
                $ci=$str('p:CdtrSchmeId/p:Id/p:PrvtId/p:Othr/p:Id',$group);
                $test('creditor-identifier',$group,fn()=>new CreditorIdentifier($ci));
                $test('sequence',$group,fn()=>$require(in_array($str('p:PmtTpInf/p:SeqTp',$group),['FRST','RCUR','OOFF','FNAL'],true),'Missing or invalid sequence type'));
                $test('instrument',$group,fn()=>$require(in_array($str('p:PmtTpInf/p:LclInstrm/p:Cd',$group),['CORE','B2B','COR1'],true),'Missing or invalid DD instrument'));
            }
            foreach ($txs as $tx) {
                $partyRole=$debit?'Dbtr':'Cdtr';
                $test('counterparty',$tx,function()use($str,$partyRole,$tx,$require) {
                    Check::text($str('p:'.$partyRole.'/p:Nm',$tx),70,'counterparty.name');
                    $require($str('p:'.$partyRole.'Acct/p:Id/p:IBAN',$tx)!=='','Counterparty account must identify an IBAN');
                });
                $amt=$xp->query($debit?'p:InstdAmt':'p:Amt/p:InstdAmt',$tx)->item(0);
                if ($amt===null) {$test('amount',$tx,fn()=>$require(false,'Only instructed EUR amounts are supported'));continue;}
                $test('amount',$amt,function()use($amt,&$groupTotal,$require) {$require($amt->getAttribute('Ccy')==='EUR','Only EUR is permitted');$groupTotal+=Check::cents($amt->textContent);});
                if ($debit) {
                    $mandate=$xp->query('p:DrctDbtTx/p:MndtRltdInf',$tx)->item(0);
                    $test('mandate',$tx,function()use($mandate,$str,$createdDate,$due,$require) {
                        $require($mandate!==null,'Mandate details are required');Check::identifier($str('p:MndtId',$mandate),'mandateId');
                        $signed=Check::date($str('p:DtOfSgntr',$mandate));$require($signed<=$createdDate&&$signed<=$due,'Mandate signature is after creation or collection');
                        $final=$str('p:FnlColltnDt',$mandate);if($final!==''){$final=Check::date($final);$require($final>=$due,'Collection is after final mandate date');}
                        $changed=$str('p:AmdmntInd',$mandate);
                        $hasDetails=false;foreach($mandate->childNodes as $child)if($child->localName==='AmdmntInfDtls')$hasDetails=true;
                        if ($hasDetails) {
                            $details=null;foreach($mandate->childNodes as $child)if($child->localName==='AmdmntInfDtls')$details=$child;
                            $hasElement=false;foreach($details->childNodes as $child)if($child instanceof \DOMElement)$hasElement=true;
                            $require($hasElement,'Mandate amendment details must not be empty');
                            $originalId=$str('p:OrgnlMndtId',$details);if($originalId!=='')$require($originalId!==$str('p:MndtId',$mandate),'Original and current mandate IDs must differ');
                        }
                        $require(($changed==='true'||$changed==='1')===$hasDetails,'Amendment indicator and amendment details must agree');
                    });
                }
                $count++;
            }
            $test('count',$group,fn()=>$require($txs->length>0,'Empty payment group'));
            foreach (['NbOfTxs'=>(string)$txs->length,'CtrlSum'=>Check::decimal($groupTotal)] as $field=>$expected) {
                $nodes=$xp->query('p:'.$field,$group);if($nodes->length) $test('totals',$group,fn()=>$require($str('p:'.$field,$group)===$expected,'Payment group '.$field.' does not match actual transactions'));
            }
            $total+=$groupTotal;
        }
        $test('limit',$doc->documentElement,fn()=>$require($count>=1&&$count<=10000,'Message must contain 1 to 10000 transactions'));
        $header=$xp->query('/*/*/p:GrpHdr')->item(0);
        foreach (['NbOfTxs'=>(string)$count,'CtrlSum'=>Check::decimal($total)] as $field=>$expected) {
            $nodes=$xp->query('p:'.$field,$header);if($nodes->length) $test('totals',$header,fn()=>$require($str('p:'.$field,$header)===$expected,'Header '.$field.' does not match actual transactions'));
        }
        return new ValidationReport($findings?'invalid':'valid',$format,$findings,['messageId'=>$str('p:MsgId',$header),'transactionCount'=>$count,'controlSum'=>Check::decimal($total),'paymentGroupCount'=>$groups->length,'schemaSha256'=>hash_file('sha256',$schema),'sha256'=>hash('sha256',$xml)]);
    }
}
