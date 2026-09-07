<?php

declare(strict_types=1);

namespace App\Tests\Unit\Validator;

use App\Validator\CompanyId;
use App\Validator\CompanyIdValidator;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @see CompanyIdValidator
 *
 * @extends ConstraintValidatorTestCase<CompanyIdValidator>
 */
final class CompanyIdValidatorTest extends ConstraintValidatorTestCase
{
    #[Override]
    protected function createValidator(): CompanyIdValidator
    {
        return new CompanyIdValidator();
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
    public static function provideValidCompanyIds(): array
    {
        return [
            'a valid company id' => ['12345687'],
            'remainder 0, whose check digit is 1' => ['00000001'],
            'remainder 1, whose check digit is 0' => ['00000060'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function provideCompanyIdsWithAWrongCheckDigit(): array
    {
        return [
            'the same digits with the wrong check digit' => ['12345678'],
            'remainder 1 with a 1 instead' => ['00000061'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function provideMalformedCompanyIds(): array
    {
        return [
            'seven digits' => ['1234567'],
            'a valid company id with a ninth digit' => ['123456870'],
            'a valid company id with a trailing newline' => ["12345687\n"],
            'letters' => ['1234567a'],
        ];
    }

    #[DataProvider('provideValuesLeftToOtherConstraints')]
    public function testNullAndAnEmptyStringAreLeftToOtherConstraints(?string $companyId): void
    {
        $this->validator->validate($companyId, new CompanyId());

        $this->assertNoViolation();
    }

    #[DataProvider('provideValidCompanyIds')]
    public function testAValidCompanyIdPasses(string $companyId): void
    {
        $this->validator->validate($companyId, new CompanyId());

        $this->assertNoViolation();
    }

    #[DataProvider('provideCompanyIdsWithAWrongCheckDigit')]
    public function testAWrongCheckDigitIsReported(string $companyId): void
    {
        $constraint = new CompanyId();

        $this->validator->validate($companyId, $constraint);

        $this->buildViolation($constraint->checkDigitMessage)->assertRaised();
    }

    #[DataProvider('provideMalformedCompanyIds')]
    public function testAMalformedCompanyIdIsReportedAsFormatOnly(string $companyId): void
    {
        $constraint = new CompanyId();

        $this->validator->validate($companyId, $constraint);

        $this->buildViolation($constraint->formatMessage)->assertRaised();
    }
}
