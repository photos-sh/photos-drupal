<?php

declare(strict_types=1);

namespace Drupal\photos_sh\Plugin\ImageEffect;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\image\Attribute\ImageEffect;
use PhotosSh\ImageResult;
use PhotosSh\Input;
use PhotosSh\Photos;

/**
 * Colours a black-and-white photo.
 *
 * The colours are guesses.
 */
#[ImageEffect(
  id: 'photos_sh_colorize',
  label: new TranslatableMarkup('Colourise (Photos.sh)'),
  description: new TranslatableMarkup('Adds colour to a black-and-white photo with the Photos.sh API.'),
)]
class ColorizeImageEffect extends StrengthImageEffectBase {

  /**
   * {@inheritdoc}
   */
  protected function callApi(Photos $client, Input $input, string $extension): ImageResult {
    return $client->colorize($input, strength: (int) $this->configuration['strength'], format: self::apiFormat($extension));
  }

  /**
   * {@inheritdoc}
   */
  protected function strengthDescription(): string {
    return (string) $this->t('How colourful the result is. 100 is the full colour the model chooses. Lower is paler.');
  }

  /**
   * {@inheritdoc}
   */
  protected function caution(): string {
    return (string) $this->t('Colourise guesses its colours. Skin and sky are likely right, but clothes, cars and walls get invented colours. A photo that already has colour is treated as black and white. A derivative is made when first requested and can take seconds.');
  }

}
