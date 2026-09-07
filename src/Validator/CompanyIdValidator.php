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
use function strval;

final class CompanyIdValidator extends ConstraintValidator
{
    #[Override]
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof CompanyId) {
            throw new UnexpectedTypeException($constraint, CompanyId::class);
        }

        if ($value === null || $value === '') {
            return;
        }

        if (!is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        if (preg_match('/^\d{8}$/D', $value) !== 1) {
            $this->context->buildViolation($constraint->formatMessage)->addViolation();

            return;
        }

        if (self::hasValidCheckDigit($value)) {
            return;
        }

        $this->context->buildViolation($constraint->checkDigitMessage)->addViolation();
    }

    /**
     * An IČO guards against typos: its eighth digit follows from the first seven (weights 8 to 2, mod 11).
     */
    public static function hasValidCheckDigit(string $companyId): bool
    {
        $weightedSum = 0;
        for ($digitIndex = 0; $digitIndex < 7; $digitIndex++) {
            $weightedSum += intval($companyId[$digitIndex]) * (8 - $digitIndex);
        }

        return $companyId[7] === strval((11 - $weightedSum % 11) % 10);
    }
}
