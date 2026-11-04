<?php
declare(strict_types=1);
use IntelligentIntern\SepaCreator\{SepaCreator,PaymentBatch,Party,CreditTransfer,Iban,StructuredAddress,CharacterSet,JsonInput};

require dirname(__DIR__).'/vendor/autoload.php';

spl_autoload_register(static function ($class) {
    $prefix = 'IntelligentIntern\\SepaCreator\\';
    if (str_starts_with($class, $prefix)) require dirname(__DIR__) . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
});
$checks = 0;
function check(bool $pass, string $message): void {global $checks; if (!$pass) throw new RuntimeException($message); $checks++;}
function rejects(callable $fn, string $message, string $class = InvalidArgumentException::class): void {
    try {$fn();} catch (Throwable $e) {check($e instanceof $class, $message . ': wrong exception ' . get_class($e)); return;}
    check(false, $message . ': accepted invalid input');
}
function batch(array $transfers, string $date = '2026-11-05'): PaymentBatch {
    return new PaymentBatch('MSG-20261104-001','PMT-001',new Party('Example GmbH','DE89370400440532013000'),
        $date,$transfers,new DateTimeImmutable('2026-11-04T09:00:00+01:00'));
}
function cli(array $args): array {
    $process = proc_open([PHP_BINARY, dirname(__DIR__) . '/bin/sepa-creator', ...$args],
        [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    fclose($pipes[0]); $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]); return [proc_close($process),$out,$err];
}
foreach (json_decode(file_get_contents(__DIR__ . '/fixtures/iban-examples.json'),true,32,JSON_THROW_ON_ERROR) as $country=>$iban) {
    check((new Iban($iban))->value === strtoupper($iban),'SWIFT public example: ' . $country);
}
check((new Iban('de89 3704 0044 0532 0130 00'))->value === 'DE89370400440532013000','IBAN case and display-space normalisation');
foreach (['DE88370400440532013000','DE8937040044053201300','ZZ89370400440532013000',"DE89\n370400440532013000",'DE89-370400440532013000'] as $bad) rejects(fn()=>new Iban($bad),'bad IBAN rejected');
$creditor = new Party('Example Supplier','FR1420041010050500013M02606');
$weakCaller = require __DIR__.'/fixtures/weak-caller.php';
rejects(fn()=>$weakCaller($creditor),'float must also fail when the calling file disables strict types');
foreach ([0,1,0.1,0.1+0.2,'0','0.00','-1.00','1e2','1,00','01.00','1.001','1000000000.00',' 1.00','1.00 '] as $bad) {
    rejects(fn()=>new CreditTransfer('INV1',$creditor,$bad),'ambiguous/out-of-range amount rejected');
}
check((new CreditTransfer('INV1',$creditor,'1.2'))->amountCents === 120,'one fractional digit padded exactly');
foreach (['/INV','INV/','INV//1',str_repeat('I',36),"INV\n1"] as $id) rejects(fn()=>new CreditTransfer($id,$creditor,'1.00'),'identifier rules');
rejects(fn()=>new Party('Example','DE89370400440532013000','BAD'),'BIC shape');
rejects(fn()=>new Party(str_repeat('A',71),'DE89370400440532013000'),'SEPA name limit');
rejects(fn()=>new CreditTransfer('LONG',$creditor,'1.00',str_repeat('R',141)),'remittance length limit');
rejects(fn()=>new Party(str_repeat('ä',71),'DE89370400440532013000'),'Unicode character limit');
rejects(fn()=>new Party("Bad\0Name",'DE89370400440532013000'),'XML control character');
rejects(fn()=>new Party("\xFF",'DE89370400440532013000'),'malformed UTF-8');
$a = new CreditTransfer('INV1',$creditor,'149.90','Invoice 1');
$b = new CreditTransfer('INV2',new Party('Other Supplier','NL91ABNA0417164300','ABNANL2A'),'0.10',creditorReference:'RF18539007547034');
rejects(fn()=>batch([]),'empty batch');
rejects(fn()=>batch([$a,$a]),'duplicate identifiers');
rejects(fn()=>batch(array_fill(0,10001,$a)),'batch input resource limit');
rejects(fn()=>batch([$a],'2026-02-30'),'invalid calendar date');
rejects(fn()=>batch([$a],'2026-11-03'),'execution before creation');
rejects(fn()=>new CreditTransfer('X',$creditor,'1.00',creditorReference:'RF19539007547034'),'RF checksum');
rejects(fn()=>new CreditTransfer('X',$creditor,'1.00','Invoice',creditorReference:'RF18539007547034'),'remittance choices exclusive');
$writer = new SepaCreator(); $input = batch([$a,$b]); $file = $writer->create($input);
$doc = new DOMDocument(); check($doc->loadXML($file->xml,LIBXML_NONET),'generated well-formed XML');
$xp = new DOMXPath($doc);$xp->registerNamespace('p',SepaCreator::NAMESPACE);
function value(DOMXPath $xp,string $path): string {return $xp->evaluate('string(/p:Document/p:CstmrCdtTrfInitn/'.$path.')');}
check($doc->schemaValidate(__DIR__.'/../resources/pain.001.001.09.xsd'),'independent official ISO XSD validation');
check(value($xp,'p:GrpHdr/p:NbOfTxs')==='2' && value($xp,'p:PmtInf/p:NbOfTxs')==='2','group and batch counts');
check(value($xp,'p:GrpHdr/p:CtrlSum')==='150.00' && value($xp,'p:PmtInf/p:CtrlSum')==='150.00','cent-exact control sums');
check(value($xp,'p:PmtInf/p:ReqdExctnDt/p:Dt')==='2026-11-05','2019 ISO date-choice structure');
check(value($xp,'p:PmtInf/p:DbtrAgt/p:FinInstnId/p:Othr/p:Id')==='NOTPROVIDED','IBAN-only debtor agent fallback');
check(value($xp,'p:PmtInf/p:CdtTrfTxInf[2]/p:CdtrAgt/p:FinInstnId/p:BICFI')==='ABNANL2A','BICFI in V09');
check(value($xp,'p:PmtInf/p:CdtTrfTxInf[2]/p:RmtInf/p:Strd/p:CdtrRefInf/p:Ref')==='RF18539007547034','structured RF reference emitted');
check(value($xp,'p:PmtInf/p:ChrgBr')==='SLEV','SEPA charge bearer');
check($xp->query('//p:InstdAmt[@Ccy="EUR"]')->length===2,'only EUR transfers');
check($writer->create($input)->xml===$file->xml,'retry determinism with fixed IDs/time/order');
check($file->controlSum==='150.00' && $file->transactionCount===2 && $file->sha256===hash('sha256',$file->xml),'archive metadata bound to exact bytes');
$small = $writer->create(batch([new CreditTransfer('A',$creditor,'0.10'),new CreditTransfer('B',$creditor,'0.20')]));
check($small->controlSum==='0.30','classic float-precision boundary');
$large = $writer->create(batch([new CreditTransfer('A',$creditor,'999999999.99'),new CreditTransfer('B',$creditor,'999999999.99')]));
check($large->controlSum==='1999999999.98','large exact sum may exceed individual-transfer maximum');
$special = new Party('Müller & Söhne <Test>','DE89370400440532013000',address:new StructuredAddress('Köln','DE','Straße','12','50667'));
$unicode = batch([new CreditTransfer('U',$special,'1.00','Invoice & account <42>')]);
rejects(fn()=>$writer->create($unicode),'extended characters require explicit bank agreement');
$extended = (new SepaCreator(CharacterSet::Utf8))->create($unicode);
$ud = new DOMDocument();$ud->loadXML($extended->xml);
check($ud->getElementsByTagNameNS(SepaCreator::NAMESPACE,'Nm')->item(2)->textContent==='Müller & Söhne <Test>','XML injection payload remains literal beneficiary name');
check($ud->getElementsByTagName('Test')->length===0 && $ud->getElementsByTagName('StrtNm')->length===1,'literal escaping and structured address');
$outside = json_decode(file_get_contents(__DIR__.'/fixtures/iban-examples.json'),true)['AE'];
rejects(fn()=>$writer->create(batch([new CreditTransfer('NON-SEPA',new Party('Outside SEPA',$outside),'1.00')])),'non-SEPA country excluded from SCT generation');
// XSD must really reject structural corruption, not merely parse the generated document.
$broken = new DOMDocument();$broken->loadXML($file->xml);
$amount = $broken->getElementsByTagNameNS(SepaCreator::NAMESPACE,'InstdAmt')->item(0);$amount->parentNode->removeChild($amount);
$previous = libxml_use_internal_errors(true);
check(!$broken->schemaValidate(__DIR__.'/../resources/pain.001.001.09.xsd'),'official schema rejects missing transfer amount');
libxml_clear_errors();libxml_use_internal_errors($previous);
$json = json_decode(file_get_contents(__DIR__.'/../examples/batch.json'),true,64,JSON_THROW_ON_ERROR);
check($writer->create(JsonInput::batch($json))->controlSum==='150.00','JSON transport matches API');
foreach (['amount-number','typo','bad-time','boolean-string','bad-address'] as $case) {
    $bad = $json;
    if ($case==='amount-number') $bad['transfers'][0]['amount']=149.90;
    if ($case==='typo') $bad['ammount']='1.00';
    if ($case==='bad-time') $bad['createdAt']='2026-02-30T09:00:00+01:00';
    if ($case==='boolean-string') $bad['batchBooking']='false';
    if ($case==='bad-address') $bad['debtor']['address']['town']='';
    rejects(fn()=>JsonInput::batch($bad),'strict JSON case: '.$case);
}
$temp = sys_get_temp_dir().'/sepa-test-'.bin2hex(random_bytes(8));mkdir($temp,0700);
try {
    $file->writeTo($temp.'/payment.xml');
    check(file_get_contents($temp.'/payment.xml')===$file->xml,'complete private file publication');
    check((fileperms($temp.'/payment.xml')&0777)===0600,'private output permissions');
    rejects(fn()=>$small->writeTo($temp.'/payment.xml'),'existing file preserved',RuntimeException::class);
    check(file_get_contents($temp.'/payment.xml')===$file->xml,'no replacement after refused write');
    file_put_contents($temp.'/victim','unchanged');symlink($temp.'/victim',$temp.'/link');
    rejects(fn()=>$file->writeTo($temp.'/link'),'symlink not overwritten',RuntimeException::class);
    check(file_get_contents($temp.'/victim')==='unchanged','symlink target unchanged');
    [$exit,$stdout,$stderr]=cli([__DIR__.'/../examples/batch.json']);check($exit===0&&str_starts_with($stdout,'<?xml')&&$stderr==='','CLI clean XML stdout');
    [$exit,$stdout]=cli([__DIR__.'/../examples/batch.json','--json']);$payload=json_decode($stdout,true,64,JSON_THROW_ON_ERROR);
    check($exit===0&&hash('sha256',$payload['xml'])===$payload['sha256'],'CLI JSON binds XML and digest');
    [$exit,$stdout]=cli([__DIR__.'/../examples/batch.json','--output',$temp.'/cli.xml']);check($exit===0&&is_file($temp.'/cli.xml'),'CLI private output');
    [$exit]=cli([__DIR__.'/../examples/batch.json','--output',$temp.'/cli.xml']);check($exit===3,'CLI refuses accidental replacement');
    $bad=$json;$bad['transfers'][0]['amount']=0.1;file_put_contents($temp.'/invalid.json',json_encode($bad));
    [$exit,$stdout]=cli([$temp.'/invalid.json']);check($exit===1&&$stdout==='','CLI numeric amount rejected without partial XML');
} finally {foreach(new DirectoryIterator($temp) as $p)if(!$p->isDot())unlink($p->getPathname());rmdir($temp);}
require __DIR__.'/runtime-policy.php';
require __DIR__.'/extended.php';
echo json_encode(['generationFormats'=>count($formatsTested),'statusFormats'=>count(\IntelligentIntern\SepaCreator\Formats::statuses()),'checks'=>$checks,'format'=>SepaCreator::FORMAT,'php'=>PHP_VERSION,'schema'=>'ISO 20022 and legacy SEPA profiles, hash-pinned'],JSON_PRETTY_PRINT)."\n";
