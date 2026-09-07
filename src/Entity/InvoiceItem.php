<?php

declare(strict_types=1);

namespace App\Entity;

use App\DTO\InvoiceInput;
use App\DTO\InvoiceItemInput;
use App\Enum\VatRate;
use App\Helper\VatCalculator;
use BcMath\Number;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Ulid;

use function intval;
use function strval;

/**
 * Validated as InvoiceItemInput inside the InvoiceInput the Invoice API operations take.
 *
 * @see InvoiceItemInput
 * @see InvoiceInput
 */
#[ORM\Entity]
final class InvoiceItem
{
    #[Groups(['invoice:read'])]
    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME)]
    private Ulid $id;

    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false)]
    private Invoice $invoice;

    #[Groups(['invoice:read'])]
    #[ORM\Column(length: 255)]
    private string $description;

    #[Groups(['invoice:read'])]
    #[ORM\Column(precision: 10, scale: 3)]
    private Number $quantity;

    #[Groups(['invoice:read'])]
    #[ORM\Column(length: 16)]
    private string $unit;

    #[ORM\Column(precision: 12, scale: 2)]
    private Number $unitPriceNetInMajorUnits;

    #[Groups(['invoice:read'])]
    #[ORM\Column(length: 2)]
    private VatRate $vatRate;

    /**
     * Frozen by Invoice::issue() together with the VAT recapitulation, null on a draft.
     */
    #[ORM\Column(precision: 12, scale: 2, nullable: true)]
    private ?Number $issuedNetAmountInMajorUnits = null;

    public function __construct(?Ulid $id = null)
    {
        $this->id = $id ?? new Ulid();
    }

    public function getId(): Ulid
    {
        return $this->id;
    }

    public function getInvoice(): Invoice
    {
        return $this->invoice;
    }

    public function setInvoice(Invoice $invoice): self
    {
        $this->invoice = $invoice;

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getQuantity(): Number
    {
        return $this->quantity;
    }

    public function setQuantity(Number $quantity): self
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getUnit(): string
    {
        return $this->unit;
    }

    public function setUnit(string $unit): self
    {
        $this->unit = $unit;

        return $this;
    }

    #[Groups(['invoice:read'])]
    public function getUnitPriceNet(): int
    {
        return intval(strval($this->unitPriceNetInMajorUnits->mul(100)));
    }

    public function setUnitPriceNet(int $minorUnits): self
    {
        $this->unitPriceNetInMajorUnits = new Number($minorUnits)->div(100);

        return $this;
    }

    #[Groups(['invoice:read'])]
    public function getNetAmount(): int
    {
        if (!$this->issuedNetAmountInMajorUnits instanceof Number) {
            return VatCalculator::calculateLineNetAmount($this);
        }

        return intval(strval($this->issuedNetAmountInMajorUnits->mul(100)));
    }

    public function freezeNetAmount(): void
    {
        $this->issuedNetAmountInMajorUnits = new Number(VatCalculator::calculateLineNetAmount($this))->div(100);
    }

    public function getVatRate(): VatRate
    {
        return $this->vatRate;
    }

    public function setVatRate(VatRate $vatRate): self
    {
        $this->vatRate = $vatRate;

        return $this;
    }
}
