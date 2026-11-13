<?php

declare(strict_types=1);

namespace Drupal\Tests\photos_sh\Unit;

use Drupal\photos_sh\Http\GuzzleTransport;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request as PsrRequest;
use GuzzleHttp\Psr7\Response as PsrResponse;
use PhotosSh\Exception\ConnectionException;
use PhotosSh\Exception\TimeoutException;
use PhotosSh\Transport\Request;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the Guzzle transport.
 *
 * @coversDefaultClass \Drupal\photos_sh\Http\GuzzleTransport
 * @group photos_sh
 */
#[Group('photos_sh')]
class GuzzleTransportTest extends UnitTestCase {

  /**
   * Guzzle's request history.
   *
   * @var array<int, array<string, mixed>>
   */
  protected array $history = [];

  /**
   * Builds a transport whose client answers from the queue.
   */
  protected function transport(mixed ...$queue): GuzzleTransport {
    $stack = HandlerStack::create(new MockHandler($queue));
    $stack->push(Middleware::history($this->history));
    return new GuzzleTransport(new Client(['handler' => $stack]));
  }

  /**
   * A response comes back with its status, headers and body.
   */
  public function testReturnsStatusHeadersAndBody(): void {
    $headers = [
      'Content-Type' => 'image/png',
      'X-Image-Width' => '10',
      'Set-Cookie' => ['a=1', 'b=2'],
    ];
    $transport = $this->transport(new PsrResponse(200, $headers, 'PNGDATA'));
    $response = $transport->send(new Request(
      'POST',
      'http://engine.test/api/v1/upscale?scale=2',
      ['Authorization' => 'Bearer k', 'Content-Type' => 'multipart/form-data; boundary=x'],
      'BODY',
      30.0,
    ));

    $this->assertSame(200, $response->status);
    $this->assertSame('PNGDATA', $response->body);
    $this->assertSame('image/png', $response->header('content-type'));
    $this->assertSame('10', $response->header('X-Image-Width'));
    $this->assertSame('a=1, b=2', $response->header('Set-Cookie'));
  }

  /**
   * The request goes out as built.
   *
   * Errors and redirects are left to the library.
   */
  public function testSendsTheRequestAsGiven(): void {
    $transport = $this->transport(new PsrResponse(200));
    $headers = ['Authorization' => 'Bearer k', 'User-Agent' => 'photos-sh-php/1.0.0'];
    $transport->send(new Request('POST', 'http://engine.test/api/v1/upscale?scale=2', $headers, 'BODY', 12.5));

    $this->assertCount(1, $this->history);
    $request = $this->history[0]['request'];
    $options = $this->history[0]['options'];
    $this->assertSame('POST', $request->getMethod());
    $this->assertSame(
      'http://engine.test/api/v1/upscale?scale=2',
      (string) $request->getUri(),
    );
    $this->assertSame('Bearer k', $request->getHeaderLine('Authorization'));
    $this->assertSame('photos-sh-php/1.0.0', $request->getHeaderLine('User-Agent'));
    $this->assertSame('BODY', (string) $request->getBody());
    $this->assertFalse($options['http_errors']);
    $this->assertFalse($options['allow_redirects']);
    $this->assertEquals(12.5, $options['timeout']);
  }

  /**
   * Error statuses are responses, not exceptions.
   */
  public function testErrorStatusesAreReturned(): void {
    $payment = '{"error":{"code":"payment_required","message":"No credits"}}';
    $transport = $this->transport(
      new PsrResponse(402, [], $payment),
      new PsrResponse(503, ['Retry-After' => '3'], '{}'),
      new PsrResponse(302, ['Location' => 'http://elsewhere.test/']),
    );
    $request = new Request('GET', 'http://engine.test/api/v1/account');

    $this->assertSame(402, $transport->send($request)->status);
    $busy = $transport->send($request);
    $this->assertSame(503, $busy->status);
    $this->assertSame('3', $busy->header('Retry-After'));
    $this->assertSame(302, $transport->send($request)->status);
  }

  /**
   * A failed connection becomes the library's ConnectionException.
   */
  public function testConnectionFailure(): void {
    $transport = $this->transport(new ConnectException(
      'cURL error 7: refused',
      new PsrRequest('GET', 'http://engine.test/'),
    ));

    $this->expectException(ConnectionException::class);
    $this->expectExceptionMessage('GET http://engine.test/api/v1/account failed');
    $transport->send(new Request('GET', 'http://engine.test/api/v1/account'));
  }

  /**
   * A timeout becomes a TimeoutException.
   */
  public function testTimeout(): void {
    $transport = $this->transport(new ConnectException(
      'cURL error 28: Operation timed out',
      new PsrRequest('GET', 'http://engine.test/'),
      NULL,
      ['errno' => 28],
    ));

    $this->expectException(TimeoutException::class);
    $transport->send(new Request('GET', 'http://engine.test/api/v1/account'));
  }

}
