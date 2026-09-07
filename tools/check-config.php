<?php

declare(strict_types=1);

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\SingleCommandApplication;
use Tools\Config\ConfigHygiene;

require dirname(__DIR__) . '/vendor/autoload.php';

$checkConfig = static function (InputInterface $input, OutputInterface $output): int {
    $projectDir = dirname(__DIR__);
    $problems = [];

    $isFeatureToggle = static function (string $keyPath) use ($projectDir): bool {
        [$extension, $path] = explode('.', $keyPath, 2) + [1 => ''];
        if ($path === '') {
            return false;
        }

        $reference = shell_exec(sprintf(
            'php %s/bin/console config:dump-reference %s %s 2>/dev/null',
            $projectDir,
            escapeshellarg($extension),
            escapeshellarg($path),
        ));

        return is_string($reference) && preg_match('/^\S+:\s*\n\s{4}enabled:/m', $reference) === 1;
    };

    foreach (ConfigHygiene::findConfigFilesSettingNothing($projectDir, $isFeatureToggle) as $emptyFile) {
        $problems[] = sprintf('%s sets nothing; delete it', $emptyFile);
    }

    foreach (ConfigHygiene::findUnreadEnvVars($projectDir) as $unreadName) {
        $problems[] = sprintf('%s is defined in a .env file and read by nothing', $unreadName);
    }

    // debug:container --deprecations exits 0 whatever it finds, so the count is read from its JSON
    $deprecationCommand = sprintf('php %s/bin/console debug:container --deprecations --format=json', $projectDir);
    $deprecationJson = shell_exec($deprecationCommand . ' 2>/dev/null');
    $deprecationReport = json_decode(is_string($deprecationJson) ? $deprecationJson : '', true);
    $deprecationCount = is_array($deprecationReport) ? ($deprecationReport['remainingCount'] ?? null) : null;

    if (!is_int($deprecationCount)) {
        $problems[] = 'debug:container --deprecations gave no readable count';
    } elseif ($deprecationCount > 0) {
        $problems[] = sprintf(
            '%d deprecations in the container build, run debug:container --deprecations',
            $deprecationCount,
        );
    }

    $output->writeln($problems === [] ? 'Config check: OK' : $problems);

    return $problems === [] ? Command::SUCCESS : Command::FAILURE;
};

new SingleCommandApplication()->setName('Config check')->setCode($checkConfig)->run();
