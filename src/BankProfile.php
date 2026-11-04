<?php
declare(strict_types=1);
namespace IntelligentIntern\SepaCreator;
/** Explicit serialization switches; mandatory ISO/business validation still applies. */
final readonly class BankProfile
{
    public function __construct(public bool $omitGroupHeaderControlSum=false,public bool $omitAgentIfBicMissing=false) {}
}
