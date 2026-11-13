<?php

declare(strict_types=1);

namespace Drupal\photos_sh\Plugin\ImageEffect;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Image\ImageInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\image\Attribute\ImageEffect;
use Drupal\photos_sh\Catalog;
use PhotosSh\BlurFacesResult;
use PhotosSh\ImageResult;
use PhotosSh\Input;
use PhotosSh\Photos;

/**
 * Hides every face: blurred, pixelated or covered with a colour.
 *
 * The image keeps its size and format (png, jpg or webp; anything else
 * becomes png).
 */
#[ImageEffect(
  id: 'photos_sh_blur_faces',
  label: new TranslatableMarkup('Hide faces (Photos.sh)'),
  description: new TranslatableMarkup('Blurs, pixelates or covers the faces in a photo with the Photos.sh API.'),
)]
class BlurFacesImageEffect extends PhotosShEffectBase {

  public const MODES = ['blur', 'pixelate', 'fill'];

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['mode' => 'blur', 'strength' => 50, 'color' => 'black'];
  }

  /**
   * {@inheritdoc}
   */
  protected function callApi(Photos $client, Input $input, string $extension): ImageResult {
    $fill = $this->configuration['mode'] === 'fill';
    return $client->blurFaces(
      $input,
      mode: (string) $this->configuration['mode'],
      strength: $fill ? NULL : (int) $this->configuration['strength'],
      color: $fill ? (string) $this->configuration['color'] : NULL,
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
  protected function logResult(ImageResult $result, ImageInterface $image): void {
    if ($result instanceof BlurFacesResult && $result->faces === 0) {
      $this->logger->notice('Hide faces for @image: no face was found, so the photo is unchanged.', [
        '@image' => $image->getSource(),
      ]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    $mode = $this->configuration['mode'];
    $text = match ($mode) {
      'pixelate' => $this->t('pixelate, strength @strength%', ['@strength' => (int) $this->configuration['strength']]),
      'fill' => $this->t('cover with @colour', ['@colour' => $this->configuration['color']]),
      default => $this->t('blur, strength @strength%', ['@strength' => (int) $this->configuration['strength']]),
    };
    return ['#markup' => $text] + parent::getSummary();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['caution'] = [
      '#type' => 'item',
      '#markup' => $this->t('Only faces the model finds are hidden, and a small face, a profile or a face seen from behind can be missed. Blur and pixelate make a face hard to recognise, not impossible. Only covering it with a colour leaves nothing of it.'),
    ];
    $form['mode'] = [
      '#type' => 'radios',
      '#title' => $this->t('How to hide a face'),
      '#options' => [
        'blur' => $this->t('Blur it'),
        'pixelate' => $this->t('Pixelate it'),
        'fill' => $this->t('Cover it with a colour'),
      ],
      '#default_value' => $this->configuration['mode'],
      '#required' => TRUE,
    ];
    $form['strength'] = [
      '#type' => 'number',
      '#title' => $this->t('Strength'),
      '#description' => $this->t('How strong the blur is, or how coarse the squares are.'),
      '#field_suffix' => '%',
      '#default_value' => (int) $this->configuration['strength'],
      '#min' => 1,
      '#max' => 100,
      '#step' => 1,
      '#states' => ['invisible' => [':input[name="mode"]' => ['value' => 'fill']]],
    ];
    $form['color'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Colour'),
      '#description' => $this->t('A colour as #rrggbb, or <em>white</em> or <em>black</em>.'),
      '#default_value' => $this->configuration['color'],
      '#size' => 12,
      '#maxlength' => 7,
      '#states' => ['visible' => [':input[name="mode"]' => ['value' => 'fill']]],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);
    $mode = $form_state->getValue('mode');
    if (!in_array($mode, self::MODES, TRUE)) {
      $form_state->setErrorByName('mode', $this->t('Choose how to hide a face.'));
      return;
    }
    if ($mode === 'fill') {
      if (!preg_match(Catalog::COLOUR_PATTERN, trim((string) $form_state->getValue('color')))) {
        $form_state->setErrorByName('color', $this->t('Enter a colour as #rrggbb, or white or black.'));
      }
    }
    elseif (!self::isWholeNumber($form_state->getValue('strength'), 1, 100)) {
      $form_state->setErrorByName('strength', $this->t('Enter a whole number from 1 to 100.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $defaults = $this->defaultConfiguration();
    $fill = $form_state->getValue('mode') === 'fill';
    $this->configuration['mode'] = (string) $form_state->getValue('mode');
    // Each of the two settings only means something in its own mode.
    $this->configuration['strength'] = $fill ? $defaults['strength'] : (int) $form_state->getValue('strength');
    $this->configuration['color'] = $fill ? strtolower(trim((string) $form_state->getValue('color'))) : $defaults['color'];
  }

}
