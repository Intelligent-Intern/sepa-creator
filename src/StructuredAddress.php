<?php
declare(strict_types=1);
namespace IntelligentIntern\SepaCreator;
use IntelligentIntern\SepaCreator\Internal\Check;

final readonly class StructuredAddress
{
    public string $town;
    public string $country;
    public ?string $street;
    public ?string $buildingNumber;
    public ?string $postCode;
    public function __construct(string $town, string $country, ?string $street = null, ?string $buildingNumber = null, ?string $postCode = null)
    {
        $this->town = Check::text($town, 35, 'address.town');
        $country = strtoupper($country);
        if (!preg_match('/^[A-Z]{2}$/D', $country)) throw new \InvalidArgumentException('address.country: expected ISO alpha-2 code');
        $this->country = $country;
        $this->street = $street === null ? null : Check::text($street, 70, 'address.street');
        $this->buildingNumber = $buildingNumber === null ? null : Check::text($buildingNumber, 16, 'address.buildingNumber');
        $this->postCode = $postCode === null ? null : Check::text($postCode, 16, 'address.postCode');
    }
}
