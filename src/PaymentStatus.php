<?php
declare(strict_types=1);
namespace IntelligentIntern\SepaCreator;
/** Bank-reported information only. Acceptance is never interpreted as a booked payment. */
final readonly class PaymentStatus implements \JsonSerializable
{
    public function __construct(public string $format,public string $messageId,public string $createdAt,public string $originalMessageId,
        public string $originalFormat,public ?string $groupStatus,public array $reasons,public array $groups,public string $sha256,public string $schemaSha256) {}
    public function jsonSerialize():array {return get_object_vars($this);}
}
