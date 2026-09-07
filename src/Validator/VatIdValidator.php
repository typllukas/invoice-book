<?php

declare(strict_types=1);

namespace App\Validator;

use Override;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

use function intval;
use function is_string;
use function preg_match;
use function strlen;
use function substr;

final class VatIdValidator extends ConstraintValidator
{
    #[Override]
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof VatId) {
            throw new UnexpectedTypeException($constraint, VatId::class);
        }

        if ($value === null || $value === '') {
            return;
        }

        if (!is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        if (preg_match('/^CZ\d{8,10}$/D', $value) !== 1) {
            $this->context->buildViolation($constraint->formatMessage)->addViolation();

            return;
        }

        $digits = substr($value, 2);
        $hasValidCheckDigit = match (strlen($digits)) {
            8 => CompanyIdValidator::hasValidCheckDigit($digits),
            10 => $this->isValidBirthNumber($digits),
            // nine digits: a birth number from before 1954, or a VAT group (CZ699...) with an unpublished check digit
            default => true,
        };
        if ($hasValidCheckDigit) {
            return;
        }

        $this->context->buildViolation($constraint->checkDigitMessage)->addViolation();
    }

    /**
     * Numbers issued until 1985 whose first nine digits leave 10 end in 0.
     */
    private function isValidBirthNumber(string $birthNumber): bool
    {
        return intval($birthNumber) % 11 === 0
            || (intval(substr($birthNumber, 0, 9)) % 11 === 10 && $birthNumber[9] === '0');
    }
}
