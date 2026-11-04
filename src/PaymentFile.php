<?php
declare(strict_types=1);
namespace IntelligentIntern\SepaCreator;

final readonly class PaymentFile implements \JsonSerializable
{
    public string $sha256;
    public function __construct(public string $xml, public string $messageId, public int $transactionCount,
        public string $controlSum, public string $schemaSha256, public string $format = SepaCreator::FORMAT, public int $paymentGroupCount = 1)
    {
        $this->sha256 = hash('sha256', $xml);
    }
    public function jsonSerialize(): array
    {
        return ['format' => $this->format, 'paymentGroupCount' => $this->paymentGroupCount, 'messageId' => $this->messageId,
            'transactionCount' => $this->transactionCount, 'controlSum' => $this->controlSum,
            'currency' => 'EUR', 'sha256' => $this->sha256, 'schemaSha256' => $this->schemaSha256];
    }
    /** Publish a complete private file atomically. Existing files and symlinks are never overwritten. */
    public function writeTo(string $path): void
    {
        $dir = realpath(dirname($path)); $name = basename($path);
        if ($dir === false || !is_dir($dir) || in_array($name, ['', '.', '..'], true)) throw new \InvalidArgumentException('Output directory must already exist');
        $temp = @tempnam($dir, '.sepa-');
        if ($temp === false || dirname($temp) !== $dir) {
            if ($temp !== false) unlink($temp);
            throw new \RuntimeException('Cannot create private output file');
        }
        try {
            if (!chmod($temp, 0600) || file_put_contents($temp, $this->xml) !== strlen($this->xml)) throw new \RuntimeException('Cannot write complete SEPA file');
            if (!@link($temp, $dir . DIRECTORY_SEPARATOR . $name)) throw new \RuntimeException('Output already exists or cannot be published atomically');
        } finally {if (is_file($temp)) unlink($temp);}
    }
}
