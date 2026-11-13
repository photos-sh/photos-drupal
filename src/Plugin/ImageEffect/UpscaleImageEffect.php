<?php

declare(strict_types=1);

namespace Drupal\photos_sh\Plugin\ImageEffect;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\image\Attribute\ImageEffect;
use PhotosSh\ImageResult;
use PhotosSh\Input;
use PhotosSh\Photos;

/**
 * Upscales with AI, by 2x, 3x or 4x.
 *
 * The image keeps its format (png, jpg or webp; anything else becomes png).
 */
#[ImageEffect(
  id: 'photos_sh_upscale',
  label: new TranslatableMarkup('Upscale (Photos.sh)'),
  description: new TranslatableMarkup('Enlarges the image with AI, using the Photos.sh API.'),
)]
class UpscaleImageEffect extends PhotosShEffectBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['scale' => 2];
  }

  /**
   * {@inheritdoc}
   */
  protected function callApi(Photos $client, Input $input, string $extension): ImageResult {
    return $client->upscale($input, scale: (int) $this->configuration['scale'], format: self::apiFormat($extension));
  }

  /**
   * {@inheritdoc}
   */
  protected function targetExtension(string $extension): string {
    return self::keepExtension($extension);
  }

  /**
   * {@inheritdoc}
   */
  public function transformDimensions(array &$dimensions, $uri): void {
    $scale = (int) $this->configuration['scale'];
    foreach (['width', 'height'] as $side) {
      if (isset($dimensions[$side])) {
        $dimensions[$side] = (int) round($dimensions[$side] * $scale);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    return ['#markup' => $this->t('@scale×', ['@scale' => (int) $this->configuration['scale']])] + parent::getSummary();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['scale'] = [
      '#type' => 'select',
      '#title' => $this->t('Scale'),
      '#description' => $this->t('How many times larger each side becomes. The API charges per image, and larger sources take longer.'),
      '#options' => [2 => '2×', 3 => '3×', 4 => '4×'],
      '#default_value' => (int) $this->configuration['scale'],
      '#required' => TRUE,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);
    if (!in_array((int) $form_state->getValue('scale'), [2, 3, 4], TRUE)) {
      $form_state->setErrorByName('scale', $this->t('Choose 2×, 3× or 4×.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['scale'] = (int) $form_state->getValue('scale');
  }

}
