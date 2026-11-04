<?php
declare(strict_types=1);
namespace IntelligentIntern\SepaCreator\Internal;

/** Only the trusted, shipped resources are selectable. No network or caller-provided schemas. */
final class Resources
{
    public static function path(string $name): string
    {
        if (!in_array($name, ['pain.001.001.09.xsd', 'iban-formats.json'], true)
            && !preg_match('~^schemas/pain\.(?:001|002|008)\.[0-9]{3}\.[0-9]{2}\.xsd$~D', $name)) {
            throw new \LogicException('Unknown bundled resource');
        }
        $root = dirname(__DIR__, 2) . '/resources/';
        $manifest = json_decode(file_get_contents($root . 'manifest.json'), true, 32, JSON_THROW_ON_ERROR);
        $path = $root . $name;
        if (!is_file($path) || !hash_equals($manifest['files'][$name] ?? '', hash_file('sha256', $path))) {
            throw new \RuntimeException('Bundled resource integrity check failed: ' . $name);
        }
        return $path;
    }
}
