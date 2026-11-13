<?php

declare(strict_types=1);

namespace Drupal\Tests\photos_sh\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\SchemaCheckTestTrait;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;

/**
 * Base for tests that run with a mocked HTTP client.
 *
 * Drupal's http_client is replaced by a Guzzle client whose handler answers
 * from a queue and records the requests, so the whole stack (effect, library,
 * transport) runs and only the network is faked.
 */
abstract class PhotosShKernelTestBase extends KernelTestBase {

  use SchemaCheckTestTrait;

  public const API_KEY = 'kernel-test-key-0123456789';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'file', 'image', 'photos_sh'];

  /**
   * The queued answers.
   */
  protected static MockHandler $mock;

  /**
   * Guzzle's history: one entry per request made.
   *
   * @var array<int, array<string, mixed>>
   */
  protected static array $history = [];

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    self::$mock = new MockHandler();
    self::$history = [];
    TestLogger::$records = [];
    $container->register('http_client', Client::class)
      ->setFactory([static::class, 'createHttpClient']);
    $container->register('photos_sh_test.logger', TestLogger::class)->addTag('logger');
  }

  /**
   * Builds the mocked client.
   */
  public static function createHttpClient(): Client {
    $stack = HandlerStack::create(self::$mock);
    $stack->push(Middleware::history(self::$history));
    return new Client(['handler' => $stack]);
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system', 'image', 'photos_sh']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->config('photos_sh.settings')
      ->set('api_key', self::API_KEY)
      ->set('base_url', 'http://engine.test/api')
      ->save();
  }

  /**
   * Queues a response.
   *
   * @param int $status
   *   The HTTP status.
   * @param string $body
   *   The body.
   * @param array<string, string> $headers
   *   Response headers.
   */
  protected function queue(int $status, string $body = '', array $headers = []): void {
    self::$mock->append(new Response($status, $headers, $body));
  }

  /**
   * Queues a JSON error as the API sends it.
   *
   * @param int $status
   *   The HTTP status.
   * @param string $code
   *   The API's error code.
   * @param string $message
   *   The API's message.
   */
  protected function queueError(int $status, string $code, string $message): void {
    $this->queue($status, json_encode(['error' => ['code' => $code, 'message' => $message]]), ['Content-Type' => 'application/json']);
  }

  /**
   * Queues an image answer with the headers the API sets.
   *
   * @param string $bytes
   *   The encoded image.
   * @param string $mime
   *   Its MIME type.
   * @param int $width
   *   Its width, for X-Image-Width.
   * @param int $height
   *   Its height, for X-Image-Height.
   */
  protected function queueImage(string $bytes, string $mime, int $width, int $height): void {
    $this->queue(200, $bytes, [
      'Content-Type' => $mime,
      'X-Image-Width' => (string) $width,
      'X-Image-Height' => (string) $height,
    ]);
  }

  /**
   * Makes the encoded bytes of a test image.
   *
   * @param int $width
   *   Width in pixels.
   * @param int $height
   *   Height in pixels.
   * @param string $type
   *   One of png, jpg or webp.
   * @param bool $transparent
   *   Whether the image is fully transparent.
   *
   * @return string
   *   The encoded image.
   */
  protected function imageBytes(int $width, int $height, string $type = 'png', bool $transparent = FALSE): string {
    $image = imagecreatetruecolor($width, $height);
    imagealphablending($image, FALSE);
    imagesavealpha($image, TRUE);
    imagefill($image, 0, 0, $transparent ? imagecolorallocatealpha($image, 0, 0, 0, 127) : imagecolorallocate($image, 200, 60, 60));
    ob_start();
    match ($type) {
      'png' => imagepng($image),
      'jpg' => imagejpeg($image),
      'webp' => imagewebp($image),
      default => throw new \InvalidArgumentException('Unknown image type ' . $type),
    };
    return (string) ob_get_clean();
  }

  /**
   * The requests made so far, as Guzzle recorded them.
   *
   * @return \Psr\Http\Message\RequestInterface[]
   *   The requests.
   */
  protected function requests(): array {
    return array_map(static fn(array $entry) => $entry['request'], self::$history);
  }

  /**
   * The image a recorded request uploaded.
   *
   * @param int $index
   *   Which request, from 0.
   *
   * @return array{0: int, 1: int, 2: string}
   *   Width, height and the file name the part was sent with.
   */
  protected function sentImage(int $index): array {
    $body = (string) $this->requests()[$index]->getBody();
    preg_match('/filename="([^"]+)"/', $body, $name);
    $start = strpos($body, "\r\n\r\n") + 4;
    $end = strrpos($body, "\r\n--");
    $info = getimagesizefromstring(substr($body, $start, $end - $start));
    return [$info[0], $info[1], $name[1]];
  }

  /**
   * Messages logged to the module's channel.
   *
   * @return string[]
   *   Messages.
   */
  protected function photosLog(): array {
    return array_column(array_filter(TestLogger::$records, static fn(array $r) => $r['channel'] === 'photos_sh'), 'message');
  }

}
