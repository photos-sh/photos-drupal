<?php

declare(strict_types=1);

namespace Drupal\photos_sh\Plugin\ImageEffect;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\image\Attribute\ImageEffect;
use PhotosSh\ImageResult;
use PhotosSh\Input;
use PhotosSh\Photos;

/**
 * Removes noise and JPEG artefacts.
 *
 * The image keeps its size and format (png, jpg or webp; anything else
 * becomes png). It takes photos up to 12 megapixels.
 */
#[ImageEffect(
  id: 'photos_sh_denoise',
  label: new TranslatableMarkup('Remove noise (Photos.sh)'),
  description: new TranslatableMarkup('Removes noise and JPEG artefacts with the Photos.sh API.'),
)]
class DenoiseImageEffect extends StrengthImageEffectBase {

  /**
   * {@inheritdoc}
   */
  protected function callApi(Photos $client, Input $input, string $extension): ImageResult {
    return $client->denoise($input, strength: (int) $this->configuration['strength'], format: self::apiFormat($extension));
  }

  /**
   * {@inheritdoc}
   */
  protected function strengthDescription(): string {
    return (string) $this->t('How much of the cleaned image replaces the photo. 100 is all of it. Lower it for a photo that is already clean, which loses a little fine texture.');
  }

  /**
   * {@inheritdoc}
   */
  protected function caution(): string {
    return (string) $this->t('Remove noise is slow without a GPU. The derivative is made when the image is first requested, so that request can take seconds. It takes photos up to 12 megapixels.');
  }

}
