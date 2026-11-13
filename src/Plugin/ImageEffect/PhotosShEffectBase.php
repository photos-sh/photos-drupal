<?php

declare(strict_types=1);

namespace Drupal\photos_sh\Plugin\ImageEffect;

use Drupal\Core\Image\ImageInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\image\ConfigurableImageEffectBase;
use Drupal\photos_sh\ImageBridge;
use Drupal\photos_sh\PhotosClientFactory;
use Drupal\photos_sh\Exception\ImageBridgeException;
use PhotosSh\ImageResult;
use PhotosSh\Input;
use PhotosSh\Photos;
use PhotosSh\Exception\ApiException;
use PhotosSh\Exception\PhotosException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for the effects that send the image to Photos.sh.
 *
 * Subclasses say what to ask the API for ({@see self::callApi()}) and what
 * comes back ({@see self::targetExtension()}, transformDimensions()). If the
 * API fails the effect returns FALSE: the image style writes no derivative, so
 * the next request for it tries again, and the failure is logged (never with
 * the API key).
 */
abstract class PhotosShEffectBase extends ConfigurableImageEffectBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs the effect.
   */
  final public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    LoggerInterface $logger,
    protected ImageBridge $bridge,
    protected PhotosClientFactory $clientFactory,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $logger);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.channel.photos_sh'),
      $container->get('photos_sh.image_bridge'),
      $container->get('photos_sh.client_factory'),
    );
  }

  /**
   * Calls the API.
   *
   * @param \PhotosSh\Photos $client
   *   The API client.
   * @param \PhotosSh\Input $input
   *   The image to send.
   * @param string $extension
   *   The extension the derivative is promised to have; ask the API for this
   *   format where it takes one.
   *
   * @throws \PhotosSh\Exception\PhotosException
   */
  abstract protected function callApi(Photos $client, Input $input, string $extension): ImageResult;

  /**
   * The extension the derivative has after this effect.
   *
   * @param string $extension
   *   The extension of the image going in, in lower case.
   */
  abstract protected function targetExtension(string $extension): string;

  /**
   * {@inheritdoc}
   */
  public function applyEffect(ImageInterface $image): bool {
    if (!$image->isValid()) {
      // An earlier effect failed; it has logged why.
      return FALSE;
    }
    try {
      [$input, $current] = $this->bridge->export($image);
      $target = $this->targetExtension($current);
      // No waiting and trying again while a visitor waits for the image: a
      // busy API fails the derivative, and the next request for it tries anew.
      $result = $this->callApi($this->clientFactory->get(0), $input, $target);
      $this->bridge->import($image, $result->bytes, $target);
    }
    catch (PhotosException | ImageBridgeException $e) {
      $this->logFailure($e, $image);
      // Core's image style saves a derivative whatever an effect returns, so
      // spoil the image: the save then fails and no derivative is written.
      $this->bridge->invalidate($image);
      return FALSE;
    }
    $this->logResult($result, $image);

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeExtension($extension) {
    return $this->targetExtension(strtolower((string) $extension));
  }

  /**
   * The image format name the API takes for a file extension.
   */
  protected static function apiFormat(string $extension): string {
    return $extension === 'jpeg' ? 'jpg' : $extension;
  }

  /**
   * Keeps png, jpg and webp; anything else becomes png.
   *
   * These are the formats the API writes that every GD build can load.
   */
  protected static function keepExtension(string $extension): string {
    return in_array($extension, ['png', 'jpg', 'jpeg', 'webp'], TRUE) ? $extension : 'png';
  }

  /**
   * Whether a submitted value is a whole number within a range.
   *
   * @param mixed $value
   *   The submitted value (a string from a form, as a rule).
   * @param int $min
   *   The lowest allowed value.
   * @param int $max
   *   The highest allowed value.
   */
  protected static function isWholeNumber(mixed $value, int $min, int $max): bool {
    if (!is_int($value) && !(is_string($value) && preg_match('/^-?\d+$/', trim($value)))) {
      return FALSE;
    }
    $number = (int) $value;
    return $number >= $min && $number <= $max;
  }

  /**
   * Hook for subclasses: note something about a successful call.
   */
  protected function logResult(ImageResult $result, ImageInterface $image): void {
  }

  /**
   * Logs a failure, without the key.
   */
  protected function logFailure(\Throwable $e, ImageInterface $image): void {
    $context = [
      '@effect' => $this->getPluginDefinition()['label'] ?? $this->getPluginId(),
      '@image' => $image->getSource(),
      '@message' => $e->getMessage(),
    ];
    if ($e instanceof ApiException) {
      $this->logger->error('@effect failed for @image: HTTP @status @code: @message', $context + [
        '@status' => $e->status,
        '@code' => $e->errorCode ?? 'unknown',
      ]);
      return;
    }
    $this->logger->error('@effect failed for @image: @message', $context);
  }

}
