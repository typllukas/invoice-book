<?php

declare(strict_types=1);

namespace App\DTO;

use App\Validator\CompanyId;
use App\Validator\VatId;
use Symfony\Component\Validator\Constraints as Assert;

final class InvoiceInput
{
    #[Assert\NotBlank(normalizer: 'trim')]
    #[Assert\Length(max: 255)]
    public string $clientName;

    #[Assert\NotBlank(normalizer: 'trim')]
    #[Assert\Length(max: 255)]
    public string $clientAddress;

    #[Assert\NotBlank]
    #[CompanyId]
    public string $clientCompanyId;

    #[Assert\NotBlank(allowNull: true)]
    #[VatId]
    public ?string $clientVatId = null;

    #[Assert\NotBlank]
    #[Assert\Date]
    #[Assert\GreaterThanOrEqual(
        propertyPath: 'taxPointAt',
        message: 'Splatnost nemůže být dřív než datum uskutečnění plnění.',
    )]
    public string $dueAt;

    #[Assert\NotBlank]
    #[Assert\Date]
    public string $taxPointAt;

    #[Assert\NotBlank(allowNull: true, normalizer: 'trim')]
    #[Assert\Length(max: 1000)]
    public ?string $note = null;

    /**
     * @var list<InvoiceItemInput>
     */
    #[Assert\Valid]
    public array $items = [];
}
