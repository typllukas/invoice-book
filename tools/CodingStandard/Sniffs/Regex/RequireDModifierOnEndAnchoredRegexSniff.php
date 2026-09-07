<?php

declare(strict_types=1);

namespace CodingStandard\Sniffs\Regex;

use Override;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

use function preg_match;
use function str_contains;
use function substr;

use const T_CONSTANT_ENCAPSED_STRING;

/**
 * Without D, a final $ also matches before a trailing newline, so '/^\d{8}$/' accepts "12345678\n".
 */
final class RequireDModifierOnEndAnchoredRegexSniff implements Sniff
{
    private const string END_ANCHORED_PATTERN = '{^(?:(?<delimiter>[/~#!%|@]).*(?<!\\\\)\$\k<delimiter>'
        . '|\{.*(?<!\\\\)\$\})(?<modifiers>[a-zA-Z]*)$}sD';

    /**
     * @return array<int, int|string>
     */
    #[Override]
    public function register(): array
    {
        return [T_CONSTANT_ENCAPSED_STRING];
    }

    #[Override]
    public function process(File $phpcsFile, int $stringTokenIndex): void
    {
        $literal = $phpcsFile->getTokensAsString($stringTokenIndex, 1);

        $patternMatches = [];
        if (preg_match(self::END_ANCHORED_PATTERN, substr($literal, 1, -1), $patternMatches) !== 1) {
            return;
        }

        // with m the anchor is meant to match at every line end, and D has no effect
        $modifiers = $patternMatches['modifiers'];
        if (str_contains($modifiers, 'D') || str_contains($modifiers, 'm')) {
            return;
        }

        $fix = $phpcsFile->addFixableError(
            'A final $ also matches before a trailing newline; add the D modifier.',
            $stringTokenIndex,
            'Missing',
        );
        if (!$fix) {
            return;
        }

        $phpcsFile->fixer->replaceToken($stringTokenIndex, substr($literal, 0, -1) . 'D' . substr($literal, -1));
    }
}
