<?php

declare(strict_types=1);

namespace App\Tests\Tools\Rector\Fixture;

use ApiPlatform\Doctrine\Common\State\PersistProcessor;
use ApiPlatform\Doctrine\Common\State\RemoveProcessor;
use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/samples/{id}/approve',
            processor: PersistProcessor::class,
            input: false,
        ),
        new Get(
            normalizationContext: [
                'groups' => ['sample:read'],
            ],
            provider: ItemProvider::class,
            // a path of its own
            uriTemplate: '/samples/{id}/detail',
        ),
        new Delete(
            extraProperties: ['sample' => true],
            stateless: true,
            processor: RemoveProcessor::class,
        ),
    ],
    uriTemplate: '/samples',
    shortName: 'Sample',
)]
final class ApiPlatformArguments
{
    #[ApiProperty(description: 'Sample', security: 'is_granted("ROLE_USER")')]
    public string $sample = '';

    public function buildResourceOutsideAttribute(): ApiResource
    {
        return new ApiResource(normalizationContext: ['groups' => ['sample:read']], security: 'is_granted("ROLE_USER")');
    }
}

?>
-----
<?php

declare(strict_types=1);

namespace App\Tests\Tools\Rector\Fixture;

use ApiPlatform\Doctrine\Common\State\PersistProcessor;
use ApiPlatform\Doctrine\Common\State\RemoveProcessor;
use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;

#[ApiResource(
    shortName: 'Sample',
    uriTemplate: '/samples',
    operations: [
        new Post(
            uriTemplate: '/samples/{id}/approve',
            processor: PersistProcessor::class,
            input: false,
        ),
        new Get(
            // a path of its own
            uriTemplate: '/samples/{id}/detail',
            provider: ItemProvider::class,
            normalizationContext: [
                'groups' => ['sample:read'],
            ],
        ),
        new Delete(
            processor: RemoveProcessor::class,
            extraProperties: ['sample' => true],
            stateless: true,
        ),
    ],
)]
final class ApiPlatformArguments
{
    #[ApiProperty(description: 'Sample', security: 'is_granted("ROLE_USER")')]
    public string $sample = '';

    public function buildResourceOutsideAttribute(): ApiResource
    {
        return new ApiResource(normalizationContext: ['groups' => ['sample:read']], security: 'is_granted("ROLE_USER")');
    }
}

?>
