<?php

declare(strict_types=1);

namespace Drupal\photos_sh\ImageMagick;

use Drupal\Core\Site\Settings;
use Drupal\imagemagick\Plugin\ImageToolkit\Operation\imagemagick\ImagemagickImageToolkitOperationBase;

/**
 * Replaces the image with encoded image data, for the ImageMagick toolkit.
 *
 * The ImageMagick toolkit does not hold pixels: it queues command line
 * arguments and runs them once, from a source file, when the image is saved.
 * To replace the image, the data is written to a temporary file, the queued
 * arguments are dropped, and that file becomes the new source. The image is
 * then re-identified, so its size and format are the result's.
 *
 * This class is not in the Plugin/ directory on purpose: its parent class
 * exists only when the ImageMagick module is installed, and Drupal reads every
 * class in Plugin/ directories. photos_sh_image_toolkit_operation_alter()
 * registers it when the module is there.
 *
 * @see photos_sh_image_toolkit_operation_alter()
 * @see \Drupal\photos_sh\Plugin\ImageToolkit\Operation\gd\LoadData
 */
class LoadData extends ImagemagickImageToolkitOperationBase {

  /**
   * Temporary files made here, removed when the request ends.
   *
   * @var string[]
   */
  protected static array $files = [];

  /**
   * {@inheritdoc}
   */
  protected function arguments(): array {
    return [
      'bytes' => [
        'description' => 'The encoded image.',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function validateArguments(array $arguments): array {
    if (!is_string($arguments['bytes']) || $arguments['bytes'] === '') {
      throw new \InvalidArgumentException('The image data must be a non-empty string.');
    }
    return $arguments;
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments): bool {
    $info = @getimagesizefromstring($arguments['bytes']);
    if (!$info) {
      return FALSE;
    }
    $extension = match ($info[2]) {
      IMAGETYPE_PNG => 'png',
      IMAGETYPE_JPEG => 'jpg',
      IMAGETYPE_WEBP => 'webp',
      IMAGETYPE_GIF => 'gif',
      default => NULL,
    };
    $toolkit = $this->getToolkit();
    $format = $extension !== NULL ? $toolkit->getExecManager()->getFormatMapper()->getFormatFromExtension($extension) : NULL;
    if ($format === NULL || !$toolkit->getExecManager()->getFormatMapper()->isFormatEnabled($format)) {
      return FALSE;
    }

    // A real path (not a temporary:// URI) so that it can be removed at the
    // end of the request, and read by the executable as it is. This is the
    // directory Drupal uses for temporary files.
    $base = tempnam((string) Settings::get('file_temp_path', sys_get_temp_dir()), 'photos_sh_');
    if ($base === FALSE) {
      return FALSE;
    }
    $path = $base . '.' . $extension;
    if (!@rename($base, $path) || file_put_contents($path, $arguments['bytes']) === FALSE) {
      @unlink($base);
      @unlink($path);
      return FALSE;
    }
    self::remember($path);

    // Make the file the source, with nothing queued and no memory of the old
    // image (orientation, profiles, a destination format set by an earlier
    // convert).
    $previous = $toolkit->getSource();
    $toolkit->reset($info[0], $info[1], $format);
    $toolkit->arguments()->setSource($path)->setDestinationFormat('');
    self::forget($previous);

    // Read the size, format and colour information from the file.
    return $toolkit->parseFile();
  }

  /**
   * Notes a temporary file to remove at the end of the request.
   */
  protected static function remember(string $path): void {
    if (self::$files === []) {
      register_shutdown_function(static function (): void {
        foreach (self::$files as $file) {
          try {
            @unlink($file);
          }
          catch (\Throwable) {
            // The container may be gone when the path is a stream wrapper URI
            // (as in tests); a leftover temporary file is harmless.
          }
        }
      });
    }
    self::$files[$path] = $path;
  }

  /**
   * Removes a temporary file made here, once it is no longer the source.
   */
  protected static function forget(string $path): void {
    if (isset(self::$files[$path])) {
      @unlink($path);
      unset(self::$files[$path]);
    }
  }

}
