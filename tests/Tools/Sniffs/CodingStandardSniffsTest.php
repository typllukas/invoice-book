<?php

declare(strict_types=1);

namespace App\Tests\Tools\Sniffs;

use CodingStandard\Sniffs\Formatting\RequireShortChainOnOneLineSniff;
use CodingStandard\Sniffs\Formatting\SerializationGroupsLayoutSniff;
use CodingStandard\Sniffs\Regex\RequireDModifierOnEndAnchoredRegexSniff;
use PHP_CodeSniffer\Config;
use PHP_CodeSniffer\Files\LocalFile;
use PHP_CodeSniffer\Ruleset;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function define;
use function defined;

/**
 * @see RequireDModifierOnEndAnchoredRegexSniff
 * @see RequireShortChainOnOneLineSniff
 * @see SerializationGroupsLayoutSniff
 */
final class CodingStandardSniffsTest extends TestCase
{
    private const string FIXTURE_DIR = __DIR__ . '/Fixture/';

    /**
     * @return array<string, array{string, string}>
     */
    public static function provideFixableFixtures(): array
    {
        return [
            'serialization groups layout' => [
                'SerializationGroupsLayouts.php',
                'CodingStandard.Formatting.SerializationGroupsLayout',
            ],
            'short chain' => ['ShortChains.php', 'CodingStandard.Formatting.RequireShortChainOnOneLine'],
            'D modifier' => ['RegexPatterns.php', 'CodingStandard.Regex.RequireDModifierOnEndAnchoredRegex'],
        ];
    }

    private function processFixture(string $fixtureFileName, string $sniffCode): LocalFile
    {
        // bin/phpcs loads its own classes and defines these before anything else runs
        require_once __DIR__ . '/../../../vendor/squizlabs/php_codesniffer/autoload.php';
        require_once __DIR__ . '/../../../vendor/squizlabs/php_codesniffer/src/Util/Tokens.php';
        if (!defined('PHP_CODESNIFFER_VERBOSITY')) {
            define('PHP_CODESNIFFER_VERBOSITY', 0);
            define('PHP_CODESNIFFER_CBF', false);
        }

        $config = new Config([
            '--standard=' . __DIR__ . '/../../../tools/CodingStandard',
            '--sniffs=' . $sniffCode,
        ], false);

        $fixtureFile = new LocalFile(self::FIXTURE_DIR . $fixtureFileName, new Ruleset($config), $config);
        $fixtureFile->process();

        return $fixtureFile;
    }

    /**
     * @return list<array{int, string}>
     */
    private function sniffFixture(string $fixtureFileName, string $sniffCode): array
    {
        /** @var array<int, array<int, list<array{source: string}>>> $errorsByLine */
        $errorsByLine = $this->processFixture($fixtureFileName, $sniffCode)->getErrors();

        $reportedErrors = [];
        foreach ($errorsByLine as $line => $errorsByColumn) {
            foreach ($errorsByColumn as $errors) {
                foreach ($errors as $error) {
                    $reportedErrors[] = [$line, $error['source']];
                }
            }
        }

        return $reportedErrors;
    }

    public function testSerializationGroupsSplitOnlyFromTwoGroupsUp(): void
    {
        self::assertSame(
            [
                [11, 'CodingStandard.Formatting.SerializationGroupsLayout.GroupsOnOneLine'],
                [14, 'CodingStandard.Formatting.SerializationGroupsLayout.SingleGroupSplit'],
                [35, 'CodingStandard.Formatting.SerializationGroupsLayout.GroupsOnOneLine'],
            ],
            $this->sniffFixture(
                'SerializationGroupsLayouts.php',
                'CodingStandard.Formatting.SerializationGroupsLayout',
            ),
        );
    }

    public function testAChainOnTwoLinesIsReportedOnlyWhenItFitsOnOne(): void
    {
        self::assertSame(
            [
                [12, 'CodingStandard.Formatting.RequireShortChainOnOneLine.MultiLine'],
                [14, 'CodingStandard.Formatting.RequireShortChainOnOneLine.MultiLine'],
                [26, 'CodingStandard.Formatting.RequireShortChainOnOneLine.MultiLine'],
            ],
            $this->sniffFixture('ShortChains.php', 'CodingStandard.Formatting.RequireShortChainOnOneLine'),
        );
    }

    public function testAnEndAnchoredRegexWithoutTheDModifierIsReported(): void
    {
        self::assertSame(
            [
                [11, 'CodingStandard.Regex.RequireDModifierOnEndAnchoredRegex.Missing'],
                [15, 'CodingStandard.Regex.RequireDModifierOnEndAnchoredRegex.Missing'],
                [21, 'CodingStandard.Regex.RequireDModifierOnEndAnchoredRegex.Missing'],
            ],
            $this->sniffFixture('RegexPatterns.php', 'CodingStandard.Regex.RequireDModifierOnEndAnchoredRegex'),
        );
    }

    #[DataProvider('provideFixableFixtures')]
    public function testTheFixerRewritesTheFixtureIntoItsFixedVersion(string $fixtureFileName, string $sniffCode): void
    {
        $fixtureFile = $this->processFixture($fixtureFileName, $sniffCode);
        $fixtureFile->fixer->fixFile();

        self::assertStringEqualsFile(
            self::FIXTURE_DIR . $fixtureFileName . '.fixed',
            $fixtureFile->fixer->getContents(),
        );
    }
}
