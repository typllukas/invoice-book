<?php

declare(strict_types=1);

namespace CodingStandard\Sniffs\Formatting;

use Override;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

use function count;
use function implode;
use function preg_match;
use function str_repeat;
use function trim;

use const T_ATTRIBUTE;
use const T_COMMA;
use const T_OPEN_SHORT_ARRAY;

/**
 * One group on one line, two or more one per line.
 *
 * @phpstan-type Token array{
 *     code: int|string,
 *     line: int,
 *     column: int,
 *     attribute_closer?: int,
 *     bracket_closer?: int,
 * }
 */
final class SerializationGroupsLayoutSniff implements Sniff
{
    /**
     * @return array<int, int|string>
     */
    #[Override]
    public function register(): array
    {
        return [T_ATTRIBUTE];
    }

    #[Override]
    public function process(File $phpcsFile, int $attributeOpener): void
    {
        /** @var array<int, Token> $tokens */
        $tokens = $phpcsFile->getTokens();

        $attributeCloser = $tokens[$attributeOpener]['attribute_closer'] ?? null;
        if ($attributeCloser === null) {
            return;
        }

        $attributeText = $phpcsFile->getTokensAsString($attributeOpener, $attributeCloser - $attributeOpener + 1);
        if (preg_match('{^#\[\s*\\\\?(?:\w+\\\\)*Groups\s*\(\s*\[}D', $attributeText) !== 1) {
            return;
        }

        $arrayOpener = $phpcsFile->findNext(T_OPEN_SHORT_ARRAY, $attributeOpener, $attributeCloser);
        if ($arrayOpener === false) {
            return;
        }

        $arrayCloser = $tokens[$arrayOpener]['bracket_closer'] ?? null;
        if ($arrayCloser === null) {
            return;
        }

        $groupNames = $this->collectGroupNames($phpcsFile, $tokens, $arrayOpener, $arrayCloser);
        $isSingleGroup = count($groupNames) === 1;
        $isOneLine = $tokens[$arrayOpener]['line'] === $tokens[$arrayCloser]['line'];
        if ($groupNames === [] || $isSingleGroup === $isOneLine) {
            return;
        }

        $fix = $phpcsFile->addFixableError(
            $isSingleGroup
                ? 'A single serialization group stays on the attribute line.'
                : 'Two or more serialization groups go one per line.',
            $attributeOpener,
            $isSingleGroup ? 'SingleGroupSplit' : 'GroupsOnOneLine',
        );
        if (!$fix) {
            return;
        }

        $indentation = str_repeat(' ', $tokens[$attributeOpener]['column'] - 1);
        $arrayText = $isSingleGroup
            ? '[' . $groupNames[0] . ']'
            : "[\n" . $indentation . '    ' . implode(",\n" . $indentation . '    ', $groupNames) . ",\n"
                . $indentation . ']';

        $phpcsFile->fixer->beginChangeset();
        $phpcsFile->fixer->replaceToken($arrayOpener, $arrayText);
        for ($tokenIndex = $arrayOpener + 1; $tokenIndex <= $arrayCloser; $tokenIndex++) {
            $phpcsFile->fixer->replaceToken($tokenIndex, '');
        }

        $phpcsFile->fixer->endChangeset();
    }

    /**
     * @param array<int, Token> $tokens
     *
     * @return list<string>
     */
    private function collectGroupNames(File $phpcsFile, array $tokens, int $arrayOpener, int $arrayCloser): array
    {
        $groupNames = [];
        $groupNameStart = $arrayOpener + 1;
        for ($tokenIndex = $arrayOpener + 1; $tokenIndex <= $arrayCloser; $tokenIndex++) {
            if ($tokens[$tokenIndex]['code'] !== T_COMMA && $tokenIndex !== $arrayCloser) {
                continue;
            }

            $groupName = trim($phpcsFile->getTokensAsString($groupNameStart, $tokenIndex - $groupNameStart));
            if ($groupName !== '') {
                $groupNames[] = $groupName;
            }

            $groupNameStart = $tokenIndex + 1;
        }

        return $groupNames;
    }
}
