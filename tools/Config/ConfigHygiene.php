<?php

declare(strict_types=1);

namespace Tools\Config;

use Closure;
use Composer\Autoload\ClassLoader;
use ReflectionClass;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

use function array_any;
use function array_diff;
use function array_filter;
use function array_flip;
use function array_keys;
use function array_merge;
use function array_unique;
use function dirname;
use function file_exists;
use function file_get_contents;
use function implode;
use function is_array;
use function preg_match_all;
use function preg_replace;
use function sort;
use function str_ends_with;
use function strrchr;
use function strval;
use function substr;

final class ConfigHygiene
{
    private const array ENV_FILES = ['.env', '.env.dev', '.env.test', '.env.prod'];

    private const array SKIPPED_DIRECTORIES = ['vendor', 'var', 'node_modules', '.git', 'tests/Tools/Config/Fixture'];

    private const array READER_PATTERNS = [
        '/%env\(([^)%]+)\)%/',
        '/\benv:\s*[\'"]([^\'"]+)[\'"]/',
        '/\$_(?:SERVER|ENV)\[[\'"]([A-Z][A-Z0-9_]*)[\'"]\]/',
        '/\bgetenv\([\'"]([A-Z][A-Z0-9_]*)[\'"]\)/',
        '/\$\{([A-Z][A-Z0-9_]*)/',
        '/\$\(([A-Z][A-Z0-9_]*)\)/',
    ];

    /**
     * Outside PHP a bare $NAME is a shell or Dockerfile reference, inside it would be a variable.
     */
    private const string BARE_SHELL_REFERENCE = '/\$([A-Z][A-Z0-9_]*)\b/';

    /**
     * A file of empty keys still sets something when a key is a feature switch: framework.esi: ~ turns ESI on.
     *
     * @param Closure(string): bool $isFeatureToggle takes a dotted key path such as framework.esi
     *
     * @return list<string>
     */
    public static function findConfigFilesSettingNothing(string $projectDir, Closure $isFeatureToggle): array
    {
        $filesSettingNothing = [];

        foreach (Finder::create()->files()->in($projectDir . '/config')->name('*.yaml')->sortByName() as $file) {
            $configuration = Yaml::parseFile($file->getPathname());
            if (self::containsSetValue($configuration)) {
                continue;
            }

            if (array_any(self::findEmptyKeyPaths($configuration, []), $isFeatureToggle)) {
                continue;
            }

            $filesSettingNothing[] = 'config/' . $file->getRelativePathname();
        }

        return $filesSettingNothing;
    }

    /**
     * @return list<string>
     */
    public static function findUnreadEnvVars(string $projectDir): array
    {
        $definedNames = [];

        foreach (self::ENV_FILES as $envFile) {
            $contents = self::readFile($projectDir . '/' . $envFile);
            preg_match_all('/^([A-Z][A-Z0-9_]*)=/m', $contents, $matches);
            $definedNames = array_merge($definedNames, $matches[1]);
        }

        $unreadNames = array_diff(
            array_unique($definedNames),
            self::findReadEnvVars($projectDir),
            self::findEnvVarsReadByVendorCode(),
        );
        sort($unreadNames);

        return $unreadNames;
    }

    /**
     * @return list<string>
     */
    private static function findReadEnvVars(string $projectDir): array
    {
        // any file may read an env var, a compose file under ci/ as much as a service in src/
        $readerFiles = Finder::create()->files()->in($projectDir)->ignoreDotFiles(false)
            ->exclude(self::SKIPPED_DIRECTORIES)->notName('*.md');
        $readNames = [];

        foreach ($readerFiles as $readerFile) {
            $contents = self::readFile($readerFile->getPathname());
            $readerPatterns = str_ends_with($readerFile->getFilename(), '.php')
                ? self::READER_PATTERNS
                : [...self::READER_PATTERNS, self::BARE_SHELL_REFERENCE];

            foreach ($readerPatterns as $readerPattern) {
                preg_match_all($readerPattern, $contents, $matches);

                foreach ($matches[1] as $reference) {
                    // resolve:NAME and default::NAME carry processors before the name
                    $processorSeparator = strrchr($reference, ':');
                    $readNames[] = $processorSeparator === false ? $reference : substr($processorSeparator, 1);
                }
            }
        }

        return array_keys(array_flip($readNames));
    }

    /**
     * APP_ENV, KERNEL_CLASS or APP_RUNTIME are read from $_SERVER, $_ENV or getenv() by installed code,
     * with nothing in the project naming them.
     *
     * @return list<string>
     */
    private static function findEnvVarsReadByVendorCode(): array
    {
        $classLoaderFile = new ReflectionClass(ClassLoader::class)->getFileName();
        if ($classLoaderFile === false) {
            return [];
        }

        $vendorFiles = Finder::create()->files()->name('*.php')->in(dirname($classLoaderFile, 2));
        $readNames = [];

        foreach ($vendorFiles as $vendorFile) {
            preg_match_all(
                '/\$_(?:SERVER|ENV)\[[\'"]([A-Z][A-Z0-9_]*)[\'"]\]|getenv\([\'"]([A-Z][A-Z0-9_]*)[\'"]\)/',
                self::readFile($vendorFile->getPathname()),
                $matches,
            );
            $readNames = array_merge($readNames, $matches[1], $matches[2]);
        }

        return array_keys(array_flip(array_filter($readNames, static fn (string $name): bool => $name !== '')));
    }

    private static function containsSetValue(mixed $node): bool
    {
        if (!is_array($node)) {
            return $node !== null;
        }

        return array_any($node, self::containsSetValue(...));
    }

    /**
     * @param list<string> $keyPath
     *
     * @return list<string>
     */
    private static function findEmptyKeyPaths(mixed $node, array $keyPath): array
    {
        if ($node === null || $node === []) {
            return $keyPath === [] ? [] : [preg_replace('/^when@\w+\./', '', implode('.', $keyPath)) ?? ''];
        }

        if (!is_array($node)) {
            return [];
        }

        $emptyKeyPaths = [];
        foreach ($node as $key => $child) {
            $emptyKeyPaths = [...$emptyKeyPaths, ...self::findEmptyKeyPaths($child, [...$keyPath, strval($key)])];
        }

        return $emptyKeyPaths;
    }

    private static function readFile(string $path): string
    {
        $contents = file_exists($path) ? file_get_contents($path) : false;

        return $contents === false ? '' : $contents;
    }
}
