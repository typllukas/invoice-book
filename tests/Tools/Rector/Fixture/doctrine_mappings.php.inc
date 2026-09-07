<?php

declare(strict_types=1);

namespace App\Tests\Tools\Rector\Fixture;

use App\Enum\VatRate;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

class DoctrineMappings
{
    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    /* a comment between the attributes does not hide the Id */
    private Ulid $id;

    #[ORM\Column(length: 9, unique: true, nullable: true)]
    private ?string $number = null;

    #[ORM\Column(type: 'string', length: 16)]
    private string $code;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $paidAt = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private DateTimeImmutable $dueAt;

    #[ORM\Column(type: Types::TEXT)]
    private string $note;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?DoctrineMappings $parent = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private DoctrineMappings $owner;

    #[ORM\Column(type: Types::STRING, length: 2, enumType: VatRate::class)]
    private VatRate $vatRate;

    #[ORM\Column(length: 2, enumType: VatRate::class)]
    private string $legacyVatRate;

    #[ORM\Column(length: 2, enumType: VatRate::class)]
    private VatRate $defaultVatRate;

    #[ORM\Column(type: Types::ASCII_STRING, length: 2, enumType: VatRate::class)]
    private VatRate $asciiVatRate;

    #[ORM\Column(type: Types::ENUM, enumType: VatRate::class)]
    private VatRate $enumVatRate;

    #[ORM\Column(type: Types::STRING, length: 16)]
    private string|null $reference = null;

    #[ORM\ManyToOne, ORM\JoinColumn]
    private ?DoctrineMappings $manager = null;
}

?>
-----
<?php

declare(strict_types=1);

namespace App\Tests\Tools\Rector\Fixture;

use App\Enum\VatRate;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

class DoctrineMappings
{
    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME)]
    /* a comment between the attributes does not hide the Id */
    private Ulid $id;

    #[ORM\Column(length: 9, unique: true, nullable: true)]
    private ?string $number = null;

    #[ORM\Column(length: 16)]
    private string $code;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $paidAt = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private DateTimeImmutable $dueAt;

    #[ORM\Column(type: Types::TEXT)]
    private string $note;

    #[ORM\ManyToOne]
    private ?DoctrineMappings $parent = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private DoctrineMappings $owner;

    #[ORM\Column(length: 2)]
    private VatRate $vatRate;

    #[ORM\Column(length: 2, enumType: VatRate::class)]
    private string $legacyVatRate;

    #[ORM\Column(length: 2)]
    private VatRate $defaultVatRate;

    #[ORM\Column(type: Types::ASCII_STRING, length: 2, enumType: VatRate::class)]
    private VatRate $asciiVatRate;

    #[ORM\Column(type: Types::ENUM)]
    private VatRate $enumVatRate;

    #[ORM\Column(length: 16)]
    private string|null $reference = null;

    #[ORM\ManyToOne]
    private ?DoctrineMappings $manager = null;
}

?>
