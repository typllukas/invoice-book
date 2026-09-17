<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Serializer\Attribute\Groups;

final readonly class InvoicePdf
{
    public function __construct(
        #[Groups(['invoice_pdf:read'])]
        public string $name,
        #[Groups(['invoice_pdf:read'])]
        public string $base64Content,
    ) {
    }
}
