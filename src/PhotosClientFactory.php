<?php

declare(strict_types=1);

namespace Drupal\photos_sh;

use Drupal\Core\Config\ConfigFactoryInterface;
use PhotosSh\Exception\InvalidInputException;
use PhotosSh\Photos;
use PhotosSh\Transport\Transport;

/**
 * Builds the Photos.sh API client from the site's configuration.
 *
 * The API key comes from, in this order:
 * - the PHOTOS_API_KEY environment variable;
 * - the Key module key chosen in the settings (when Key is installed);
 * - the key saved in the module's configuration (which settings.php can
 *   override with $config['photos_sh.settings']['api_key']).
 */
class PhotosClientFactory {

  public const ENV_API_KEY = 'PHOTOS_API_KEY';

  public const SOURCE_ENV = 'env';

  public const SOURCE_KEY = 'key';

  public const SOURCE_CONFIG = 'config';

  /**
   * Constructs the factory.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \PhotosSh\Transport\Transport $transport
   *   The HTTP transport the client uses.
   * @param object|null $keyRepository
   *   The Key module's \Drupal\key\KeyRepositoryInterface service, or NULL
   *   when the Key module is not installed.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected Transport $transport,
    protected ?object $keyRepository = NULL,
  ) {
  }

  /**
   * Builds the client.
   *
   * @param int $max_retries
   *   How often the client tries again, after a wait, when the API is busy or
   *   cannot be reached. Pass 0 where a wait would hold up a page request.
   *
   * @throws \PhotosSh\Exception\InvalidInputException
   *   When no API key is configured.
   */
  public function get(int $max_retries = 2): Photos {
    $key = $this->getApiKey();
    if ($key === NULL) {
      throw new InvalidInputException('No Photos.sh API key is configured.');
    }
    $config = $this->configFactory->get('photos_sh.settings');
    $base_url = trim((string) $config->get('base_url'));
    $timeout = (int) $config->get('timeout');

    return new Photos(
      apiKey: $key,
      baseUrl: $base_url !== '' ? $base_url : Photos::DEFAULT_BASE_URL,
      timeout: $timeout > 0 ? (float) $timeout : 60.0,
      maxRetries: $max_retries,
      transport: $this->transport,
    );
  }

  /**
   * Whether an API key is available.
   */
  public function isConfigured(): bool {
    return $this->getApiKey() !== NULL;
  }

  /**
   * Where the API key comes from: 'env', 'key', 'config' or NULL for none.
   */
  public function getApiKeySource(): ?string {
    if ($this->fromEnvironment() !== NULL) {
      return self::SOURCE_ENV;
    }
    if ($this->usesKeyModule()) {
      return $this->fromKeyModule() !== NULL ? self::SOURCE_KEY : NULL;
    }

    return $this->fromConfig() !== NULL ? self::SOURCE_CONFIG : NULL;
  }

  /**
   * The API key in use, or NULL. Never log or print it.
   */
  public function getApiKey(): ?string {
    return match ($this->getApiKeySource()) {
      self::SOURCE_ENV => $this->fromEnvironment(),
      self::SOURCE_KEY => $this->fromKeyModule(),
      self::SOURCE_CONFIG => $this->fromConfig(),
      default => NULL,
    };
  }

  /**
   * Whether the settings name a Key module key.
   *
   * If so, the key saved in configuration is not used.
   */
  protected function usesKeyModule(): bool {
    return $this->keyRepository !== NULL
      && trim((string) $this->configFactory->get('photos_sh.settings')->get('key_id')) !== '';
  }

  /**
   * Reads the environment variable.
   */
  protected function fromEnvironment(): ?string {
    $value = getenv(self::ENV_API_KEY);
    if (!is_string($value) || trim($value) === '') {
      $value = $_SERVER[self::ENV_API_KEY] ?? $_ENV[self::ENV_API_KEY] ?? NULL;
    }

    return is_string($value) && trim($value) !== '' ? trim($value) : NULL;
  }

  /**
   * Reads the chosen Key module key.
   */
  protected function fromKeyModule(): ?string {
    $id = trim((string) $this->configFactory->get('photos_sh.settings')->get('key_id'));
    /** @var \Drupal\key\KeyRepositoryInterface $repository */
    $repository = $this->keyRepository;
    $key = $repository->getKey($id);
    $value = $key?->getKeyValue();

    return is_string($value) && trim($value) !== '' ? trim($value) : NULL;
  }

  /**
   * Reads the key saved in configuration.
   */
  protected function fromConfig(): ?string {
    $value = $this->configFactory->get('photos_sh.settings')->get('api_key');

    return is_string($value) && trim($value) !== '' ? trim($value) : NULL;
  }

}
