<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enum\VatRate;
use Symfony\Component\Serializer\Attribute\Groups;

final readonly class VatSummaryLine
{
    public function __construct(
        #[Groups(['invoice:operation:get'])]
        public VatRate $vatRate,
        #[Groups(['invoice:operation:get'])]
        public int $netAmount,
        #[Groups(['invoice:operation:get'])]
        public int $vatAmount,
        #[Groups(['invoice:operation:get'])]
        public int $grossAmount,
    ) {
    }
}
