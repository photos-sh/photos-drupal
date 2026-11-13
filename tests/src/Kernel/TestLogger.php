<?php

declare(strict_types=1);

namespace Drupal\Tests\photos_sh\Kernel;

use Drupal\Core\Logger\RfcLoggerTrait;
use Psr\Log\LoggerInterface;

/**
 * Collects log messages for assertions.
 */
class TestLogger implements LoggerInterface {

  use RfcLoggerTrait;

  /**
   * The messages logged, with placeholders filled in.
   *
   * @var array<int, array{level: mixed, channel: string, message: string}>
   */
  public static array $records = [];

  /**
   * {@inheritdoc}
   */
  public function log($level, string|\Stringable $message, array $context = []): void {
    $replace = [];
    foreach ($context as $key => $value) {
      if (is_scalar($value) || $value instanceof \Stringable) {
        $replace[$key] = (string) $value;
      }
    }
    self::$records[] = [
      'level' => $level,
      'channel' => (string) ($context['channel'] ?? ''),
      'message' => strtr((string) $message, $replace),
    ];
  }

}
