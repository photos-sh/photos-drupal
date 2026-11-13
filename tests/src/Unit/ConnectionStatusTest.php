<?php

declare(strict_types=1);

namespace Drupal\Tests\photos_sh\Unit;

use Drupal\photos_sh\ConnectionStatus;
use Drupal\photos_sh\PhotosClientFactory;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use PhotosSh\Photos;
use PhotosSh\Transport\Response;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the connection status line.
 *
 * @coversDefaultClass \Drupal\photos_sh\ConnectionStatus
 * @group photos_sh
 */
#[Group('photos_sh')]
class ConnectionStatusTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Translatable messages are cast to strings here.
    $container = new ContainerBuilder();
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);
  }

  /**
   * Builds a status over a fake API that answers with the given response.
   */
  protected function statusFor(?Response $response): ConnectionStatus {
    $factory = $this->createMock(PhotosClientFactory::class);
    $factory->method('isConfigured')->willReturn($response !== NULL);
    if ($response !== NULL) {
      $client = new Photos('k-12345678', 'http://engine.test/api', transport: new FakeTransport($response));
      $factory->method('get')->willReturn($client);
    }
    return new ConnectionStatus($factory);
  }

  /**
   * Without a key there is nothing to ask.
   */
  public function testNotConfigured(): void {
    $result = $this->statusFor(NULL)->check();
    $this->assertSame(ConnectionStatus::WARNING, $result['severity']);
    $this->assertStringContainsString('No API key', (string) $result['message']);
  }

  /**
   * Credits are shown.
   */
  public function testCredits(): void {
    $result = $this->statusFor(new Response(200, [], '{"account":"acme","credits":1234}'))->check();
    $this->assertSame(ConnectionStatus::OK, $result['severity']);
    $this->assertSame('Connected to account acme, 1,234 credits left.', (string) $result['message']);
  }

  /**
   * Unlimited accounts say so.
   */
  public function testUnlimited(): void {
    $result = $this->statusFor(new Response(200, [], '{"account":"sdk","unlimited":true}'))->check();
    $this->assertSame('Connected to account sdk, with unlimited credits.', (string) $result['message']);
  }

  /**
   * A refused key is an error in plain words, without the key.
   */
  public function testKeyRefused(): void {
    $result = $this->statusFor(new Response(401, [], '{"error":{"code":"unauthorized","message":"bad key"}}'))->check();
    $this->assertSame(ConnectionStatus::ERROR, $result['severity']);
    $this->assertStringContainsString('refused the API key', (string) $result['message']);
    $this->assertStringNotContainsString('k-12345678', (string) $result['message']);
    $this->assertNull($result['account']);
  }

  /**
   * Out of credits is a warning.
   */
  public function testOutOfCredits(): void {
    $result = $this->statusFor(new Response(402, [], '{"error":{"code":"payment_required","message":"x"}}'))->check();
    $this->assertSame(ConnectionStatus::WARNING, $result['severity']);
    $this->assertStringContainsString('out of credits', (string) $result['message']);
  }

}
