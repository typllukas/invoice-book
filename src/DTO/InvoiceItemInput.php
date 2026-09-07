<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enum\VatRate;
use BcMath\Number;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

use function preg_match;

final class InvoiceItemInput
{
    /**
     * DECIMAL(12,2) in "haléře": the largest amount the column holds.
     */
    private const int MAXIMUM_UNIT_PRICE_NET = 999999999999;

    /**
     * Stricter than Assert\Type('numeric'), which passes "1e5" and " 1", and the D flag refuses "1\n":
     * new Number() in InvoiceInputMapper throws on all three, a 500 instead of a 422.
     */
    private const string QUANTITY_PATTERN = '/^\d+(\.\d{1,3})?$/D';

    public ?Ulid $id = null;

    #[Assert\NotBlank(normalizer: 'trim')]
    #[Assert\Length(max: 255)]
    public string $description;

    /**
     * @var numeric-string
     */
    #[Assert\NotNull]
    #[Assert\Regex(pattern: self::QUANTITY_PATTERN, message: 'Množství musí být číslo, nejvýš na tři desetinná místa.')]
    #[Assert\GreaterThan(value: 0)]
    #[Assert\LessThanOrEqual(value: 9999999.999)]
    public string $quantity;

    #[Assert\NotBlank(normalizer: 'trim')]
    #[Assert\Length(max: 16)]
    public string $unit;

    #[Assert\NotNull]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'Cena za jednotku nesmí být záporná.')]
    #[Assert\LessThanOrEqual(value: self::MAXIMUM_UNIT_PRICE_NET, message: 'Cena za jednotku je příliš velká.')]
    public int $unitPriceNet;

    #[Assert\NotNull]
    public VatRate $vatRate;

    #[Assert\Callback]
    public function validateLineNetAmount(ExecutionContextInterface $executionContext): void
    {
        if (!isset($this->quantity, $this->unitPriceNet)) {
            return;
        }

        if (preg_match(self::QUANTITY_PATTERN, $this->quantity) !== 1) {
            return;
        }

        if (new Number($this->quantity)->mul($this->unitPriceNet) <= self::MAXIMUM_UNIT_PRICE_NET) {
            return;
        }

        $executionContext->buildViolation('Částka za řádek je příliš velká.')
            ->atPath('unitPriceNet')
            ->addViolation();
    }
}
