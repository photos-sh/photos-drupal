<?php

declare(strict_types=1);

namespace Drupal\photos_sh\Plugin\ImageEffect;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\image\Attribute\ImageEffect;
use Drupal\photos_sh\Catalog;
use Drupal\photos_sh\Shape;
use PhotosSh\ImageResult;
use PhotosSh\Input;
use PhotosSh\Photos;

/**
 * Expands the canvas to a shape, continuing the photo or with a colour.
 *
 * The image keeps its format (png, jpg or webp; anything else becomes png).
 * One side grows and the photo stays in the middle, so the size is known in
 * advance.
 */
#[ImageEffect(
  id: 'photos_sh_expand',
  label: new TranslatableMarkup('Expand to a shape (Photos.sh)'),
  description: new TranslatableMarkup('Adds room around the photo to reach a shape, using the Photos.sh API.'),
)]
class ExpandImageEffect extends PhotosShEffectBase {

  use ShapeEffectTrait;

  public const FILLS = ['inpaint', 'colour'];

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['shape' => '1:1', 'custom' => '', 'fill' => 'inpaint', 'colour' => '#ffffff'];
  }

  /**
   * {@inheritdoc}
   */
  protected function callApi(Photos $client, Input $input, string $extension): ImageResult {
    $colour = $this->configuration['fill'] === 'colour';
    return $client->expand(
      $input,
      aspect: $this->aspect(),
      fill: $colour ? (string) $this->configuration['colour'] : 'inpaint',
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
  public function transformDimensions(array &$dimensions, $uri): void {
    if (empty($dimensions['width']) || empty($dimensions['height'])) {
      $dimensions['width'] = $dimensions['height'] = NULL;
      return;
    }
    [$dimensions['width'], $dimensions['height']] = Shape::expandSize((int) $dimensions['width'], (int) $dimensions['height'], $this->aspect());
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    $fill = $this->configuration['fill'] === 'colour'
      ? $this->t('colour @colour', ['@colour' => $this->configuration['colour']])
      : $this->t('continued by inpainting');
    return ['#markup' => $this->aspect() . ', ' . $fill] + parent::getSummary();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['caution'] = [
      '#type' => 'item',
      '#markup' => $this->t('Inpainting continues a plain or softly textured background well. It does not invent content: a subject cut off at the edge stays cut off, and a busy scene turns into a smear. Each side can grow by at most the photo’s own width or height. A derivative is made when first requested and can take seconds.'),
    ];
    $this->addShapeFields($form);
    $form['fill'] = [
      '#type' => 'radios',
      '#title' => $this->t('New area'),
      '#options' => [
        'inpaint' => $this->t('Continue the photo by inpainting'),
        'colour' => $this->t('Fill with a colour'),
      ],
      '#default_value' => $this->configuration['fill'],
      '#required' => TRUE,
    ];
    $form['colour'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Colour'),
      '#description' => $this->t('A colour as #rrggbb, or <em>white</em> or <em>black</em>.'),
      '#default_value' => $this->configuration['colour'],
      '#size' => 12,
      '#maxlength' => 7,
      '#states' => ['visible' => [':input[name="fill"]' => ['value' => 'colour']]],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);
    $this->validateShapeFields($form_state);
    $fill = $form_state->getValue('fill');
    if (!in_array($fill, self::FILLS, TRUE)) {
      $form_state->setErrorByName('fill', $this->t('Choose how to fill the new area.'));
    }
    elseif ($fill === 'colour' && !preg_match(Catalog::COLOUR_PATTERN, trim((string) $form_state->getValue('colour')))) {
      $form_state->setErrorByName('colour', $this->t('Enter a colour as #rrggbb, or white or black.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $this->submitShapeFields($form_state);
    $colour = $form_state->getValue('fill') === 'colour';
    $this->configuration['fill'] = $colour ? 'colour' : 'inpaint';
    $this->configuration['colour'] = $colour
      ? strtolower(trim((string) $form_state->getValue('colour')))
      : $this->defaultConfiguration()['colour'];
  }

}
