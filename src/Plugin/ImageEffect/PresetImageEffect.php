<?php

declare(strict_types=1);

namespace Drupal\photos_sh\Plugin\ImageEffect;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\image\Attribute\ImageEffect;
use Drupal\photos_sh\Catalog;
use PhotosSh\ImageResult;
use PhotosSh\Input;
use PhotosSh\Photos;

/**
 * Runs a Photos.sh preset (marketplace, studio, social, cutout, web).
 *
 * The preset decides the size; only the file format is promised here, and a
 * result in another format is converted to it.
 */
#[ImageEffect(
  id: 'photos_sh_preset',
  label: new TranslatableMarkup('Run a preset (Photos.sh)'),
  description: new TranslatableMarkup('Applies a built-in Photos.sh preset: a ready-made chain of edits.'),
)]
class PresetImageEffect extends PhotosShEffectBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['preset' => 'marketplace', 'format' => 'jpg'];
  }

  /**
   * {@inheritdoc}
   */
  protected function callApi(Photos $client, Input $input, string $extension): ImageResult {
    return $client->run($input, preset: (string) $this->configuration['preset']);
  }

  /**
   * {@inheritdoc}
   */
  protected function targetExtension(string $extension): string {
    return (string) $this->configuration['format'];
  }

  /**
   * {@inheritdoc}
   */
  public function transformDimensions(array &$dimensions, $uri): void {
    // The preset decides the size.
    $dimensions['width'] = $dimensions['height'] = NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    $summary = $this->t('@preset, @format', [
      '@preset' => $this->configuration['preset'],
      '@format' => strtoupper((string) $this->configuration['format']),
    ]);
    return ['#markup' => $summary] + parent::getSummary();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['preset'] = [
      '#type' => 'select',
      '#title' => $this->t('Preset'),
      '#options' => Catalog::presets(),
      '#default_value' => $this->configuration['preset'],
      '#required' => TRUE,
    ];
    $form['format'] = [
      '#type' => 'select',
      '#title' => $this->t('Output format'),
      '#description' => $this->t('The file format of the derivative. Each preset normally delivers one format (marketplace: JPEG, studio and web: WebP, cutout: PNG); if the API answers in another, the image is converted.'),
      '#options' => Catalog::formats(),
      '#default_value' => $this->configuration['format'],
      '#required' => TRUE,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);
    if (!isset(Catalog::presets()[$form_state->getValue('preset')])) {
      $form_state->setErrorByName('preset', $this->t('Choose a preset.'));
    }
    if (!isset(Catalog::formats()[$form_state->getValue('format')])) {
      $form_state->setErrorByName('format', $this->t('Choose an output format.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['preset'] = (string) $form_state->getValue('preset');
    $this->configuration['format'] = (string) $form_state->getValue('format');
  }

}
