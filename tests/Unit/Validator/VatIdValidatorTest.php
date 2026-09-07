<?php

declare(strict_types=1);

namespace App\Tests\Unit\Validator;

use App\Validator\VatId;
use App\Validator\VatIdValidator;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @see VatIdValidator
 *
 * @extends ConstraintValidatorTestCase<VatIdValidator>
 */
final class VatIdValidatorTest extends ConstraintValidatorTestCase
{
    #[Override]
    protected function createValidator(): VatIdValidator
    {
        return new VatIdValidator();
    }

    /**
     * @return array<string, array{?string}>
     */
    public static function provideValuesLeftToOtherConstraints(): array
    {
        return [
            'null' => [null],
            'an empty string' => [''],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function provideValidVatIds(): array
    {
        return [
            'a company, CZ and a valid company id' => ['CZ12345687'],
            'a person, ten digits divisible by 11' => ['CZ0000000011'],
            'a person, first nine digits leaving 10 and a final 0' => ['CZ0000000100'],
            'nine digits, left unchecked' => ['CZ000000001'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function provideVatIdsWithAWrongCheckDigit(): array
    {
        return [
            'a company id with the wrong check digit' => ['CZ12345678'],
            'ten digits not divisible by 11' => ['CZ0000000012'],
            'first nine digits leaving 10 and a final 1' => ['CZ0000000101'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function provideMalformedVatIds(): array
    {
        return [
            'no country code' => ['12345687'],
            'another country code' => ['SK12345687'],
            'seven digits' => ['CZ1234567'],
            'eleven digits' => ['CZ00000000011'],
            'a valid VAT id with a trailing newline' => ["CZ12345687\n"],
        ];
    }

    #[DataProvider('provideValuesLeftToOtherConstraints')]
    public function testNullAndAnEmptyStringAreLeftToOtherConstraints(?string $vatId): void
    {
        $this->validator->validate($vatId, new VatId());

        $this->assertNoViolation();
    }

    #[DataProvider('provideValidVatIds')]
    public function testAValidVatIdPasses(string $vatId): void
    {
        $this->validator->validate($vatId, new VatId());

        $this->assertNoViolation();
    }

    #[DataProvider('provideVatIdsWithAWrongCheckDigit')]
    public function testAWrongCheckDigitIsReported(string $vatId): void
    {
        $constraint = new VatId();

        $this->validator->validate($vatId, $constraint);

        $this->buildViolation($constraint->checkDigitMessage)->assertRaised();
    }

    #[DataProvider('provideMalformedVatIds')]
    public function testAMalformedVatIdIsReportedAsFormatOnly(string $vatId): void
    {
        $constraint = new VatId();

        $this->validator->validate($vatId, $constraint);

        $this->buildViolation($constraint->formatMessage)->assertRaised();
    }
}
