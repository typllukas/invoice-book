<?php

declare(strict_types=1);

namespace App\Tests\Tools\Sniffs\Fixture;

use Symfony\Component\Serializer\Attribute\Groups;

final class SerializationGroupsLayouts
{
    #[Groups(['sample:read', 'sample:write'])]
    public string $twoGroupsOnOneLine = '';

    #[Groups([
        'sample:read',
    ])]
    public string $oneGroupSplit = '';

    #[Groups(['sample:read'])]
    public string $oneGroupOnOneLine = '';

    #[Groups([
        'sample:read',
        'sample:write',
    ])]
    public string $twoGroupsSplit = '';

    #[\Symfony\Component\Validator\Constraints\Choice(['sample:read', 'sample:write'])]
    public string $otherAttribute = '';

    #[Groups([])]
    public string $noGroup = '';

    public function __construct(
        #[Groups(['sample:read', 'sample:write'])]
        public string $promotedTwoGroupsOnOneLine,
    ) {
    }
}
