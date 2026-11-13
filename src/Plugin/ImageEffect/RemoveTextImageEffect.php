<?php

declare(strict_types=1);

namespace Drupal\photos_sh\Plugin\ImageEffect;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Image\ImageInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\image\Attribute\ImageEffect;
use PhotosSh\ImageResult;
use PhotosSh\Input;
use PhotosSh\Photos;
use PhotosSh\RemoveTextResult;

/**
 * Removes text, watermarks and captions.
 *
 * The image keeps its size and format (png, jpg or webp; anything else
 * becomes png).
 */
#[ImageEffect(
  id: 'photos_sh_remove_text',
  label: new TranslatableMarkup('Remove text (Photos.sh)'),
  description: new TranslatableMarkup('Paints out text, watermarks and captions with the Photos.sh API.'),
)]
class RemoveTextImageEffect extends PhotosShEffectBase {

  public const REGIONS = ['background', 'all'];

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['region' => 'background'];
  }

  /**
   * {@inheritdoc}
   */
  protected function callApi(Photos $client, Input $input, string $extension): ImageResult {
    return $client->removeText($input, region: (string) $this->configuration['region'], format: self::apiFormat($extension));
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
  public function getSummary() {
    $text = $this->configuration['region'] === 'all' ? $this->t('all text') : $this->t('text off the product');
    return ['#markup' => $text] + parent::getSummary();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['region'] = [
      '#type' => 'radios',
      '#title' => $this->t('Region'),
      '#options' => [
        'background' => $this->t('Text off the product (labels and packaging on the product stay)'),
        'all' => $this->t('All text, including text on the product'),
      ],
      '#default_value' => $this->configuration['region'],
      '#required' => TRUE,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);
    if (!in_array($form_state->getValue('region'), self::REGIONS, TRUE)) {
      $form_state->setErrorByName('region', $this->t('Choose a region.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['region'] = (string) $form_state->getValue('region');
  }

  /**
   * {@inheritdoc}
   */
  protected function logResult(ImageResult $result, ImageInterface $image): void {
    if ($result instanceof RemoveTextResult) {
      $this->logger->debug('Remove text for @image: @removed removed, @kept kept.', [
        '@image' => $image->getSource(),
        '@removed' => $result->removed,
        '@kept' => $result->kept,
      ]);
    }
  }

}
