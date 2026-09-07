<?php

declare(strict_types=1);

namespace App\Enum;

enum InvoiceStatus: string
{
    case DRAFT = 'draft';
    case ISSUED = 'issued';
}
