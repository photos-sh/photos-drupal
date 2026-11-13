<?php

declare(strict_types=1);

namespace Drupal\photos_sh;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Image\ImageInterface;
use Drupal\imagemagick\Plugin\ImageToolkit\ImagemagickToolkit;
use Drupal\photos_sh\Exception\ImageBridgeException;
use Drupal\system\Plugin\ImageToolkit\GDToolkit;
use PhotosSh\Input;

/**
 * Moves an image between Drupal's image toolkit and the Photos.sh API.
 *
 * The toolkit holds the image in memory, so the current state is written to a
 * temporary file to be sent, and the API's answer is loaded back into the
 * same image object, so effects later in the style keep working on the result.
 * Loading back needs a toolkit operation, which this module provides for GD
 * and for the ImageMagick module's toolkit.
 */
class ImageBridge {

  public function __construct(protected FileSystemInterface $fileSystem) {
  }

  /**
   * Encodes the image's current state for sending.
   *
   * @return array{0: \PhotosSh\Input, 1: string}
   *   The input and its file extension (png, jpg, gif, webp, ...).
   *
   * @throws \Drupal\photos_sh\Exception\ImageBridgeException
   */
  public function export(ImageInterface $image): array {
    $this->assertSupported($image);

    $extension = self::normalise($this->currentExtension($image));
    $base = $this->fileSystem->tempnam('temporary://', 'photos_sh_');
    if ($base === FALSE) {
      throw new ImageBridgeException('Could not create a temporary file.');
    }
    // The extension tells ImageMagick what to write.
    $path = $base . '.' . $extension;
    try {
      if (!$image->getToolkit()->save($path)) {
        throw new ImageBridgeException('The image toolkit could not write the image.');
      }
      $bytes = file_get_contents($path);
      if ($bytes === FALSE || $bytes === '') {
        throw new ImageBridgeException('Could not read back the temporary image.');
      }
    }
    finally {
      $this->fileSystem->unlink($base);
      if (file_exists($path)) {
        $this->fileSystem->unlink($path);
      }
    }

    return [Input::fromBytes($bytes, 'image.' . $extension), $extension];
  }

  /**
   * Replaces the image with the encoded image the API returned.
   *
   * @param \Drupal\Core\Image\ImageInterface $image
   *   The image to replace.
   * @param string $bytes
   *   The encoded image.
   * @param string $extension
   *   The extension the image style promises for its derivative. When the API
   *   returned another format, the image is converted to this one.
   *
   * @throws \Drupal\photos_sh\Exception\ImageBridgeException
   */
  public function import(ImageInterface $image, string $bytes, string $extension): void {
    $this->assertSupported($image);

    if (!$image->apply('photos_sh_load', ['bytes' => $bytes])) {
      throw new ImageBridgeException('The result from Photos.sh is not an image the toolkit can read.');
    }
    $actual = self::normalise($this->currentExtension($image));
    if ($actual !== self::normalise($extension) && !$image->convert(self::normalise($extension))) {
      throw new ImageBridgeException(sprintf('Could not convert the result from %s to %s.', $actual, $extension));
    }
  }

  /**
   * Marks the image invalid, so the style does not save a derivative.
   *
   * Core's image style ignores what an effect returns and saves whatever the
   * image holds. An invalid image makes the save fail instead, so a failed
   * Photos.sh call leaves no derivative (and the next request tries again).
   */
  public function invalidate(ImageInterface $image): void {
    $toolkit = $image->getToolkit();
    if ($toolkit instanceof GDToolkit) {
      $toolkit->setImage(NULL);
    }
    elseif ($toolkit instanceof ImagemagickToolkit) {
      // No source format means no MIME type, which is what makes an
      // ImageMagick image invalid.
      $toolkit->arguments()->setSourceFormat('');
    }
  }

  /**
   * The file extension the image would be saved with now.
   *
   * For GD that is the type of the image in memory. ImageMagick keeps the
   * source format apart from the destination format a convert operation sets,
   * and the destination format is what a save writes.
   */
  protected function currentExtension(ImageInterface $image): string {
    $toolkit = $image->getToolkit();
    // The instanceof check does not load the class, so it is safe without the
    // module.
    if ($toolkit instanceof ImagemagickToolkit) {
      $arguments = $toolkit->arguments();
      $format = $arguments->getDestinationFormat() ?: $arguments->getSourceFormat();
      $mime = $toolkit->getExecManager()->getFormatMapper()->getMimeTypeFromFormat($format) ?? '';
    }
    else {
      $mime = (string) $image->getMimeType();
    }
    return self::extensionForMime($mime);
  }

  /**
   * Maps a MIME type to the file extension used for it.
   */
  public static function extensionForMime(string $mime): string {
    return match (strtolower($mime)) {
      'image/jpeg' => 'jpg',
      'image/png' => 'png',
      'image/gif' => 'gif',
      'image/webp' => 'webp',
      'image/avif' => 'avif',
      'image/tiff' => 'tiff',
      'image/bmp' => 'bmp',
      default => 'png',
    };
  }

  /**
   * Treats jpeg and jpg alike.
   */
  protected static function normalise(string $extension): string {
    $extension = strtolower($extension);

    return $extension === 'jpeg' ? 'jpg' : $extension;
  }

  /**
   * Checks that the image is valid and its toolkit can be used.
   */
  protected function assertSupported(ImageInterface $image): void {
    if (!$image->isValid()) {
      throw new ImageBridgeException('The image is not valid.');
    }
    if (!in_array($image->getToolkitId(), ['gd', 'imagemagick'], TRUE)) {
      throw new ImageBridgeException(sprintf('The "%s" image toolkit is not supported; use GD or ImageMagick.', $image->getToolkitId()));
    }
  }

}
