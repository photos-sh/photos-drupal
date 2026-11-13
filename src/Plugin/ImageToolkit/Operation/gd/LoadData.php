<?php

declare(strict_types=1);

namespace Drupal\photos_sh\Plugin\ImageToolkit\Operation\gd;

use Drupal\Core\ImageToolkit\Attribute\ImageToolkitOperation;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\system\Plugin\ImageToolkit\Operation\gd\GDImageToolkitOperationBase;

/**
 * Replaces the image with encoded image data (a PNG, JPEG, WebP, ...).
 *
 * Used by the Photos.sh effects to load the API's result back into the image
 * being processed.
 */
#[ImageToolkitOperation(
  id: 'photos_sh_gd_load',
  toolkit: 'gd',
  operation: 'photos_sh_load',
  label: new TranslatableMarkup('Load image data'),
  description: new TranslatableMarkup('Replaces the image with encoded image data.'),
)]
class LoadData extends GDImageToolkitOperationBase {

  /**
   * {@inheritdoc}
   */
  protected function arguments() {
    return [
      'bytes' => [
        'description' => 'The encoded image.',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function validateArguments(array $arguments) {
    if (!is_string($arguments['bytes']) || $arguments['bytes'] === '') {
      throw new \InvalidArgumentException('The image data must be a non-empty string.');
    }
    return $arguments;
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    $info = @getimagesizefromstring($arguments['bytes']);
    if (!$info || !in_array(image_type_to_extension($info[2], FALSE), $this->getToolkit()->getSupportedExtensions(), TRUE)) {
      return FALSE;
    }
    $image = @imagecreatefromstring($arguments['bytes']);
    if (!$image instanceof \GdImage) {
      return FALSE;
    }
    // Filters and saving expect a true-colour image.
    if (!imageistruecolor($image)) {
      imagepalettetotruecolor($image);
    }
    imagealphablending($image, FALSE);
    imagesavealpha($image, TRUE);

    $this->getToolkit()->setImage($image);
    $this->getToolkit()->setType($info[2]);
    return TRUE;
  }

}
