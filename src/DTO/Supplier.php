<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class Supplier
{
    public function __construct(
        #[Autowire(param: 'supplier.name')]
        public string $name,
        #[Autowire(param: 'supplier.address')]
        public string $address,
        #[Autowire(param: 'supplier.company_id')]
        public string $companyId,
        #[Autowire(param: 'supplier.vat_id')]
        public string $vatId,
        #[Autowire(param: 'supplier.bank_account')]
        public string $bankAccount,
        #[Autowire(param: 'supplier.register_entry')]
        public string $registerEntry,
    ) {
    }
}
