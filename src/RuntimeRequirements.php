<?php
declare(strict_types=1);
namespace IntelligentIntern\SepaCreator;

/** Security patch floors reviewed against the 2026-11-19 PHP releases; stable builds only. */
final class RuntimeRequirements
{
    public const CONSTRAINT = '~8.2.36 || ~8.3.37 || ~8.4.28 || ~8.5.13';
    private const MINIMUM = ['8.2'=>'8.2.36', '8.3'=>'8.3.37', '8.4'=>'8.4.28', '8.5'=>'8.5.13'];

    public static function supports(string $version): bool
    {
        $version = explode('+', $version, 2)[0]; // Build metadata does not change the upstream patch version.
        if (!preg_match('/\A([0-9]+)\.([0-9]+)\.([0-9]+)\z/', $version, $parts)) return false;
        $minimum = self::MINIMUM[$parts[1].'.'.$parts[2]] ?? null;
        return $minimum !== null && version_compare($version, $minimum, '>=');
    }

    public static function assertSupported(): void
    {
        if (!self::supports(PHP_VERSION)) {
            throw new \RuntimeException('Unsupported PHP runtime '.PHP_VERSION.'. Security updates required: '.self::CONSTRAINT.'. See SECURITY.md.');
        }
    }
}
