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
 * Fixes light and colour, automatically or by hand.
 *
 * The image keeps its size and format (png, jpg or webp; anything else
 * becomes png).
 */
#[ImageEffect(
  id: 'photos_sh_enhance',
  label: new TranslatableMarkup('Fix light and colour (Photos.sh)'),
  description: new TranslatableMarkup('Corrects the light and colour of a photo with the Photos.sh API.'),
)]
class EnhanceImageEffect extends PhotosShEffectBase {

  public const MODES = ['auto', 'custom'];

  /**
   * The manual settings and their ranges.
   */
  public const SETTINGS = [
    'brightness' => [-100, 100],
    'contrast' => [-100, 100],
    'saturation' => [-100, 100],
    'sharpen' => [0, 100],
  ];

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['mode' => 'auto', 'brightness' => 0, 'contrast' => 0, 'saturation' => 0, 'sharpen' => 0];
  }

  /**
   * {@inheritdoc}
   */
  protected function callApi(Photos $client, Input $input, string $extension): ImageResult {
    $format = self::apiFormat($extension);
    if ($this->configuration['mode'] !== 'custom') {
      return $client->enhance($input, auto: TRUE, format: $format);
    }
    return $client->enhance(
      $input,
      brightness: (int) $this->configuration['brightness'],
      contrast: (int) $this->configuration['contrast'],
      saturation: (int) $this->configuration['saturation'],
      sharpen: (int) $this->configuration['sharpen'],
      format: $format,
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
    if ($this->configuration['mode'] !== 'custom') {
      return ['#markup' => $this->t('automatic')] + parent::getSummary();
    }
    $labels = [
      'brightness' => $this->t('brightness @value', ['@value' => (int) $this->configuration['brightness']]),
      'contrast' => $this->t('contrast @value', ['@value' => (int) $this->configuration['contrast']]),
      'saturation' => $this->t('saturation @value', ['@value' => (int) $this->configuration['saturation']]),
      'sharpen' => $this->t('sharpen @value', ['@value' => (int) $this->configuration['sharpen']]),
    ];
    $parts = [];
    foreach ($labels as $name => $label) {
      if ((int) $this->configuration[$name] !== 0) {
        $parts[] = $label;
      }
    }
    return ['#markup' => implode(', ', $parts)] + parent::getSummary();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['caution'] = [
      '#type' => 'item',
      '#markup' => $this->t('This adjusts the light and colour that are there. It does not remove noise, sharpen a blurred photo or bring back detail from bright areas that are pure white.'),
    ];
    $form['mode'] = [
      '#type' => 'radios',
      '#title' => $this->t('Settings'),
      '#options' => [
        'auto' => $this->t('Automatic: stretch the tones from black to white and take out a colour cast'),
        'custom' => $this->t('Choose the settings'),
      ],
      '#default_value' => $this->configuration['mode'],
      '#required' => TRUE,
    ];
    $states = ['visible' => [':input[name="mode"]' => ['value' => 'custom']]];
    $titles = [
      'brightness' => $this->t('Brightness'),
      'contrast' => $this->t('Contrast'),
      'saturation' => $this->t('Saturation'),
      'sharpen' => $this->t('Sharpening'),
    ];
    $descriptions = [
      'brightness' => $this->t('From -100 (darker) to 100 (lighter). 0 changes nothing.'),
      'contrast' => $this->t('From -100 (flatter) to 100 (stronger). 0 changes nothing.'),
      'saturation' => $this->t('From -100 (black and white) to 100 (more colour). 0 changes nothing.'),
      'sharpen' => $this->t('From 0 to 100. 0 changes nothing.'),
    ];
    foreach (self::SETTINGS as $name => [$min, $max]) {
      $form[$name] = [
        '#type' => 'number',
        '#title' => $titles[$name],
        '#description' => $descriptions[$name],
        '#default_value' => (int) $this->configuration[$name],
        '#min' => $min,
        '#max' => $max,
        '#step' => 1,
        '#states' => $states,
      ];
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);
    $mode = $form_state->getValue('mode');
    if (!in_array($mode, self::MODES, TRUE)) {
      $form_state->setErrorByName('mode', $this->t('Choose automatic or your own settings.'));
      return;
    }
    if ($mode !== 'custom') {
      return;
    }
    $changed = FALSE;
    $invalid = FALSE;
    foreach (self::SETTINGS as $name => [$min, $max]) {
      $value = $form_state->getValue($name);
      if (!self::isWholeNumber($value, $min, $max)) {
        $form_state->setErrorByName($name, $this->t('Enter a whole number from @min to @max.', [
          '@min' => $min,
          '@max' => $max,
        ]));
        $invalid = TRUE;
      }
      elseif ((int) $value !== 0) {
        $changed = TRUE;
      }
    }
    // The API does the automatic fix when every setting is 0, so say so here.
    if (!$changed && !$invalid) {
      $form_state->setErrorByName('mode', $this->t('Change at least one setting, or choose automatic.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $custom = $form_state->getValue('mode') === 'custom';
    $this->configuration['mode'] = $custom ? 'custom' : 'auto';
    foreach (array_keys(self::SETTINGS) as $name) {
      $this->configuration[$name] = $custom ? (int) $form_state->getValue($name) : 0;
    }
  }

}
