<?php

declare(strict_types=1);

namespace Drupal\Tests\photos_sh\Unit;

use Drupal\photos_sh\ApiErrorMessage;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use PhotosSh\Exception\AccountSuspendedException;
use PhotosSh\Exception\ApiException;
use PhotosSh\Exception\BusyException;
use PhotosSh\Exception\ConnectionException;
use PhotosSh\Exception\InvalidInputException;
use PhotosSh\Exception\PaymentRequiredException;
use PhotosSh\Exception\PhotosException;
use PhotosSh\Exception\TimeoutException;
use PhotosSh\Exception\UnauthorizedException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the plain-words error messages.
 *
 * @coversDefaultClass \Drupal\photos_sh\ApiErrorMessage
 * @group photos_sh
 */
#[Group('photos_sh')]
class ApiErrorMessageTest extends UnitTestCase {

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
   * Each failure gets its own wording.
   *
   * @dataProvider provideFailures
   */
  #[DataProvider('provideFailures')]
  public function testMessages(\Throwable $e, string $expected): void {
    $this->assertStringContainsString($expected, (string) ApiErrorMessage::forException($e));
  }

  /**
   * Failures and the words expected.
   */
  public static function provideFailures(): \Generator {
    yield 'key refused' => [new UnauthorizedException(401, 'unauthorized', 'bad key'), 'refused the API key'];
    yield 'no credits' => [new PaymentRequiredException(402, 'payment_required', 'none'), 'out of credits'];
    yield 'suspended' => [new AccountSuspendedException(403, 'account_suspended', 'x'), 'suspended'];
    yield 'busy' => [new BusyException(503, 'busy', 'x', 2), 'busy'];
    yield 'timeout' => [new TimeoutException('t'), 'did not answer in time'];
    yield 'unreachable' => [new ConnectionException('c'), 'could not be reached'];
    yield 'no key' => [new InvalidInputException('x'), 'No Photos.sh API key'];
    yield 'other api error' => [new ApiException(422, 'unprocessable', 'No subject found'), 'No subject found'];
    yield 'anything else' => [new PhotosException('x'), 'request failed'];
  }

}
