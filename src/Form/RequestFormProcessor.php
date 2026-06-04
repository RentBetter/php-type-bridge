<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Form;

use JsonException;
use PTGS\TypeBridge\Contract\ContractFormType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Submits HTTP request data (query string or JSON body) through Symfony forms and
 * returns the populated, validated DTO.
 *
 * Two distinct failure modes:
 *
 *  - Malformed input (bad JSON, non-object body, invalid query params) throws a
 *    Symfony BadRequestHttpException (400). Apps that want a richer 400 envelope can
 *    catch and re-shape it, or rely on their own kernel.exception handling.
 *  - JSON-body field-validation failures throw whatever the injected
 *    ValidationErrorResponseFactory produces (a ThrowableApiResponse, rendered by
 *    TypeBridgeThrowableListener), so the 422 envelope is app-specific.
 */
final readonly class RequestFormProcessor
{
    public function __construct(
        private FormFactoryInterface $formFactory,
        private ValidationErrorResponseFactory $validationErrorResponseFactory,
    ) {}

    /**
     * Submits query-string parameters through a filter form. Returns the populated DTO
     * on success; throws BadRequestHttpException on validation failure.
     *
     * @template T of object
     *
     * @param class-string<AbstractFilterType<T>> $type
     * @param T|null $data
     * @param array<string, mixed> $options
     *
     * @return T
     */
    public function processQueryForm(string $type, Request $request, ?object $data = null, array $options = []): object
    {
        $form = $this->formFactory->create($type, $data, $options);
        $form->submit($request->query->all(), clearMissing: false);

        if (!$form->isValid()) {
            $errors = new FormErrors($form);

            throw new BadRequestHttpException($errors->first('Invalid query parameters.'));
        }

        /** @var T $processedData */
        $processedData = $form->getData();

        return $processedData;
    }

    /**
     * Submits a JSON request body through a Symfony form. Returns the populated DTO on
     * success; throws the configured validation-error response on form validation failure.
     *
     * @template T of object
     *
     * @param class-string<ContractFormType<T>> $type
     * @param T|null $data
     * @param array<string, mixed> $options
     *
     * @return T
     */
    public function processForm(string $type, Request $request, ?object $data = null, array $options = []): object
    {
        $form = $this->formFactory->create($type, $data, $options);
        $blockPrefix = $form->getName();

        $json = $this->parseJsonObject($request);
        $submitted = $json[$blockPrefix] ?? [];

        if (!\is_array($submitted) || array_is_list($submitted)) {
            throw new BadRequestHttpException(\sprintf('`%s` must be an object.', $blockPrefix));
        }

        /** @var T $processedData */
        $processedData = $this->submitAndGet($form, $submitted);

        return $processedData;
    }

    /**
     * Submits a JSON request body directly through a Symfony form (no block-prefix wrapping).
     * Use for flat-body action endpoints where the root object IS the form data.
     *
     * @template T of object
     *
     * @param class-string<ContractFormType<T>> $type
     * @param T|null $data
     * @param array<string, mixed> $options
     *
     * @return T
     */
    public function processFlatForm(string $type, Request $request, ?object $data = null, array $options = []): object
    {
        $form = $this->formFactory->create($type, $data, $options);
        $submitted = $this->parseJsonObject($request);

        /** @var T $processedData */
        $processedData = $this->submitAndGet($form, $submitted);

        return $processedData;
    }

    /**
     * @template TData
     *
     * @param FormInterface<TData> $form
     * @param array<array-key, mixed> $submitted
     */
    private function submitAndGet(FormInterface $form, array $submitted): mixed
    {
        // clearMissing: false preserves initial DTO values for fields the client
        // omitted — required for partial-PUT semantics where the caller updates
        // only a subset of fields and the rest are kept from the entity. With
        // Symfony's default (true), unset fields would be reset to null, which
        // breaks `processForm(..., $request, T::fromEntity($entity))` updates.
        $form->submit($submitted, clearMissing: false);

        if (!$form->isValid()) {
            $errors = new FormErrors($form);

            throw $this->validationErrorResponseFactory->create($errors->allWithPath());
        }

        return $form->getData();
    }

    /**
     * @return array<array-key, mixed>
     */
    private function parseJsonObject(Request $request): array
    {
        $raw = trim($request->getContent());

        if ('' === $raw) {
            return [];
        }

        try {
            $json = json_decode($raw, true, 512, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new BadRequestHttpException('Malformed JSON body.');
        }

        if (!\is_array($json) || array_is_list($json)) {
            throw new BadRequestHttpException('JSON body must be an object.');
        }

        return $json;
    }
}
