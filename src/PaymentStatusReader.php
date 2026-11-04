<?php
declare(strict_types=1);
namespace IntelligentIntern\SepaCreator;
use IntelligentIntern\SepaCreator\Internal\Xml;
final class PaymentStatusReader
{
    public function read(string $xml): PaymentStatus {
        $doc=Xml::parse($xml);$format=Xml::format($doc);
        if (!in_array($format,Formats::statuses(),true)) throw new \InvalidArgumentException('Unsupported payment status format');
        $schema=Formats::schema($format);$errors=Xml::schemaErrors($doc,$schema);
        (new ValidationReport($errors?'invalid':'valid',$format,$errors))->assertValid();
        $xp=Xml::xpath($doc);$str=fn(string $path,?\DOMNode $n=null):string=>$xp->evaluate('string('.$path.')',$n);
        $optional=fn(string $path,\DOMNode $n):?string=>($v=$str($path,$n))===''?null:$v;
        $reasons=static function(\DOMNode $n)use($xp,$str):array {
            $list=[];foreach($xp->query('p:StsRsnInf',$n) as $r) {
                $additional=[];foreach($xp->query('p:AddtlInf',$r)as$a)$additional[]=$a->textContent;
                $list[]=['code'=>$str('p:Rsn/p:Cd',$r)?:null,'proprietary'=>$str('p:Rsn/p:Prtry',$r)?:null,'additionalInformation'=>$additional];
            }return $list;
        };
        $message=$xp->query('/p:Document/p:CstmrPmtStsRpt')->item(0);$original=$xp->query('p:OrgnlGrpInfAndSts',$message)->item(0);$groups=[];$count=0;
        foreach($xp->query('p:OrgnlPmtInfAndSts',$message)as$g) {
            $transactions=[];
            foreach($xp->query('p:TxInfAndSts',$g)as$t) {
                if(++$count>10000)throw new \InvalidArgumentException('Status report exceeds 10000 transactions');
                $transactions[]=['instructionId'=>$optional('p:OrgnlInstrId',$t),'endToEndId'=>$optional('p:OrgnlEndToEndId',$t),'uetr'=>$optional('p:OrgnlUETR',$t),'status'=>$optional('p:TxSts',$t),'reasons'=>$reasons($t),'accountServicerReference'=>$optional('p:AcctSvcrRef',$t)];
            }
            $groups[]=['paymentInfoId'=>$str('p:OrgnlPmtInfId',$g),'status'=>$optional('p:PmtInfSts',$g),'reasons'=>$reasons($g),'transactions'=>$transactions];
        }
        return new PaymentStatus($format,$str('p:GrpHdr/p:MsgId',$message),$str('p:GrpHdr/p:CreDtTm',$message),$str('p:OrgnlMsgId',$original),$str('p:OrgnlMsgNmId',$original),$optional('p:GrpSts',$original),$reasons($original),$groups,hash('sha256',$xml),hash_file('sha256',$schema));
    }
}
