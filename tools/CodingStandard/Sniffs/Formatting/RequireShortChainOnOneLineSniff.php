<?php

declare(strict_types=1);

namespace CodingStandard\Sniffs\Formatting;

use Override;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

use function str_contains;

use const T_COMMENT;
use const T_NULLSAFE_OBJECT_OPERATOR;
use const T_OBJECT_OPERATOR;
use const T_WHITESPACE;

/**
 * Only a chain written on two lines, so query builders keep their shape.
 *
 * @phpstan-type Token array{code: int|string, content: string, line: int, column: int, length: int}
 */
final class RequireShortChainOnOneLineSniff implements Sniff
{
    public int $lineLimit = 120;

    /**
     * @return array<int, int|string>
     */
    #[Override]
    public function register(): array
    {
        return [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR];
    }

    #[Override]
    public function process(File $phpcsFile, int $linkTokenIndex): void
    {
        /** @var array<int, Token> $tokens */
        $tokens = $phpcsFile->getTokens();

        $tokenBeforeLink = $phpcsFile->findPrevious(T_WHITESPACE, $linkTokenIndex - 1, null, true);
        if ($tokenBeforeLink === false) {
            return;
        }

        // a line comment keeps the line break inside its own token, so the chain has no one line form
        if ($tokens[$tokenBeforeLink]['code'] === T_COMMENT) {
            return;
        }

        $linkLine = $tokens[$linkTokenIndex]['line'];
        if ($tokens[$tokenBeforeLink]['line'] !== $linkLine - 1) {
            return;
        }

        $statementStart = $phpcsFile->findStartOfStatement($linkTokenIndex);
        $statementEnd = $phpcsFile->findEndOfStatement($linkTokenIndex);
        if ($tokens[$statementStart]['line'] !== $linkLine - 1 || $tokens[$statementEnd]['line'] !== $linkLine) {
            return;
        }

        $lastTokenOnLinkLine = $statementEnd;
        while (isset($tokens[$lastTokenOnLinkLine + 1]) && $tokens[$lastTokenOnLinkLine + 1]['line'] === $linkLine) {
            $lastTokenOnLinkLine++;
        }

        if ($this->measureJoinedLength($tokens, $statementStart, $lastTokenOnLinkLine) > $this->lineLimit) {
            return;
        }

        $fix = $phpcsFile->addFixableError('The chain fits on one line.', $linkTokenIndex, 'MultiLine');
        if (!$fix) {
            return;
        }

        $phpcsFile->fixer->beginChangeset();
        for ($tokenIndex = $tokenBeforeLink + 1; $tokenIndex < $linkTokenIndex; $tokenIndex++) {
            $phpcsFile->fixer->replaceToken($tokenIndex, '');
        }

        $phpcsFile->fixer->endChangeset();
    }

    /**
     * @param array<int, Token> $tokens
     */
    private function measureJoinedLength(array $tokens, int $statementStart, int $lastTokenOnLinkLine): int
    {
        $joinedLength = $tokens[$statementStart]['column'] - 1;
        for ($tokenIndex = $statementStart; $tokenIndex <= $lastTokenOnLinkLine; $tokenIndex++) {
            // PHPCS splits whitespace at the line end, the indentation is its own token at column 1
            $isBreak = str_contains($tokens[$tokenIndex]['content'], "\n") || $tokens[$tokenIndex]['column'] === 1;
            if ($tokens[$tokenIndex]['code'] === T_WHITESPACE && $isBreak) {
                continue;
            }

            $joinedLength += $tokens[$tokenIndex]['length'];
        }

        return $joinedLength;
    }
}
