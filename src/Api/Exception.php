<?php

/**
 * This file is part of the contentful/contentful-core package.
 *
 * @copyright 2015-2022 Contentful GmbH
 * @license   MIT
 */

declare(strict_types=1);

namespace Contentful\Core\Api;

use GuzzleHttp\Exception\RequestException;
use function GuzzleHttp\json_decode as guzzle_json_decode;
use GuzzleHttp\Psr7\Message as GuzzleMessage;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * An Exception is thrown when an errors occurs while communicating with the API.
 */
class Exception extends \RuntimeException implements \Serializable
{
    /**
     * @var RequestException|null
     */
    private $previous;

    /**
     * @var string|null
     */
    private $requestId;

    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var ResponseInterface|null
     */
    private $response;

    /**
     * Exception constructor.
     */
    public function __construct(RequestException $previous, string $message = '')
    {
        $this->previous = $previous;
        $this->request = $previous->getRequest();
        $this->response = $previous->getResponse();

        if ($this->response) {
            $this->requestId = $this->response->getHeaderLine('X-Contentful-Request-Id');
        }

        if ('' === $message) {
            $message = self::createExceptionMessage($previous, $this->response);
        }

        parent::__construct($message, 0, $previous);
    }

    /**
     * {@inheritdoc}
     */
    public function serialize(): string
    {
        return \serialize([
            'message' => $this->message,
            'code' => $this->code,
            'file' => $this->message,
            'line' => $this->line,
            'requestId' => $this->requestId,
            'request' => GuzzleMessage::toString($this->request),
            'response' => $this->response ? GuzzleMessage::toString($this->response) : null,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize($serialized)
    {
        $data = \unserialize($serialized);

        $this->message = $data['message'];
        $this->code = $data['code'];
        $this->file = $data['file'];
        $this->line = $data['line'];
        $this->requestId = $data['requestId'];
        $this->request = GuzzleMessage::parseRequest($data['request']);
        $this->response = $data['response'] ? GuzzleMessage::parseResponse($data['response']) : null;
    }

    private static function createExceptionMessage(
        RequestException $previous,
        ResponseInterface $response = null
    ): string {
        if (!$response) {
            return $previous->getMessage();
        }

        try {
            $result = guzzle_json_decode((string) $response->getBody(), true);
            if (\is_array($result) && isset($result['message'])) {
                return $result['message'];
            }
        } catch (\InvalidArgumentException $e) {
            return $previous->getMessage();
        }

        return $previous->getMessage();
    }

    /**
     * Get the request that caused the exception.
     */
    public function getRequest(): RequestInterface
    {
        return $this->request;
    }

    /**
     * Get the associated response.
     *
     * @return ResponseInterface|null
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * Check if a response was received.
     */
    public function hasResponse(): bool
    {
        return null !== $this->response;
    }

    /**
     * @return string|null
     */
    public function getRequestId()
    {
        return $this->requestId;
    }
}
