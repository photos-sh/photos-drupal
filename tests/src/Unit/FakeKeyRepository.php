<?php

declare(strict_types=1);

namespace Drupal\Tests\photos_sh\Unit;

/**
 * Stands in for the Key module's repository, holding one key named "photos".
 */
class FakeKeyRepository {

  public function __construct(protected ?string $value) {
  }

  /**
   * Gets a key.
   */
  public function getKey(string $id): ?object {
    return $id === 'photos' ? new FakeKey($this->value) : NULL;
  }

}
