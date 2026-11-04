<?php
declare(strict_types=1);
namespace IntelligentIntern\SepaCreator;
final readonly class ValidationReport implements \JsonSerializable
{
    public function __construct(public string $status,public ?string $format,public array $findings,public array $metadata=[]) {}
    public function isValid(): bool {return $this->status==='valid';}
    public function assertValid(): void {
        if (!$this->isValid()) throw new \InvalidArgumentException('SEPA validation '.$this->status.': '.implode('; ',array_column($this->findings,'message')));
    }
    public function jsonSerialize(): array {return ['status'=>$this->status,'format'=>$this->format,'findings'=>$this->findings,'metadata'=>$this->metadata];}
}
