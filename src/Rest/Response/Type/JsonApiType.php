<?php declare(strict_types=1);

namespace Shopware\Rest\Response\Type;

use Shopware\Api\Entity\Entity;
use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Write\FieldException\InvalidFieldException;
use Shopware\Rest\Exception\WriteStackHttpException;
use Shopware\Rest\Response\JsonApiResponse;
use Shopware\Rest\Response\ResponseTypeInterface;
use Shopware\Rest\RestContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Serializer\Serializer;

class JsonApiType implements ResponseTypeInterface
{
    /**
     * @var Serializer
     */
    private $serializer;

    public function __construct(Serializer $serializer)
    {
        $this->serializer = $serializer;
    }

    public function supportsContentType(string $contentType): bool
    {
        return $contentType === 'application/vnd.api+json';
    }

    /**
     * @param Entity                  $entity
     * @param string|EntityDefinition $definition
     * @param RestContext             $context
     * @param bool                    $setLocationHeader
     *
     * @return Response
     */
    public function createDetailResponse(Entity $entity, string $definition, RestContext $context, bool $setLocationHeader = false): Response
    {
        $headers = [];
        $baseUrl = $this->getBaseUrl($context);

        if ($setLocationHeader) {
            $headers['Location'] = $baseUrl . '/api/' . $this->camelCaseToSnailCase($definition::getEntityName()) . '/' . $entity->getId();
        }

        $rootNode = [
            'links' => [
                'self' => $baseUrl . $context->getRequest()->getPathInfo(),
            ],
        ];

        $response = $this->serializer->serialize(
            $entity,
            'jsonapi',
            [
                'uri' => $baseUrl . '/api',
                'data' => $rootNode,
                'definition' => $definition,
                'basic' => false,
            ]
        );

        return new JsonApiResponse($response, JsonApiResponse::HTTP_OK, $headers, true);
    }

    public function createListingResponse(SearchResultInterface $searchResult, string $definition, RestContext $context): Response
    {
        $baseUrl = $this->getBaseUrl($context);

        $uri = $baseUrl . $context->getRequest()->getPathInfo();

        $rootNode = [
            'links' => $this->createPaginationLinks($searchResult, $uri, $context->getRequest()->query->all()),
        ];

        $rootNode['links']['self'] = $context->getRequest()->getUri();

        if ($searchResult->getCriteria()->fetchCount()) {
            $rootNode['meta'] = [
                'total' => $searchResult->getTotal(),
            ];
        }

        $response = $this->serializer->serialize(
            $searchResult,
            'jsonapi',
            [
                'uri' => $baseUrl . '/api',
                'data' => $rootNode,
                'definition' => $definition,
                'basic' => true,
            ]
        );

        return new JsonApiResponse($response, JsonApiResponse::HTTP_OK, [], true);
    }

    public function createErrorResponse(Request $request, \Throwable $exception, int $statusCode = 400): Response
    {
        $errorData = [
            'errors' => $this->convertExceptionToError($exception),
        ];

        return new JsonApiResponse($errorData, $statusCode);
    }

    /**
     * @param string|EntityDefinition $definition
     * @param string                  $id
     * @param RestContext             $context
     *
     * @return Response
     */
    public function createRedirectResponse(string $definition, string $id, RestContext $context): Response
    {
        $headers = [
            'Location' => $this->getBaseUrl($context) . '/api/' . $this->camelCaseToSnailCase($definition::getEntityName()) . '/' . $id,
        ];

        return new Response(null, Response::HTTP_NO_CONTENT, $headers);
    }

    private function createPaginationLinks(SearchResultInterface $searchResult, string $uri, array $parameters): array
    {
        $limit = $searchResult->getCriteria()->getLimit() ?? 0;
        $offset = $searchResult->getCriteria()->getOffset() ?? 0;

        if ($limit <= 0) {
            return [];
        }

        $pagination = [
            'first' => $this->buildPaginationUrl(
                $uri,
                array_merge(
                    $parameters,
                    ['page' => [
                        'offset' => 0,
                        'limit' => $limit,
                    ]]
                )
            ),
            'last' => $this->buildPaginationUrl(
                $uri,
                array_merge(
                    $parameters,
                    ['page' => [
                        'offset' => ceil($searchResult->getTotal() / $limit) * $limit - $limit,
                        'limit' => $limit,
                    ]]
                )
            ),
        ];

        if ($offset - $limit > 0) {
            $pagination['prev'] = $this->buildPaginationUrl(
                $uri,
                array_merge(
                    $parameters,
                    ['page' => [
                        'offset' => $offset - $limit,
                        'limit' => $limit,
                    ]]
                )
            );
        }

        if ($offset + $limit < $searchResult->getTotal()) {
            $pagination['next'] = $this->buildPaginationUrl(
                $uri,
                array_merge(
                    $parameters,
                    ['page' => [
                        'offset' => $offset + $limit,
                        'limit' => $limit,
                    ]]
                )
            );
        }

        return $pagination;
    }

    private function buildPaginationUrl(string $uri, array $parameters): string
    {
        return $uri . '?' . http_build_query($parameters);
    }

    private function convertExceptionToError(\Throwable $exception): array
    {
        if ($exception instanceof WriteStackHttpException) {
            return $this->handleWriteStackException($exception);
        }

        $statusCode = 500;

        if ($exception instanceof HttpException) {
            $statusCode = $exception->getStatusCode();
        }

        // single exception (default)
        return [
            [
                'code' => (string) $exception->getCode(),
                'status' => (string) $statusCode,
                'title' => Response::$statusTexts[$statusCode] ?? 'unknown status',
                'detail' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ],
        ];
    }

    private function handleWriteStackException(WriteStackHttpException $exception): array
    {
        $errors = [];

        foreach ($exception->getExceptionStack()->getExceptions() as $innerException) {
            if ($innerException instanceof InvalidFieldException) {
                foreach ($innerException->getViolations() as $violation) {
                    $errors[] = [
                        'code' => (string) $exception->getCode(),
                        'status' => (string) $exception->getStatusCode(),
                        'title' => $innerException->getConcern(),
                        'detail' => $violation->getMessage(),
                        'source' => ['pointer' => $innerException->getPath()],
                        'trace' => $innerException->getTraceAsString(),
                    ];
                }

                continue;
            }

            $errors[] = [
                'code' => (string) $exception->getCode(),
                'status' => (string) $exception->getStatusCode(),
                'title' => $innerException->getConcern(),
                'detail' => $innerException->getMessage(),
                'source' => ['pointer' => $innerException->getPath()],
                'trace' => $innerException->getTraceAsString(),
            ];
        }

        return $errors;
    }

    private function getBaseUrl(RestContext $context): string
    {
        return $context->getRequest()->getSchemeAndHttpHost() . $context->getRequest()->getBasePath();
    }

    private function camelCaseToSnailCase(string $input): string
    {
        $input = str_replace('_', '-', $input);

        return ltrim(strtolower(preg_replace('/[A-Z]/', '-$0', $input)), '-');
    }
}