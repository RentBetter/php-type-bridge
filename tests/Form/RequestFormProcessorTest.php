<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Form;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Form\DefaultValidationErrorResponseFactory;
use PTGS\TypeBridge\Form\RequestFormProcessor;
use PTGS\TypeBridge\Form\ValidationErrorResponseFactory;
use PTGS\TypeBridge\Response\ValidationErrorResponse;
use PTGS\TypeBridge\Tests\Form\Fixtures\CustomValidationError;
use PTGS\TypeBridge\Tests\Form\Fixtures\CustomValidationErrorResponseFactory;
use PTGS\TypeBridge\Tests\Form\Fixtures\SampleData;
use PTGS\TypeBridge\Tests\Form\Fixtures\SampleType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\HttpFoundation\Request;

final class RequestFormProcessorTest extends TestCase
{
    public function test_it_populates_the_dto_on_a_valid_submission(): void
    {
        $processor = new RequestFormProcessor($this->formFactory(), new DefaultValidationErrorResponseFactory());

        $data = $processor->processForm(SampleType::class, $this->jsonRequest([
            'sample' => ['name' => 'Acme', 'count' => '7'],
        ]));

        self::assertInstanceOf(SampleData::class, $data);
        self::assertSame('Acme', $data->name);
        self::assertSame(7, $data->count);
    }

    public function test_it_throws_the_default_validation_response_on_invalid_submission(): void
    {
        $processor = new RequestFormProcessor($this->formFactory(), new DefaultValidationErrorResponseFactory());

        try {
            $processor->processForm(SampleType::class, $this->jsonRequest([
                'sample' => ['name' => 'Acme', 'count' => 'not-a-number'],
            ]));
            self::fail('Expected a validation error to be thrown.');
        } catch (ValidationErrorResponse $error) {
            self::assertNotEmpty($error->errors);
            // Paths are rooted at the form's block prefix, mirroring the JSON body shape.
            self::assertSame('sample.count', $error->errors[0]['path']);
            self::assertArrayHasKey('message', $error->errors[0]);
        }
    }

    public function test_the_thrown_response_is_pluggable_via_the_factory(): void
    {
        $processor = new RequestFormProcessor($this->formFactory(), new CustomValidationErrorResponseFactory());

        try {
            $processor->processForm(SampleType::class, $this->jsonRequest([
                'sample' => ['name' => 'Acme', 'count' => 'not-a-number'],
            ]));
            self::fail('Expected a validation error to be thrown.');
        } catch (CustomValidationError $error) {
            self::assertSame('CUSTOM_VALIDATION', $error->errorCode);
            self::assertSame('sample.count', $error->failures[0]['path']);
        }
    }

    public function test_default_factory_produces_the_lean_bundle_response(): void
    {
        $factory = new DefaultValidationErrorResponseFactory();

        $response = $factory->create([['path' => 'field', 'message' => 'bad']]);

        self::assertInstanceOf(ValidationErrorResponse::class, $response);
        self::assertSame([['path' => 'field', 'message' => 'bad']], $response->errors);
        self::assertSame('Validation failed', $response->getMessage());
    }

    public function test_a_custom_factory_satisfies_the_published_interface(): void
    {
        $factory = new CustomValidationErrorResponseFactory();

        self::assertInstanceOf(ValidationErrorResponseFactory::class, $factory);
        self::assertInstanceOf(CustomValidationError::class, $factory->create([]));
    }

    private function formFactory(): FormFactoryInterface
    {
        return Forms::createFormFactory();
    }

    /**
     * @param array<string, mixed> $body
     */
    private function jsonRequest(array $body): Request
    {
        return Request::create(
            uri: '/',
            method: 'POST',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($body, \JSON_THROW_ON_ERROR),
        );
    }
}
