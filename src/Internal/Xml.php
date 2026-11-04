<?php
declare(strict_types=1);
namespace IntelligentIntern\SepaCreator\Internal;
/** UTF-8 only, bounded, with no DTD or external entity expansion. */
final class Xml
{
    public static function parse(string $xml): \DOMDocument {
        if (strlen($xml)>25*1024*1024||!preg_match('//u',$xml)||str_contains($xml,"\0")||preg_match('/<!DOCTYPE|<!ENTITY/i',$xml)) throw new \InvalidArgumentException('XML must be UTF-8, at most 25 MiB, and contain no DTD/entities');
        if (preg_match('/<\?xml[^?]*encoding\s*=\s*[\'"]([^\'"]+)/i',$xml,$m)&&strtoupper($m[1])!=='UTF-8') throw new \InvalidArgumentException('Only UTF-8 XML encoding is supported');
        $prev=libxml_use_internal_errors(true);libxml_clear_errors();
        try {
            $doc=new \DOMDocument();$doc->resolveExternals=false;$doc->substituteEntities=false;
            if (!$doc->loadXML($xml,LIBXML_NONET)||$doc->doctype!==null) throw new \InvalidArgumentException('Malformed or prohibited XML');
            return $doc;
        } finally {libxml_clear_errors();libxml_use_internal_errors($prev);}
    }
    public static function xpath(\DOMDocument $doc): \DOMXPath {$xp=new \DOMXPath($doc);$xp->registerNamespace('p',$doc->documentElement->namespaceURI??'');return $xp;}
    public static function format(\DOMDocument $doc): string {
        $ns=$doc->documentElement?->namespaceURI??'';$prefix='urn:iso:std:iso:20022:tech:xsd:';
        if ($doc->documentElement?->localName!=='Document'||!str_starts_with($ns,$prefix)) throw new \InvalidArgumentException('Expected an ISO 20022 Document namespace');
        return substr($ns,strlen($prefix));
    }
    public static function schemaErrors(\DOMDocument $doc,string $schema): array {
        $prev=libxml_use_internal_errors(true);libxml_clear_errors();
        try {
            if ($doc->schemaValidate($schema)) return [];
            return array_map(fn($e)=>['code'=>'xsd','path'=>'line '.$e->line,'message'=>trim($e->message)],libxml_get_errors())?:[['code'=>'xsd','path'=>'/','message'=>'Schema validation failed']];
        } finally {libxml_clear_errors();libxml_use_internal_errors($prev);}
    }
}
