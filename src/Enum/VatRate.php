<?php

declare(strict_types=1);

namespace App\Enum;

use function intval;

/**
 * Czech VAT rates since 1. 1. 2024 and the exemption with deduction (books, § 71i), named on its document.
 * Issued invoices store the value, so a rate the law drops must stay as a case.
 */
enum VatRate: string
{
    case STANDARD = '21';
    case REDUCED = '12';
    case EXEMPT = '0';

    public function toPercentage(): int
    {
        return intval($this->value);
    }
}
