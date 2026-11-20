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
 * Removes the background with Photos.sh.
 *
 * Without a background colour the result is a transparent PNG. With one, the
 * image keeps its format (png, jpg or webp; anything else becomes png). For a
 * studio shot (a product on a plain light backdrop) the box, case or lid shown
 * with the product can be kept too. The model that finds the subject can be
 * chosen: Atlas (the API's default), Lumen or Muse.
 */
#[ImageEffect(
  id: 'photos_sh_remove_background',
  label: new TranslatableMarkup('Remove background (Photos.sh)'),
  description: new TranslatableMarkup('Removes the background with the Photos.sh API.'),
)]
class RemoveBackgroundImageEffect extends PhotosShEffectBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'background' => '',
      'crop' => FALSE,
      'padding' => 0,
      'studio' => FALSE,
      'model' => Catalog::DEFAULT_MODEL,
    ];
  }

  /**
   * The model to send to the API, or NULL to leave it to the API's default.
   *
   * Atlas is the API's default, so it is not sent; an unknown model is not
   * sent either.
   */
  protected function apiModel(): ?string {
    $model = (string) ($this->configuration['model'] ?? '');
    return $model !== Catalog::DEFAULT_MODEL && isset(Catalog::models()[$model]) ? $model : NULL;
  }

  /**
   * {@inheritdoc}
   */
  protected function callApi(Photos $client, Input $input, string $extension): ImageResult {
    $crop = !empty($this->configuration['crop']);
    $background = trim((string) $this->configuration['background']);
    $padding = (int) $this->configuration['padding'];

    return $client->removeBackground(
      $input,
      format: self::apiFormat($extension),
      background: $background !== '' ? $background : NULL,
      crop: $crop ? TRUE : NULL,
      padding: $crop && $padding > 0 ? $padding : NULL,
      studio: !empty($this->configuration['studio']) ? TRUE : NULL,
      model: $this->apiModel(),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function targetExtension(string $extension): string {
    return trim((string) $this->configuration['background']) === '' ? 'png' : self::keepExtension($extension);
  }

  /**
   * {@inheritdoc}
   */
  public function transformDimensions(array &$dimensions, $uri): void {
    // Cropping to the subject changes the size in a way only the API knows.
    if (!empty($this->configuration['crop'])) {
      $dimensions['width'] = $dimensions['height'] = NULL;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    $parts = [];
    $background = trim((string) $this->configuration['background']);
    $parts[] = $background !== '' ? $this->t('background @colour', ['@colour' => $background]) : $this->t('transparent');
    if (!empty($this->configuration['crop'])) {
      $parts[] = $this->t('cropped, padding @padding px', ['@padding' => (int) $this->configuration['padding']]);
    }
    if (!empty($this->configuration['studio'])) {
      $parts[] = $this->t('studio shot');
    }
    $model = $this->apiModel();
    if ($model !== NULL) {
      $parts[] = $this->t('model @model', ['@model' => Catalog::models()[$model]]);
    }
    return ['#markup' => implode(', ', $parts)] + parent::getSummary();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['background'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Background colour'),
      '#description' => $this->t('A colour as #rrggbb, or <em>white</em> or <em>black</em>. Leave empty for a transparent PNG.'),
      '#default_value' => $this->configuration['background'],
      '#size' => 12,
      '#maxlength' => 7,
    ];
    $form['crop'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Crop to the subject'),
      '#default_value' => !empty($this->configuration['crop']),
    ];
    $form['padding'] = [
      '#type' => 'number',
      '#title' => $this->t('Padding'),
      '#description' => $this->t('The margin kept around the subject when cropping.'),
      '#field_suffix' => $this->t('px'),
      '#default_value' => (int) $this->configuration['padding'],
      '#min' => 0,
      '#max' => 1000,
      '#step' => 1,
      '#states' => ['visible' => [':input[name="crop"]' => ['checked' => TRUE]]],
    ];
    $form['studio'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Studio shot'),
      '#description' => $this->t('The product photos are taken on a plain light backdrop (a light tent): background removal also keeps the box, case or lid shown with the product. A photo without a plain light backdrop is cut out as usual.'),
      '#default_value' => !empty($this->configuration['studio']),
    ];
    $model = (string) ($this->configuration['model'] ?? '');
    $form['model'] = [
      '#type' => 'radios',
      '#title' => $this->t('Model'),
      '#description' => $this->t('The model that finds the subject. Each costs the same; try another when one drops part of the product.'),
      '#options' => Catalog::models(),
      '#default_value' => isset(Catalog::models()[$model]) ? $model : Catalog::DEFAULT_MODEL,
      '#required' => TRUE,
    ];
    foreach (Catalog::modelDescriptions() as $key => $description) {
      $form['model'][$key]['#description'] = $description;
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);
    $background = trim((string) $form_state->getValue('background'));
    if ($background !== '' && !preg_match('/^(#[0-9a-fA-F]{6}|white|black)$/', $background)) {
      $form_state->setErrorByName('background', $this->t('Enter a colour as #rrggbb, or white or black, or leave the field empty.'));
    }
    $model = $form_state->getValue('model');
    if ($model !== NULL && !isset(Catalog::models()[(string) $model])) {
      $form_state->setErrorByName('model', $this->t('Choose a model.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['background'] = strtolower(trim((string) $form_state->getValue('background')));
    $this->configuration['crop'] = (bool) $form_state->getValue('crop');
    $this->configuration['padding'] = $this->configuration['crop'] ? (int) $form_state->getValue('padding') : 0;
    $this->configuration['studio'] = (bool) $form_state->getValue('studio');
    $model = (string) $form_state->getValue('model');
    $this->configuration['model'] = isset(Catalog::models()[$model]) ? $model : Catalog::DEFAULT_MODEL;
  }

}
