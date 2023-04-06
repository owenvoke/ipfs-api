<?php

namespace Ipfs\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Utils;
use GuzzleHttp\RequestOptions;
use Ipfs\Contracts\IpfsClient;
use Ipfs\IpfsException;
use Psr\Http\Message\StreamInterface;

class HttpClient implements IpfsClient
{
    private string $host;

    private int $port;

    private Client $http;

    private Request $request;

    private array $requestOptions;

    public function __construct(string $host, int $port, array $options = [])
    {
        $this->host = $host;
        $this->port = $port;
        $this->http = new Client(array_merge([
            RequestOptions::HTTP_ERRORS => false,
            RequestOptions::TIMEOUT => 10.0,
            RequestOptions::HEADERS => [
                'Accept' => 'application/json',
            ],
        ], $options));
        $this->requestOptions = [];
    }

    public function request(string $url, array $payload = []): IpfsClient
    {
        $path = $this->buildQuery($url, $payload);

        $this->request = new Request('POST', "{$this->host}:{$this->port}/api/v0/{$path}");

        return $this;
    }

    /**
     * @SuppressWarnings(PHPMD.StaticAccess)
     *
     * @return array|resource|StreamInterface|null
     */
    public function send(array $options = [])
    {
        $response = $this->http->send($this->request, array_merge_recursive($this->requestOptions, $options));
        $this->requestOptions = [];

        if ($response->getStatusCode() !== 200) {
            throw IpfsException::makeFromResponse($response);
        }

        if (in_array(RequestOptions::STREAM, array_keys($options)) && $options[RequestOptions::STREAM] === true) {
            return $response->getBody()->detach();
        }

        $contents = $response->getBody()->getContents();
        if (empty($contents)) {
            return [];
        }

        if (current($response->getHeader('Content-Type')) === 'application/json') {
            // @phpstan-ignore-next-line
            return json_decode($contents, true) ?? $this->parse($contents);
        }

        return ['Content' => $contents];
    }

    /**
     * @SuppressWarnings(PHPMD.ElseExpression)
     * @SuppressWarnings(PHPMD.StaticAccess)
     *
     * @param string|resource|null $content
     */
    public function attach(string $path, ?string $name = null, $content = null, ?string $mime = null): IpfsClient
    {
        $attached = [];

        if (! is_null($content)) {
            $attached[] = [
                'name' => 'file',
                'contents' => is_string($content) ? Utils::streamFor($content) : $content,
                'headers' => [
                    'Content-Type' => $mime ?? 'application/octet-stream',
                ],
                'filename' => $name ?? $path,
            ];
        } else {
            if (is_file($path)) {
                /** @var \finfo $mimeFlag */
                $mimeFlag = finfo_open(FILEINFO_MIME_TYPE);
                $attached[] = [
                    'name' => 'file',
                    'contents' => Utils::tryFopen($path, 'r'),
                    'headers' => [
                        'Content-Type' => $mime ?? finfo_file($mimeFlag, $path),
                    ],
                    'filename' => $name ?? basename($path),
                ];
            } else {
                $attached[] = [
                    'name' => 'file',
                    'contents' => 'directory',
                    'headers' => [
                        'Content-Type' => 'application/x-directory',
                    ],
                    'filename' => $path,
                ];
            }
        }

        // @phpstan-ignore-next-line
        if (! empty($attached)) {
            $this->requestOptions = array_merge_recursive($this->requestOptions, [
                RequestOptions::MULTIPART => $attached,
            ]);
        }

        return $this;
    }

    protected function parse(string $str): array
    {
        // RPC-style API
        // @see https://docs.ipfs.io/reference/http/api/#getting-started
        $parsed = preg_replace('/}/', '},', $str);
        if (! is_string($parsed)) {
            throw new IpfsException(preg_last_error_msg());
        }

        // @phpstan-ignore-next-line
        return json_decode('['.substr(trim($parsed), 0, -1).']', true);
    }

    protected function buildQuery(string $path, array $data = []): string
    {
        if (! empty($data)) {
            $arrays = [];
            $params = array_map([$this, 'formatValue'], array_filter($data, function ($datum, $key) use (&$arrays) {
                if (is_array($datum)) {
                    $arrays[] = $key;
                }

                return ! is_null($datum) && ! is_array($datum);
            }, ARRAY_FILTER_USE_BOTH));

            $query = '';
            foreach ($arrays as $key) {
                $values = (isset($data[$key])) ? implode('&', array_map(function ($arg) use ($key) {
                    // @phpstan-ignore-next-line
                    return sprintf('%1$s=%2$s', $key, $this->formatValue($arg));
                }, $data[$key])) : '';
                $query .= (! empty($values)) ? $values.'&' : '';
            }

            $query .= (! empty($params)) ? http_build_query($params) : '';

            if (! empty($query)) {
                $path .= '?'.trim($query, '&');
            }
        }

        return $path;
    }

    /**
     * @param mixed $value
     *
     * @return mixed
     */
    protected function formatValue($value)
    {
        if (is_bool($value)) {
            $value = $value ? 'true' : 'false';
        }

        return $value;
    }
}
