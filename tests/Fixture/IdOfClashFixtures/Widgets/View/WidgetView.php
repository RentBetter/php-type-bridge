<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\IdOfClashFixtures\Widgets\View;

/**
 * Points at an id union published in another module, under a name this module declares too.
 *
 * @phpstan-type GammaStatusId = 'legacy'
 * @phpstan-type _self = array{
 *     status: id-of<GammaStatus>,
 *     legacy: GammaStatusId,
 * }
 */
final class WidgetView
{
}
