<?php

declare(strict_types=1);

namespace Drupal\photos_sh\Plugin\ImageEffect;

use Drupal\Core\Image\ImageInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\image\Attribute\ImageEffect;
use PhotosSh\ImageResult;
use PhotosSh\Input;
use PhotosSh\Photos;
use PhotosSh\RestoreFacesResult;

/**
 * Redraws each face sharp, for old, blurred or small photos.
 *
 * The rest of the photo is not touched.
 */
#[ImageEffect(
  id: 'photos_sh_restore_faces',
  label: new TranslatableMarkup('Restore faces (Photos.sh)'),
  description: new TranslatableMarkup('Redraws faces sharp in old or blurred photos with the Photos.sh API.'),
)]
class RestoreFacesImageEffect extends StrengthImageEffectBase {

  /**
   * {@inheritdoc}
   */
  protected function callApi(Photos $client, Input $input, string $extension): ImageResult {
    return $client->restoreFaces($input, strength: (int) $this->configuration['strength'], format: self::apiFormat($extension));
  }

  /**
   * {@inheritdoc}
   */
  protected function strengthDescription(): string {
    return (string) $this->t('How much of the restored face replaces the original. 100 is all of it.');
  }

  /**
   * {@inheritdoc}
   */
  protected function caution(): string {
    return (string) $this->t('Restore faces invents detail, so the face only resembles the person. Do not use it for identity photos or evidence. A derivative is made when first requested and can take seconds.');
  }

  /**
   * {@inheritdoc}
   */
  protected function logResult(ImageResult $result, ImageInterface $image): void {
    if ($result instanceof RestoreFacesResult) {
      $this->logger->debug('Restore faces for @image: @faces restored, @skipped left alone.', [
        '@image' => $image->getSource(),
        '@faces' => $result->faces,
        '@skipped' => $result->facesSkipped,
      ]);
    }
  }

}
