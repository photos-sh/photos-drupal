<?php

declare(strict_types=1);

namespace Drupal\photos_sh\Plugin\ImageEffect;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Image\ImageInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\image\Attribute\ImageEffect;
use Drupal\photos_sh\Catalog;
use PhotosSh\FixResult;
use PhotosSh\ImageResult;
use PhotosSh\Input;
use PhotosSh\Photos;

/**
 * Fixes the photo for a marketplace's image rules.
 *
 * The API decides the size and background; only the file format is promised
 * here (JPEG unless configured), so a result in another format is converted.
 */
#[ImageEffect(
  id: 'photos_sh_fix',
  label: new TranslatableMarkup('Fix for a marketplace (Photos.sh)'),
  description: new TranslatableMarkup("Makes the photo meet a marketplace's image rules, using the Photos.sh API."),
)]
class FixMarketplaceImageEffect extends PhotosShEffectBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['marketplace' => 'amazon', 'format' => 'jpg'];
  }

  /**
   * {@inheritdoc}
   */
  protected function callApi(Photos $client, Input $input, string $extension): ImageResult {
    return $client->fix($input, marketplace: (string) $this->configuration['marketplace']);
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
    // The marketplace's rules decide the size.
    $dimensions['width'] = $dimensions['height'] = NULL;
  }

  /**
   * {@inheritdoc}
   */
  protected function logResult(ImageResult $result, ImageInterface $image): void {
    if ($result instanceof FixResult && !$result->passed) {
      $this->logger->notice('Fix for @marketplace: @image still fails the check: @findings', [
        '@marketplace' => $this->configuration['marketplace'],
        '@image' => $image->getSource(),
        '@findings' => implode(', ', $result->findings),
      ]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    $marketplaces = Catalog::marketplaces();
    $name = $marketplaces[$this->configuration['marketplace']] ?? $this->configuration['marketplace'];
    $summary = $this->t('@marketplace, @format', [
      '@marketplace' => $name,
      '@format' => strtoupper((string) $this->configuration['format']),
    ]);
    return ['#markup' => $summary] + parent::getSummary();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['marketplace'] = [
      '#type' => 'select',
      '#title' => $this->t('Marketplace'),
      '#options' => Catalog::marketplaces(),
      '#default_value' => $this->configuration['marketplace'],
      '#required' => TRUE,
    ];
    $form['format'] = [
      '#type' => 'select',
      '#title' => $this->t('Output format'),
      '#description' => $this->t('The file format of the derivative. If the API answers in another format, the image is converted.'),
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
    if (!isset(Catalog::marketplaces()[$form_state->getValue('marketplace')])) {
      $form_state->setErrorByName('marketplace', $this->t('Choose a marketplace.'));
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
    $this->configuration['marketplace'] = (string) $form_state->getValue('marketplace');
    $this->configuration['format'] = (string) $form_state->getValue('format');
  }

}
