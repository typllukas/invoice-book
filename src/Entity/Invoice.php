<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\BackedEnumFilter;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\ExistsFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\DTO\InvoiceInput;
use App\DTO\Supplier;
use App\DTO\VatSummaryLine;
use App\Enum\InvoiceStatus;
use App\Enum\VatRate;
use App\Helper\MixedToInteger;
use App\Helper\MixedToString;
use App\Helper\VatCalculator;
use App\Repository\InvoiceRepository;
use App\State\Processor\Invoice\CreateInvoiceProcessor;
use App\State\Processor\Invoice\DeleteInvoiceProcessor;
use App\State\Processor\Invoice\IssueInvoiceProcessor;
use App\State\Processor\Invoice\MarkInvoicePaidProcessor;
use App\State\Processor\Invoice\UpdateInvoiceProcessor;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use LogicException;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Uid\Ulid;

use function preg_replace;

#[ApiResource(
    operations: [
        new GetCollection(
            normalizationContext: [
                'groups' => [
                    'invoice:read',
                    'invoice:operation:get_collection',
                ],
            ],
        ),
        new Get(
            normalizationContext: [
                'groups' => [
                    'invoice:read',
                    'invoice:operation:get',
                ],
            ],
        ),
        new Post(
            processor: CreateInvoiceProcessor::class,
            input: InvoiceInput::class,
            normalizationContext: [
                'groups' => [
                    'invoice:read',
                    'invoice:operation:get',
                ],
            ],
        ),
        new Post(
            uriTemplate: '/invoices/{id}/issue',
            processor: IssueInvoiceProcessor::class,
            input: false,
            normalizationContext: [
                'groups' => [
                    'invoice:read',
                    'invoice:operation:get',
                ],
            ],
            status: 200,
            openapi: new OpenApiOperation(summary: 'Issue the invoice.'),
        ),
        new Post(
            uriTemplate: '/invoices/{id}/mark_paid',
            processor: MarkInvoicePaidProcessor::class,
            input: false,
            normalizationContext: [
                'groups' => [
                    'invoice:read',
                    'invoice:operation:get',
                ],
            ],
            status: 200,
            openapi: new OpenApiOperation(summary: 'Mark the invoice as paid.'),
        ),
        new Put(
            processor: UpdateInvoiceProcessor::class,
            input: InvoiceInput::class,
            normalizationContext: [
                'groups' => [
                    'invoice:read',
                    'invoice:operation:get',
                ],
            ],
        ),
        new Delete(
            processor: DeleteInvoiceProcessor::class,
        ),
    ],
)]
#[ApiFilter(OrderFilter::class, properties: ['number', 'clientName', 'issuedAt', 'dueAt', 'paidAt', 'status'])]
#[ApiFilter(SearchFilter::class, properties: ['clientName' => SearchFilter::STRATEGY_PARTIAL])]
#[ApiFilter(BackedEnumFilter::class, properties: ['status'])]
#[ApiFilter(ExistsFilter::class, properties: ['paidAt'])]
#[ApiFilter(DateFilter::class, properties: ['issuedAt', 'dueAt'])]
#[ORM\Entity(repositoryClass: InvoiceRepository::class)]
final class Invoice
{
    public const string CALENDAR_TIME_ZONE = 'Europe/Prague';

    #[Groups(['invoice:read'])]
    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME)]
    private Ulid $id;

    #[Groups(['invoice:read'])]
    #[ORM\Column(length: 11, unique: true, nullable: true)]
    private ?string $number = null;

    #[Groups(['invoice:read'])]
    #[ORM\Column(length: 16)]
    private InvoiceStatus $status = InvoiceStatus::DRAFT;

    #[Groups(['invoice:read'])]
    #[ORM\Column(length: 255)]
    private string $clientName;

    #[Groups(['invoice:operation:get'])]
    #[ORM\Column(length: 255)]
    private string $clientAddress;

    #[Groups(['invoice:operation:get'])]
    #[ORM\Column(length: 16)]
    private string $clientCompanyId;

    #[Groups(['invoice:operation:get'])]
    #[ORM\Column(length: 16, nullable: true)]
    private ?string $clientVatId = null;

    #[Groups(['invoice:operation:get'])]
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $supplierName = null;

    #[Groups(['invoice:operation:get'])]
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $supplierAddress = null;

    #[Groups(['invoice:operation:get'])]
    #[ORM\Column(length: 16, nullable: true)]
    private ?string $supplierCompanyId = null;

    #[Groups(['invoice:operation:get'])]
    #[ORM\Column(length: 16, nullable: true)]
    private ?string $supplierVatId = null;

    #[Groups(['invoice:operation:get'])]
    #[ORM\Column(length: 32, nullable: true)]
    private ?string $supplierBankAccount = null;

    /**
     * Required on a company's invoice by § 435 občanského zákoníku.
     */
    #[Groups(['invoice:operation:get'])]
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $supplierRegisterEntry = null;

    #[Groups(['invoice:read'])]
    #[Context([DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]
    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $issuedAt = null;

    #[Groups(['invoice:read'])]
    #[Context([DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]
    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private DateTimeImmutable $dueAt;

    /**
     * The tax point, DUZP on a Czech document.
     */
    #[Groups(['invoice:operation:get'])]
    #[Context([DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]
    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private DateTimeImmutable $taxPointAt;

    #[Groups(['invoice:read'])]
    #[Context([DateTimeNormalizer::TIMEZONE_KEY => 'UTC'])]
    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $paidAt = null;

    /**
     * Snapshot of the VAT recapitulation frozen by issue(), null on a draft.
     *
     * @var list<array<string, mixed>>|null
     */
    #[ORM\Column(nullable: true)]
    private ?array $issuedVatSummary = null;

    #[Groups(['invoice:operation:get'])]
    #[ORM\Column(length: 1000, nullable: true)]
    private ?string $note = null;

    /**
     * @var Collection<int, InvoiceItem>
     */
    #[Groups(['invoice:read'])]
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
     * Supplier, line amounts and VAT recapitulation are copied, so a later change to the supplier or to the VAT
     * calculation cannot alter an issued document.
     */
    public function issue(string $number, DateTimeImmutable $issuedAt, Supplier $supplier): void
    {
        if ($this->status !== InvoiceStatus::DRAFT) {
            throw new LogicException('Only a draft invoice can be issued.');
        }

        $this->number = $number;
        $this->issuedAt = $issuedAt;
        $this->status = InvoiceStatus::ISSUED;
        $this->supplierName = $supplier->name;
        $this->supplierAddress = $supplier->address;
        $this->supplierCompanyId = $supplier->companyId;
        $this->supplierVatId = $supplier->vatId;
        $this->supplierBankAccount = $supplier->bankAccount;
        $this->supplierRegisterEntry = $supplier->registerEntry;

        foreach ($this->items as $item) {
            $item->freezeNetAmount();
        }

        $this->issuedVatSummary = [];

        foreach (VatCalculator::summarize($this->items) as $summaryLine) {
            $this->issuedVatSummary[] = [
                'vatRate' => $summaryLine->vatRate->value,
                'netAmount' => $summaryLine->netAmount,
                'vatAmount' => $summaryLine->vatAmount,
                'grossAmount' => $summaryLine->grossAmount,
            ];
        }
    }

    public function markPaid(DateTimeImmutable $paidAt): void
    {
        if ($this->status !== InvoiceStatus::ISSUED || $this->paidAt instanceof DateTimeImmutable) {
            throw new LogicException('Only an issued unpaid invoice can be marked paid.');
        }

        $this->paidAt = $paidAt;
    }

    #[Groups(['invoice:operation:get'])]
    public function getVariableSymbol(): ?string
    {
        return $this->number === null ? null : preg_replace('/\D/', '', $this->number);
    }

    /**
     * @return list<VatSummaryLine>
     */
    #[Groups(['invoice:operation:get'])]
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

    #[Groups(['invoice:operation:get'])]
    public function getTotalNetAmount(): int
    {
        return VatCalculator::calculateTotalNetAmount($this->getVatSummary());
    }

    #[Groups(['invoice:operation:get'])]
    public function getTotalVatAmount(): int
    {
        return VatCalculator::calculateTotalVatAmount($this->getVatSummary());
    }

    #[Groups(['invoice:read'])]
    public function getTotalGrossAmount(): int
    {
        return VatCalculator::calculateTotalGrossAmount($this->getVatSummary());
    }
}
