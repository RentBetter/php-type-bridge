<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Generics;

use PHPUnit\Framework\TestCase;

/**
 * Proves the ContractFormType generics fix: with `@extends FormTypeInterface<TData>` on
 * ContractFormType, a concrete form can declare `@extends AbstractType<TData>` and
 * `@implements ContractFormType<TData>` and analyse clean under phpstan/phpstan-symfony
 * at level max — no generics conflict and, crucially, no @phpstan-ignore.
 */
final class ContractFormTypeGenericsTest extends TestCase
{
    public function test_the_generics_fixture_carries_no_ignore_pragma(): void
    {
        $source = file_get_contents(__DIR__ . '/Fixtures/WidgetType.php');
        self::assertNotFalse($source);
        self::assertStringNotContainsString('@phpstan-ignore', $source);
    }

    public function test_phpstan_is_clean_on_a_dual_generic_contract_form(): void
    {
        $phpstan = \dirname(__DIR__, 2) . '/vendor/bin/phpstan';
        $config = __DIR__ . '/phpstan-generics.neon';

        $command = \sprintf(
            'php -d memory_limit=512M %s analyse --no-progress --error-format=raw --configuration=%s 2>&1',
            escapeshellarg($phpstan),
            escapeshellarg($config),
        );

        exec($command, $output, $exitCode);
        $report = implode("\n", $output);

        self::assertSame(0, $exitCode, 'phpstan reported errors on the generics fixture:' . "\n" . $report);
    }
}
