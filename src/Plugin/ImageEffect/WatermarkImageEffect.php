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
 * Writes a text on the photo.
 *
 * The image keeps its size and format (png, jpg or webp; anything else
 * becomes png). The text is white.
 */
#[ImageEffect(
  id: 'photos_sh_watermark',
  label: new TranslatableMarkup('Watermark with text (Photos.sh)'),
  description: new TranslatableMarkup('Writes a text on the photo, using the Photos.sh API.'),
)]
class WatermarkImageEffect extends PhotosShEffectBase {

  public const MAX_TEXT = 200;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['text' => '', 'position' => 'bottom-right', 'size' => 25, 'opacity' => 50];
  }

  /**
   * {@inheritdoc}
   */
  protected function callApi(Photos $client, Input $input, string $extension): ImageResult {
    return $client->watermark(
      $input,
      text: (string) $this->configuration['text'],
      position: (string) $this->configuration['position'],
      scale: (int) $this->configuration['size'],
      opacity: (int) $this->configuration['opacity'],
      format: self::apiFormat($extension),
    );
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
    $positions = Catalog::positions();
    $summary = $this->t('"@text", @position, size @size%, opacity @opacity%', [
      '@text' => $this->configuration['text'],
      '@position' => strtolower((string) ($positions[$this->configuration['position']] ?? $this->configuration['position'])),
      '@size' => (int) $this->configuration['size'],
      '@opacity' => (int) $this->configuration['opacity'],
    ]);
    return ['#markup' => $summary] + parent::getSummary();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Text'),
      '#description' => $this->t('Up to @max characters. It is written in white, in a bold sans-serif font with Latin, Greek and Cyrillic letters. Other scripts come out as empty boxes.', ['@max' => self::MAX_TEXT]),
      '#default_value' => $this->configuration['text'],
      '#maxlength' => self::MAX_TEXT,
      '#required' => TRUE,
    ];
    $form['position'] = [
      '#type' => 'select',
      '#title' => $this->t('Position'),
      '#options' => Catalog::positions(),
      '#default_value' => $this->configuration['position'],
      '#required' => TRUE,
    ];
    $form['size'] = [
      '#type' => 'number',
      '#title' => $this->t('Size'),
      '#description' => $this->t('The width of the text as a percentage of the photo’s width, from 1 to 100. A photo of any size gets the same share.'),
      '#field_suffix' => '%',
      '#default_value' => (int) $this->configuration['size'],
      '#min' => 1,
      '#max' => 100,
      '#step' => 1,
      '#required' => TRUE,
    ];
    $form['opacity'] = [
      '#type' => 'number',
      '#title' => $this->t('Opacity'),
      '#description' => $this->t('From 1 (hardly visible) to 100 (solid).'),
      '#field_suffix' => '%',
      '#default_value' => (int) $this->configuration['opacity'],
      '#min' => 1,
      '#max' => 100,
      '#step' => 1,
      '#required' => TRUE,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);
    $text = trim((string) $form_state->getValue('text'));
    if ($text === '') {
      $form_state->setErrorByName('text', $this->t('Enter the text to write on the photo.'));
    }
    elseif (mb_strlen($text) > self::MAX_TEXT) {
      $form_state->setErrorByName('text', $this->t('Use at most @max characters.', ['@max' => self::MAX_TEXT]));
    }
    if (!isset(Catalog::positions()[$form_state->getValue('position')])) {
      $form_state->setErrorByName('position', $this->t('Choose a position.'));
    }
    foreach (['size', 'opacity'] as $name) {
      if (!self::isWholeNumber($form_state->getValue($name), 1, 100)) {
        $form_state->setErrorByName($name, $this->t('Enter a whole number from 1 to 100.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['text'] = trim((string) $form_state->getValue('text'));
    $this->configuration['position'] = (string) $form_state->getValue('position');
    $this->configuration['size'] = (int) $form_state->getValue('size');
    $this->configuration['opacity'] = (int) $form_state->getValue('opacity');
  }

}
