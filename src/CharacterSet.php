<?php
declare(strict_types=1);
namespace IntelligentIntern\SepaCreator;
use IntelligentIntern\SepaCreator\Internal\Check;

enum CharacterSet: string
{
    case SepaBasic = 'basic';
    /** Explicit opt-in only when the accepting bank agrees to the character repertoire. */
    case Utf8 = 'utf8';
    public function check(string $text): void {if ($this === self::SepaBasic) Check::basic($text);}
}
