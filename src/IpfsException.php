<?php

namespace Ipfs;

use Psr\Http\Message\ResponseInterface;

final class IpfsException extends \RuntimeException
{
    public static function makeFromResponse(ResponseInterface $response): self
    {
        $contents = $response->getBody()->getContents();

        /** @var array{Message: string, Code: int} $error */
        $error = (! empty($contents))
            ? json_decode($contents, true) ?? ['Message' => $contents, 'Code' => $response->getStatusCode()]
            : ['Message' => $response->getReasonPhrase(), 'Code' => $response->getStatusCode()]
        ;

        return new self($error['Message'], $error['Code']);
    }
}
