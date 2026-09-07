<?php

declare(strict_types=1);

namespace App\Tests;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use Override;
use Symfony\Contracts\HttpClient\ResponseInterface;

use function array_column;

abstract class CustomApiTestCase extends ApiTestCase
{
    use EntityGettersTrait;

    /** The client reuses the test's kernel; left null, API Platform raises a deprecation and the suite fails. */
    #[Override]
    protected static ?bool $alwaysBootKernel = false;

    /**
     * @return list<string>
     */
    protected static function getCollectionMemberValues(ResponseInterface $collectionResponse, string $key): array
    {
        $members = $collectionResponse->toArray()['member'];
        self::assertIsArray($members);
        $values = [];

        foreach (array_column($members, $key) as $value) {
            self::assertIsString($value);
            $values[] = $value;
        }

        return $values;
    }
}
