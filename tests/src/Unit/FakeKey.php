<?php

declare(strict_types=1);

namespace Drupal\Tests\photos_sh\Unit;

/**
 * Stands in for a Key module key.
 */
class FakeKey {

  public function __construct(protected ?string $value) {
  }

  /**
   * Gets the key's value.
   */
  public function getKeyValue(): ?string {
    return $this->value;
  }

}
