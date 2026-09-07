<?php

declare(strict_types=1);

namespace App\Entity;

use App\DTO\VatSummaryLine;
use App\Enum\InvoiceStatus;
use App\Enum\VatRate;
use App\Helper\MixedToInteger;
use App\Helper\MixedToString;
use App\Helper\VatCalculator;
use App\Repository\InvoiceRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: InvoiceRepository::class)]
final class Invoice
{
    public const string CALENDAR_TIME_ZONE = 'Europe/Prague';

    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME)]
    private Ulid $id;

    #[ORM\Column(length: 11, unique: true, nullable: true)]
    private ?string $number = null;

    #[ORM\Column(length: 16)]
    private InvoiceStatus $status = InvoiceStatus::DRAFT;

    #[ORM\Column(length: 255)]
    private string $clientName;

    #[ORM\Column(length: 255)]
    private string $clientAddress;

    #[ORM\Column(length: 16)]
    private string $clientCompanyId;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $clientVatId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $supplierName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $supplierAddress = null;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $supplierCompanyId = null;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $supplierVatId = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $supplierBankAccount = null;

    /**
     * Required on a company's invoice by § 435 občanského zákoníku.
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $supplierRegisterEntry = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $issuedAt = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private DateTimeImmutable $dueAt;

    /**
     * The tax point, DUZP on a Czech document.
     */
    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private DateTimeImmutable $taxPointAt;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $paidAt = null;

    /**
     * Snapshot of the VAT recapitulation frozen by issue(), null on a draft.
     *
     * @var list<array<string, mixed>>|null
     */
    #[ORM\Column(nullable: true)]
    private ?array $issuedVatSummary = null;

    #[ORM\Column(length: 1000, nullable: true)]
    private ?string $note = null;

    /**
     * @var Collection<int, InvoiceItem>
     */
    #[ORM\OneToMany(
        targetEntity: InvoiceItem::class,
        mappedBy: 'invoice',
        cascade: ['persist'],
        orphanRemoval: true,
    )]
    private Collection $items;

    public function __construct(?Ulid $id = null)
    {
        $this->id = $id ?? new Ulid();
        $this->items = new ArrayCollection();
    }

    public function getId(): Ulid
    {
        return $this->id;
    }

    public function getNumber(): ?string
    {
        return $this->number;
    }

    public function getStatus(): InvoiceStatus
    {
        return $this->status;
    }

    public function getClientName(): string
    {
        return $this->clientName;
    }

    public function setClientName(string $clientName): self
    {
        $this->clientName = $clientName;

        return $this;
    }

    public function getClientAddress(): string
    {
        return $this->clientAddress;
    }

    public function setClientAddress(string $clientAddress): self
    {
        $this->clientAddress = $clientAddress;

        return $this;
    }

    public function getClientCompanyId(): string
    {
        return $this->clientCompanyId;
    }

    public function setClientCompanyId(string $clientCompanyId): self
    {
        $this->clientCompanyId = $clientCompanyId;

        return $this;
    }

    public function getClientVatId(): ?string
    {
        return $this->clientVatId;
    }

    public function setClientVatId(?string $clientVatId): self
    {
        $this->clientVatId = $clientVatId;

        return $this;
    }

    public function getSupplierName(): ?string
    {
        return $this->supplierName;
    }

    public function getSupplierAddress(): ?string
    {
        return $this->supplierAddress;
    }

    public function getSupplierCompanyId(): ?string
    {
        return $this->supplierCompanyId;
    }

    public function getSupplierVatId(): ?string
    {
        return $this->supplierVatId;
    }

    public function getSupplierBankAccount(): ?string
    {
        return $this->supplierBankAccount;
    }

    public function getSupplierRegisterEntry(): ?string
    {
        return $this->supplierRegisterEntry;
    }

    public function getIssuedAt(): ?DateTimeImmutable
    {
        return $this->issuedAt;
    }

    public function getDueAt(): DateTimeImmutable
    {
        return $this->dueAt;
    }

    public function setDueAt(DateTimeImmutable $dueAt): self
    {
        $this->dueAt = $dueAt;

        return $this;
    }

    public function getTaxPointAt(): DateTimeImmutable
    {
        return $this->taxPointAt;
    }

    public function setTaxPointAt(DateTimeImmutable $taxPointAt): self
    {
        $this->taxPointAt = $taxPointAt;

        return $this;
    }

    public function getPaidAt(): ?DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): self
    {
        $this->note = $note;

        return $this;
    }

    /**
     * @return list<InvoiceItem>
     */
    public function getItems(): array
    {
        return $this->items->getValues();
    }

    public function addItem(InvoiceItem $item): self
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setInvoice($this);
        }

        return $this;
    }

    public function removeItem(InvoiceItem $item): self
    {
        $this->items->removeElement($item);

        return $this;
    }

    /**
     * @return list<VatSummaryLine>
     */
    public function getVatSummary(): array
    {
        if ($this->issuedVatSummary === null) {
            return VatCalculator::summarize($this->items);
        }

        $summaryLines = [];

        foreach ($this->issuedVatSummary as $summaryLine) {
            $summaryLines[] = new VatSummaryLine(
                VatRate::from(MixedToString::transformStrict($summaryLine['vatRate'])),
                MixedToInteger::transformStrict($summaryLine['netAmount']),
                MixedToInteger::transformStrict($summaryLine['vatAmount']),
                MixedToInteger::transformStrict($summaryLine['grossAmount']),
            );
        }

        return $summaryLines;
    }

    public function getTotalNetAmount(): int
    {
        return VatCalculator::calculateTotalNetAmount($this->getVatSummary());
    }

    public function getTotalVatAmount(): int
    {
        return VatCalculator::calculateTotalVatAmount($this->getVatSummary());
    }

    public function getTotalGrossAmount(): int
    {
        return VatCalculator::calculateTotalGrossAmount($this->getVatSummary());
    }
}
