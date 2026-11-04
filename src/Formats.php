<?php
declare(strict_types=1);
namespace IntelligentIntern\SepaCreator;
use IntelligentIntern\SepaCreator\Internal\Resources;

/** Explicit, offline schema allowlist; a namespace alone never enables a format. */
final class Formats
{
    public static function creditTransfers(): array {return [...array_map(fn($v)=>sprintf('pain.001.001.%02d',$v),range(3,13)),'pain.001.002.03','pain.001.003.03'];}
    public static function directDebits(): array {return [...array_map(fn($v)=>sprintf('pain.008.001.%02d',$v),range(2,12)),'pain.008.002.02','pain.008.003.02'];}
    public static function statuses(): array {return ['pain.002.001.10','pain.002.001.15'];}
    public static function all(): array {return [...self::creditTransfers(),...self::directDebits(),...self::statuses()];}
    public static function schema(string $format): string {
        if (!in_array($format,self::all(),true)) throw new \InvalidArgumentException('Unsupported message format: '.$format);
        return Resources::path('schemas/'.$format.'.xsd');
    }
    public static function namespace(string $format): string {self::schema($format); return 'urn:iso:std:iso:20022:tech:xsd:'.$format;}
    public static function supportsUetr(string $format): bool {return substr($format,9,3)==='001' && (int)substr($format,-2)>=(str_starts_with($format,'pain.001.')?9:8);}
}
