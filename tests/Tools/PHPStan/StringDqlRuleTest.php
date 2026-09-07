<?php

declare(strict_types=1);

namespace App\Tests\Tools\PHPStan;

use Override;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Tools\PHPStan\Rules\StringDqlRule;

/**
 * @see StringDqlRule
 *
 * @extends RuleTestCase<StringDqlRule>
 */
final class StringDqlRuleTest extends RuleTestCase
{
    #[Override]
    protected function getRule(): Rule
    {
        return new StringDqlRule();
    }

    public function testOnlyAQueryWrittenAsADqlStringIsReported(): void
    {
        $this->analyse([__DIR__ . '/Fixture/StringDqlQueries.php'], [
            [
                'createQuery() takes a DQL string; build the query with createQueryBuilder() instead.',
                14,
            ],
        ]);
    }
}
