<?php

declare(strict_types=1);

namespace Drupal\photos_sh\Http;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use PhotosSh\Exception\ConnectionException;
use PhotosSh\Exception\TimeoutException;
use PhotosSh\Transport\Request;
use PhotosSh\Transport\Response;
use PhotosSh\Transport\Transport;

/**
 * Sends the Photos.sh library's requests through Drupal's HTTP client.
 *
 * Error statuses come back as responses (the library turns them into
 * exceptions); redirects are not followed and nothing is retried here.
 */
final class GuzzleTransport implements Transport {

  /**
   * The cURL "operation timed out" error number.
   */
  private const CURL_TIMEOUT = 28;

  public function __construct(private readonly ClientInterface $client) {
  }

  /**
   * {@inheritdoc}
   */
  public function send(Request $request): Response {
    $options = [
      'headers' => $request->headers,
      'http_errors' => FALSE,
      'allow_redirects' => FALSE,
      'timeout' => $request->timeout,
      'connect_timeout' => min($request->timeout, 10.0),
      // Do not wait for "100 Continue" before sending large bodies.
      'expect' => FALSE,
    ];
    if ($request->body !== NULL) {
      $options['body'] = $request->body;
    }

    try {
      $response = $this->client->request($request->method, $request->url, $options);
    }
    catch (ConnectException $e) {
      $message = sprintf('%s %s failed: %s', $request->method, $request->url, $e->getMessage());
      if (($e->getHandlerContext()['errno'] ?? NULL) === self::CURL_TIMEOUT || stripos($e->getMessage(), 'timed out') !== FALSE) {
        throw new TimeoutException($message, 0, $e);
      }
      throw new ConnectionException($message, 0, $e);
    }
    catch (GuzzleException $e) {
      throw new ConnectionException(sprintf('%s %s failed: %s', $request->method, $request->url, $e->getMessage()), 0, $e);
    }

    $headers = [];
    foreach ($response->getHeaders() as $name => $values) {
      $headers[(string) $name] = implode(', ', $values);
    }

    return new Response($response->getStatusCode(), $headers, (string) $response->getBody());
  }

}
