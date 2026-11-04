<?php
declare(strict_types=1);
use IntelligentIntern\SepaCreator\{SepaCreator,Party,CreditTransfer,StructuredAddress,CharacterSet,JsonInput,Formats,PaymentMessage,PaymentGroup,DirectDebit,Mandate,CreditorIdentifier,TransactionOptions,DocumentValidator,PaymentStatusReader,BankProfile};
use Digitick\Sepa\Util\Sanitizer;

$now=new DateTimeImmutable('2026-11-04T09:00:00+01:00');
$origin=new Party('Example GmbH','DE89370400440532013000','COBADEFFXXX');
$counterparty=new Party('Supplier SARL','FR1420041010050500013M02606','PSSTFRPPXXX');
$validator=new DocumentValidator();$formatsTested=[];
function message(array $groups,?string $format=null):PaymentMessage {
    return new PaymentMessage('EXTENDED-1','Example GmbH',new DateTimeImmutable('2026-11-04T09:00:00+01:00'),$groups,$format);
}
function extendedGroup(bool $dd,string $id='G1',string $e2e='E2E1',string $scheme='CORE',string $sequence='RCUR',?Mandate $mandate=null,?TransactionOptions $options=null,?Party $party=null):PaymentGroup {
    $p=$party??new Party('Example GmbH','DE89370400440532013000','COBADEFFXXX');
    $t=$dd?new DirectDebit($e2e,$p,'12.34',$mandate??new Mandate('M-'.$e2e,'2026-01-01'),creditorReference:'RF18539007547034',options:$options):new CreditTransfer($e2e,$p,'12.34',creditorReference:'RF18539007547034',options:$options);
    return new PaymentGroup($id,$p,'2026-11-05',[$t],creditorIdentifier:$dd?'DE98ZZZ09999999999':null,scheme:$scheme,sequenceType:$sequence);
}
foreach ([...Formats::creditTransfers(),...Formats::directDebits()] as $format) {
    $dd=str_starts_with($format,'pain.008.');
    foreach($dd?['CORE','B2B']:['TRF']as$scheme) {
        $msg=message([extendedGroup($dd,'G1','E2E1',$dd?$scheme:'CORE'),extendedGroup($dd,'G2','E2E2',$dd?$scheme:'CORE')],$format);
        $f=$writer->create($msg);$r=$validator->validate($f->xml);
        check($r->isValid(),$format.' '.$scheme.' mandatory XSD/business validation');
        check($f->format===$format&&$f->transactionCount===2&&$f->controlSum==='24.68'&&$f->paymentGroupCount===2,$format.' correct group metadata');
        check($writer->create($msg)->xml===$f->xml,$format.' deterministic bytes');
        check($f->schemaSha256===hash_file('sha256',Formats::schema($format)),$format.' pinned independent schema hash');
        check(str_contains($f->xml,'RF18539007547034'),$format.' structured reference preserved');
    }
    $formatsTested[]=$format;
}
check(count($formatsTested)===26,'26 generation formats exercised with real XML');
foreach(['FRST','RCUR','OOFF','FNAL']as$seq)check($validator->validate($writer->create(message([extendedGroup(true,sequence:$seq)]))->xml)->isValid(),'DD sequence '.$seq);
$addressed=new Party('Example GmbH','DE89370400440532013000','COBADEFFXXX',new StructuredAddress('Bonn','DE','Adenauerallee','12','53113'));
foreach([...Formats::creditTransfers(),...Formats::directDebits()]as$format)if(substr($format,9,3)==='001') {
    $xml=$writer->create(message([extendedGroup(str_starts_with($format,'pain.008.'),party:$addressed)],$format))->xml;
    check(substr_count($xml,'<StrtNm>Adenauerallee</StrtNm>')===2,$format.' preserves both structured addresses');
}
rejects(fn()=>$writer->create(message([extendedGroup(false,party:$addressed)],'pain.001.003.03')),'national address cannot be silently discarded');
foreach(['pain.001.001.09','pain.001.001.13','pain.008.001.08','pain.008.001.12']as$format) {
    $dd=str_starts_with($format,'pain.008.');$options=new TransactionOptions('INS1','SUPP','SUPP','550e8400-e29b-41d4-a716-446655440000');
    $xml=$writer->create(message([extendedGroup($dd,options:$options)],$format))->xml;
    check(str_contains($xml,'<UETR>'.$options->uetr.'</UETR>')&&str_contains($xml,'<InstrId>INS1</InstrId>'),$format.' explicit UETR/instruction preserved');
    check(str_contains($xml,'<Purp>')&&str_contains($xml,'<CtgyPurp>'),$format.' purpose/category preserved');
}
rejects(fn()=>message([extendedGroup(false,options:new TransactionOptions(uetr:'550e8400-e29b-41d4-a716-446655440000'))],'pain.001.001.03'),'UETR not representable must fail');
rejects(fn()=>new TransactionOptions(uetr:'550e8400-e29b-11d4-a716-446655440000'),'non-v4 UETR');
rejects(fn()=>new TransactionOptions(purposeCode:'toolong'),'invalid purpose');
rejects(fn()=>message([extendedGroup(false),extendedGroup(false,'G2')]),'duplicate E2E across groups');
rejects(fn()=>message([extendedGroup(false),extendedGroup(false,'G1','E2E2')]),'duplicate payment group ID');
rejects(fn()=>message([extendedGroup(false),extendedGroup(true,'G2','E2E2')]),'mixed family');
rejects(fn()=>message([extendedGroup(false)],'pain.008.001.08'),'wrong format family');
rejects(fn()=>message([]),'empty message');
rejects(fn()=>new PaymentGroup('G1',$origin,'2026-11-05',[]),'empty group');
rejects(fn()=>new PaymentGroup('G1',$origin,'2026-11-05',[new CreditTransfer('T1',$origin,'1.00')],creditorIdentifier:'DE98ZZZ09999999999'),'CT in DD group');
check((new CreditorIdentifier('DE98ABC09999999999'))->value==='DE98ABC09999999999','business code excluded from creditor ID checksum');
foreach(['DE97ZZZ09999999999','DE00ZZZ09999999999','DE98','ZZ98ZZZ!']as$ci)rejects(fn()=>new CreditorIdentifier($ci),'bad creditor identifier');
rejects(fn()=>new Mandate('M1','2026-02-30'),'invalid mandate date');
rejects(fn()=>message([extendedGroup(true,mandate:new Mandate('M1','2026-11-05'))]),'future mandate signature');
rejects(fn()=>message([extendedGroup(true,mandate:new Mandate('M1','2026-01-01',finalCollectionDate:'2026-11-04'))]),'expired mandate');
rejects(fn()=>new Mandate('M1','2026-01-01',originalMandateId:'M1'),'meaningless mandate ID amendment');
rejects(fn()=>new Mandate('M1','2026-01-01',originalDebtorIban:$origin->iban,amendedDebtorAccount:true),'ambiguous account amendment');
foreach(Formats::directDebits()as$format) {
    $mandate=new Mandate('MNEW','2026-01-01','MOLD',$counterparty->iban);
    $xml=$writer->create(message([extendedGroup(true,mandate:$mandate)],$format))->xml;
    check(strpos($xml,'<OrgnlMndtId>')<strpos($xml,'<OrgnlDbtrAcct>')&&str_contains($xml,$counterparty->iban->value),$format.' preserves amendment and XSD order');
    $xml=$writer->create(message([extendedGroup(true,sequence:substr($format,9,3)!=='001'?'FRST':'RCUR',mandate:new Mandate('M1','2026-01-01',amendedDebtorAccount:true))],$format))->xml;
    check(str_contains($xml,'<Id>SMNDA</Id>'),$format.' SMNDA amendment');
}
$xml=$writer->create(message([extendedGroup(true,mandate:new Mandate('M1','2026-01-01',finalCollectionDate:'2026-12-31'))]))->xml;
check(str_contains($xml,'<FnlColltnDt>2026-12-31</FnlColltnDt>'),'final collection date actually emitted');
$base=$writer->create(message([extendedGroup(false)]))->xml;
foreach([
    'invalid IBAN checksum'=>str_replace('DE89370400440532013000','DE88370400440532013000',$base),
    'wrong count'=>str_replace('<NbOfTxs>1</NbOfTxs>','<NbOfTxs>2</NbOfTxs>',$base),
    'wrong control sum'=>str_replace('<CtrlSum>12.34</CtrlSum>','<CtrlSum>12.35</CtrlSum>',$base),
    'zero amount'=>str_replace('>12.34</InstdAmt>','>0.00</InstdAmt>',$base),
    'over limit amount'=>str_replace('>12.34</InstdAmt>','>1000000000.00</InstdAmt>',$base),
    'non-EUR'=>str_replace('Ccy="EUR"','Ccy="USD"',$base),
    'invalid RF checksum'=>str_replace('RF18539007547034','RF19539007547034',$base),
    'missing origin name'=>str_replace('<Nm>Example GmbH</Nm>','',$base),
    'non-IBAN account'=>str_replace('<IBAN>DE89370400440532013000</IBAN>','<Othr><Id>123456</Id></Othr>',$base),
    'invalid BIC'=>str_replace('COBADEFFXXX','12345678',$base),
    'wrong service'=>str_replace('<Cd>SEPA</Cd>','<Cd>NOPE</Cd>',$base),
    'wrong charges'=>str_replace('>SLEV<','>SHAR<',$base),
    'date before creation'=>str_replace('2026-11-05','2026-11-03',$base),
    'invalid character set'=>str_replace('Example GmbH','Müller GmbH',$base),
    'DTD external entity'=>'<!DOCTYPE Document [<!ENTITY x SYSTEM "file:///etc/hostname">]>'.$base,
    'UTF16'=>'\xff\xfe'.str_replace('<',"<\0",$base),
    'malformed'=>'<Document',
    'wrong schema element'=>str_replace('CdtTrfTxInf','WrongTransaction',$base),
]as$description=>$xml)check(!$validator->validate($xml)->isValid(),'validator rejects '.$description);
$badMandate=$writer->create(message([extendedGroup(true)]))->xml;
check(!$validator->validate(str_replace('<DtOfSgntr>2026-01-01</DtOfSgntr>','',$badMandate))->isValid(),'mandatory mandate signature beyond XSD optionality');
check(!$validator->validate(str_replace('<MndtId>M-E2E1</MndtId>','',$badMandate))->isValid(),'mandatory mandate ID beyond XSD optionality');
check(!$validator->validate(str_replace('DE98ZZZ09999999999','DE97ZZZ09999999999',$badMandate))->isValid(),'XML creditor ID checksum');
check($validator->validate(str_replace('pain.001.001.09','pain.001.001.99',$base))->status==='unsupported','unknown namespace is unsupported');
rejects(fn()=>Formats::schema('../../etc/passwd'),'schema allowlist');
$callback=static fn(string $value):string=>'GLOBAL-'.$value;Sanitizer::setSanitizer($callback);
try {
    $xml=$writer->create(message([extendedGroup(false)]))->xml;
    check(!str_contains($xml,'GLOBAL-')&&Sanitizer::getSanitizer()===$callback,'typed writer does not silently sanitize and restores global callback');
    rejects(fn()=>$writer->create(message([extendedGroup(false,party:$addressed)],'pain.001.003.03')),'generation error');
    check(Sanitizer::getSanitizer()===$callback,'global callback restored after exception');
}finally{Sanitizer::resetSanitizer();}
$utf8=new SepaCreator(CharacterSet::Utf8);$unicode=new Party('Müller & Söhne','DE89370400440532013000');
$xml=$utf8->create(message([extendedGroup(true,party:$unicode)]))->xml;
check(str_contains($xml,'Müller &amp; Söhne')&&(new DocumentValidator(CharacterSet::Utf8))->validate($xml)->isValid(),'opt-in UTF8 survives dependency without transliteration');
$profileWriter=new SepaCreator(profile:new BankProfile(omitGroupHeaderControlSum:true));
$xml=$profileWriter->create(message([extendedGroup(false)],'pain.001.001.03'))->xml;
$xpProfile=\IntelligentIntern\SepaCreator\Internal\Xml::xpath(\IntelligentIntern\SepaCreator\Internal\Xml::parse($xml));
check($xpProfile->query('/*/*/p:GrpHdr/p:CtrlSum')->length===0&&$validator->validate($xml)->isValid(),'explicit bank flag retains validation');
$native=new Digitick\Sepa\TransferFile\CustomerCreditTransferFile(new Digitick\Sepa\GroupHeader('NATIVE1','Example GmbH'));
$payment=new Digitick\Sepa\PaymentInformation('NATIVE-G1',$origin->iban->value,$origin->bic,$origin->name);$payment->setDueDate(new DateTimeImmutable('+1 day'));
$payment->addTransfer(new Digitick\Sepa\TransferInformation\CustomerCreditTransferInformation(1234,$counterparty->iban->value,$counterparty->name,'NATIVE-E1'));$native->addPaymentInformation($payment);
check($writer->createNative($native,'pain.001.001.13')->controlSum==='12.34','full upstream object adapter');
$invalidNative=new Digitick\Sepa\TransferFile\CustomerCreditTransferFile(new Digitick\Sepa\GroupHeader('NATIVE2','Example GmbH'));
$payment=new Digitick\Sepa\PaymentInformation('NATIVE-G1','DE88370400440532013000',$origin->bic,$origin->name);$payment->setDueDate(new DateTimeImmutable('+1 day'));
$payment->addTransfer(new Digitick\Sepa\TransferInformation\CustomerCreditTransferInformation(1234,$counterparty->iban->value,$counterparty->name,'NATIVE-E1'));$invalidNative->addPaymentInformation($payment);
rejects(fn()=>$writer->createNative($invalidNative,'pain.001.001.13'),'native adapter cannot skip IBAN validation');
foreach(Formats::statuses()as$format) {
    $xml=str_replace('pain.002.001.10',$format,file_get_contents(__DIR__.'/fixtures/status.xml'));
    $status=(new PaymentStatusReader())->read($xml);
    check($status->originalMessageId==='EXTENDED-1'&&$status->groupStatus==='PART','status original message/group status '.$format);
    check($status->groups[0]['transactions'][0]['endToEndId']==='E2E1'&&$status->groups[0]['transactions'][0]['reasons'][0]['code']==='AC01','status E2E/rejection reason '.$format);
    check($status->groups[0]['transactions'][1]['status']==='ACCP','status acceptance preserved without marking paid '.$format);
    check($status->sha256===hash('sha256',$xml),'status source digest '.$format);
    rejects(fn()=>(new PaymentStatusReader())->read(str_replace('<OrgnlMsgId>EXTENDED-1</OrgnlMsgId>','',$xml)),'status mandatory schema');
}
rejects(fn()=>(new PaymentStatusReader())->read($base),'initiation is not a status report');
rejects(fn()=>(new PaymentStatusReader())->read('<!DOCTYPE Document SYSTEM "https://example.com/test.dtd">'.$base),'status reader no DTD');
foreach(['direct-debit.json','multi-group.json']as$example) {
    $path=__DIR__.'/../examples/'.$example;$data=json_decode(file_get_contents($path),true,64,JSON_THROW_ON_ERROR);
    check($validator->validate($writer->create(JsonInput::message($data))->xml)->isValid(),'strict JSON transport '.$example);
    [$exit,$out,$err]=cli([$path,'--json']);check($exit===0&&json_decode($out,true)['transactionCount']>=1&&$err==='','CLI generation '.$example);
    $data['groups'][0]['transactions'][0]['amount']=12.34;rejects(fn()=>JsonInput::message($data),'JSON float rejected '.$example);
    $data['groups'][0]['transactions'][0]['amount']='12.34';$data['groups'][0]['typo']=1;rejects(fn()=>JsonInput::message($data),'unknown group field rejected');
}
[$exit,$out]=cli(['--formats']);check($exit===0&&count(json_decode($out,true)['creditTransfers'])===13,'CLI format inventory');
[$exit,$out]=cli(['--status',__DIR__.'/fixtures/status.xml']);check($exit===0&&json_decode($out,true)['originalMessageId']==='EXTENDED-1','CLI status parsing');
$temp=tempnam(sys_get_temp_dir(),'sepa-validate-');
try{file_put_contents($temp,$base);[$exit,$out]=cli(['--validate',$temp]);check($exit===0&&json_decode($out,true)['status']==='valid','CLI validation');file_put_contents($temp,str_replace('DE89','DE88',$base));[$exit,$out]=cli(['--validate',$temp]);check($exit===1&&json_decode($out,true)['status']==='invalid','CLI invalid report exit code');}finally{unlink($temp);}

$legacyWithOptions=batch([new CreditTransfer('LEGACY-OPTS',$origin,'1.00',options:new TransactionOptions('INS-LEGACY'))]);
check(str_contains($writer->create($legacyWithOptions)->xml,'<InstrId>INS-LEGACY</InstrId>'),'legacy batch does not silently discard new options');
$noBic=new Party('Example GmbH','DE89370400440532013000');
rejects(fn()=>(new SepaCreator(profile:new BankProfile(omitAgentIfBicMissing:true)))->create(message([extendedGroup(false,party:$noBic)])),'bank agent switch cannot bypass mandatory schema');
$before=libxml_use_internal_errors(false);
try{$validator->validate('<broken');check(libxml_use_internal_errors()===false,'validator restores libxml state on invalid XML');$validator->validate($base);check(libxml_use_internal_errors()===false,'validator restores libxml state on success');}finally{libxml_use_internal_errors($before);}
$repeated=$writer->create(message([extendedGroup(false,'G1','E1'),extendedGroup(false,'G2','E2')]))->xml;
check(!$validator->validate(str_replace('<EndToEndId>E2</EndToEndId>','<EndToEndId>E1</EndToEndId>',$repeated))->isValid(),'external XML duplicate IDs across groups');
$huge=[];for($i=0;$i<10000;$i++)$huge[]=new CreditTransfer('LARGE-'.$i,$origin,'999999999.99');
$largeMessage=message([new PaymentGroup('LARGE',$origin,'2026-11-05',$huge)]);
check($writer->create($largeMessage)->controlSum==='9999999999900.00','maximum batch size preserves exact integer control sum');
unset($huge,$largeMessage);
