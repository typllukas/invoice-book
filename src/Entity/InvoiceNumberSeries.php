<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\InvoiceNumberSeriesRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

use function sprintf;

/**
 * Locked while issuing, so two issues never get the same number. Locking the last invoice instead
 * fails in a new year - no invoice to lock yet.
 */
#[ORM\Entity(repositoryClass: InvoiceNumberSeriesRepository::class)]
final class InvoiceNumberSeries
{
    /**
     * The year and six digits are the ten the variable symbol of a Czech payment holds.
     */
    private const int HIGHEST_SEQUENCE = 999999;

    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME)]
    private Ulid $id;

    #[ORM\Column(unique: true)]
    private int $year;

    #[ORM\Column]
    private int $lastSequence = 0;

    public function __construct(?Ulid $id = null)
    {
        $this->id = $id ?? new Ulid();
    }

    public function getId(): Ulid
    {
        return $this->id;
    }

    public function getYear(): int
    {
        return $this->year;
    }

    public function setYear(int $year): self
    {
        $this->year = $year;

        return $this;
    }

    public function getLastSequence(): int
    {
        return $this->lastSequence;
    }

    public function isExhausted(): bool
    {
        return $this->lastSequence >= self::HIGHEST_SEQUENCE;
    }

    public function incrementLastSequence(): void
    {
        $this->lastSequence++;
    }

    public function formatLastNumber(): string
    {
        return sprintf('%d-%06d', $this->year, $this->lastSequence);
    }
}
