<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\IdOfFixtures\Widgets\View;

/**
 * Points at an id union published by a Discovered-mode emitter in another module.
 *
 * @phpstan-type _self = array{
 *     id: string,
 *     status: id-of<GammaStatus>,
 * }
 */
final class WidgetView
{
}
