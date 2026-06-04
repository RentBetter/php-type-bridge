<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Form;

use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;

/**
 * Walks a submitted Symfony form tree and flattens its validation errors into the
 * generic {path, message} shape consumed by ValidationErrorResponseFactory.
 *
 * Apps that need richer per-error metadata (e.g. a symbolic constraint code) build
 * it in their own ValidationErrorResponseFactory from the {path, message} pairs.
 *
 * @template TData
 */
readonly class FormErrors
{
    /**
     * @param FormInterface<TData> $form
     */
    public function __construct(
        private FormInterface $form,
    ) {}

    /**
     * Return the message of the first error, or a fallback.
     */
    public function first(string $fallback = 'Validation failed.'): string
    {
        $all = $this->allWithPath();

        return $all[0]['message'] ?? $fallback;
    }

    /**
     * @return list<array{path: string, message: string}>
     */
    public function allWithPath(): array
    {
        $errors = [];
        $path = $this->form->getName();

        foreach ($this->form->getErrors() as $error) {
            $errors[] = ['path' => $path, 'message' => $this->formatErrorMessage($error)];
        }

        foreach ($this->form->all() as $child) {
            $childPath = '' !== $path ? $path . '.' . $child->getName() : $child->getName();
            $this->collectErrors($child, $errors, $childPath);
        }

        return $errors;
    }

    /**
     * @param FormInterface<mixed> $form
     * @param list<array{path: string, message: string}> $errors
     */
    private function collectErrors(FormInterface $form, array &$errors, string $path): void
    {
        foreach ($form->getErrors() as $error) {
            $errors[] = [
                'path' => $path,
                'message' => $this->formatErrorMessage($error),
            ];
        }

        foreach ($form->all() as $child) {
            $childPath = $path . '.' . $child->getName();
            $this->collectErrors($child, $errors, $childPath);
        }
    }

    private function formatErrorMessage(FormError $error): string
    {
        $cause = $error->getCause();
        if (\is_object($cause) && method_exists($cause, 'getParameters')) {
            /** @var array<string, string> $params */
            $params = (array) $cause->getParameters();
        } else {
            $params = [];
        }

        return strtr($error->getMessage(), $params);
    }
}
