<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Http\Include;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * The normaliser that expands a reference to each entity class.
 */
final readonly class RefRegistry
{
    /** @var list<RefNormalizer> */
    private array $normalizers;

    /**
     * @param iterable<RefNormalizer> $normalizers
     */
    public function __construct(
        #[AutowireIterator(RefNormalizer::TAG)]
        iterable $normalizers,
    ) {
        $this->normalizers = array_values([...$normalizers]);
    }

    /**
     * The normaliser for an entity class — matched with is_a(), so a Doctrine proxy finds its
     * entity's normaliser.
     *
     * @param class-string $class
     */
    public function for(string $class): ?RefNormalizer
    {
        foreach ($this->normalizers as $normalizer) {
            if (is_a($class, $normalizer::entityClass(), allow_string: true)) {
                return $normalizer;
            }
        }

        return null;
    }
}
