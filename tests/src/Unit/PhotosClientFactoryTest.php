<?php

declare(strict_types=1);

namespace Drupal\Tests\photos_sh\Unit;

use Drupal\photos_sh\PhotosClientFactory;
use Drupal\Tests\UnitTestCase;
use PhotosSh\Exception\InvalidInputException;
use PhotosSh\Transport\Response;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests where the API key and settings come from.
 *
 * @coversDefaultClass \Drupal\photos_sh\PhotosClientFactory
 * @group photos_sh
 */
#[Group('photos_sh')]
class PhotosClientFactoryTest extends UnitTestCase {

  /**
   * The environment variable before the test.
   */
  protected string|false $originalEnv;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->originalEnv = getenv('PHOTOS_API_KEY');
    putenv('PHOTOS_API_KEY');
    unset($_SERVER['PHOTOS_API_KEY'], $_ENV['PHOTOS_API_KEY']);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    putenv($this->originalEnv === FALSE ? 'PHOTOS_API_KEY' : 'PHOTOS_API_KEY=' . $this->originalEnv);
    parent::tearDown();
  }

  /**
   * Builds a factory over the given settings.
   *
   * @param array<string, mixed> $settings
   *   The photos_sh.settings values.
   * @param object|null $key_repository
   *   A stand-in for the Key module's repository.
   */
  protected function factory(array $settings = [], ?object $key_repository = NULL): PhotosClientFactory {
    $settings += [
      'api_key' => '',
      'key_id' => '',
      'base_url' => 'https://api.photos.sh',
      'timeout' => 60,
    ];
    return new PhotosClientFactory(
      $this->getConfigFactoryStub(['photos_sh.settings' => $settings]),
      new FakeTransport(),
      $key_repository,
    );
  }

  /**
   * A key repository holding one key.
   */
  protected function keyRepository(?string $value): object {
    return new FakeKeyRepository($value);
  }

  /**
   * With no key anywhere the client cannot be built.
   */
  public function testNoKey(): void {
    $factory = $this->factory();
    $this->assertFalse($factory->isConfigured());
    $this->assertNull($factory->getApiKeySource());
    $this->expectException(InvalidInputException::class);
    $factory->get();
  }

  /**
   * The key saved in configuration is used.
   */
  public function testConfigKey(): void {
    $factory = $this->factory(['api_key' => ' cfg-key-123456 ']);
    $this->assertTrue($factory->isConfigured());
    $this->assertSame('config', $factory->getApiKeySource());
    $this->assertSame('cfg-key-123456', $factory->getApiKey());
  }

  /**
   * The environment variable wins over configuration and Key.
   */
  public function testEnvironmentWins(): void {
    putenv('PHOTOS_API_KEY=env-key-123456');
    $factory = $this->factory(['api_key' => 'cfg-key-123456', 'key_id' => 'photos'], $this->keyRepository('key-key-123456'));
    $this->assertSame('env', $factory->getApiKeySource());
    $this->assertSame('env-key-123456', $factory->getApiKey());
  }

  /**
   * A Key module key wins over the configuration key.
   */
  public function testKeyModuleKey(): void {
    $factory = $this->factory(['api_key' => 'cfg-key-123456', 'key_id' => 'photos'], $this->keyRepository('key-key-123456'));
    $this->assertSame('key', $factory->getApiKeySource());
    $this->assertSame('key-key-123456', $factory->getApiKey());
  }

  /**
   * A chosen Key that is missing or empty does not fall back to the config key.
   */
  public function testKeyModuleKeyMissing(): void {
    $this->assertFalse($this->factory(['api_key' => 'cfg-key-123456', 'key_id' => 'photos'], $this->keyRepository(''))->isConfigured());
    $this->assertFalse($this->factory(['api_key' => 'cfg-key-123456', 'key_id' => 'gone'], $this->keyRepository('x'))->isConfigured());
  }

  /**
   * Without the Key module the config key is used even if a key id is saved.
   */
  public function testKeyIdIgnoredWithoutKeyModule(): void {
    $factory = $this->factory(['api_key' => 'cfg-key-123456', 'key_id' => 'photos']);
    $this->assertSame('config', $factory->getApiKeySource());
  }

  /**
   * The request reaches the transport with the key as a bearer token.
   */
  public function testRequestShape(): void {
    $transport = new FakeTransport(new Response(200, [], '{"account":"acme"}'));
    $settings = [
      'api_key' => 'cfg-key-123456',
      'key_id' => '',
      'base_url' => 'http://127.0.0.1:8098/api/',
      'timeout' => 45,
    ];
    $config = $this->getConfigFactoryStub(['photos_sh.settings' => $settings]);
    (new PhotosClientFactory($config, $transport))->get()->account();

    $this->assertCount(1, $transport->requests);
    $request = $transport->requests[0];
    $this->assertSame('http://127.0.0.1:8098/api/v1/account', $request->url);
    $this->assertSame('Bearer cfg-key-123456', $request->headers['Authorization']);
    $this->assertEquals(45.0, $request->timeout);
  }

  /**
   * An empty base URL and timeout fall back to the defaults.
   */
  public function testDefaultBaseUrl(): void {
    $transport = new FakeTransport();
    $settings = [
      'api_key' => 'cfg-key-123456',
      'key_id' => '',
      'base_url' => '',
      'timeout' => 0,
    ];
    $config = $this->getConfigFactoryStub(['photos_sh.settings' => $settings]);
    (new PhotosClientFactory($config, $transport))->get()->account();
    $this->assertSame('https://api.photos.sh/v1/account', $transport->requests[0]->url);
    $this->assertEquals(60.0, $transport->requests[0]->timeout);
  }

}
