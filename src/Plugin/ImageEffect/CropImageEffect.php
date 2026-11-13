<?php

declare(strict_types=1);

namespace Drupal\photos_sh\Plugin\ImageEffect;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Image\ImageInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\image\Attribute\ImageEffect;
use Drupal\photos_sh\Shape;
use PhotosSh\CropResult;
use PhotosSh\ImageResult;
use PhotosSh\Input;
use PhotosSh\Photos;

/**
 * Crops to a shape around the subject or the faces, keeping the background.
 *
 * The image keeps its format (png, jpg or webp; anything else becomes png).
 * Without "close" the crop is the largest of that shape, so its size is known
 * in advance. Close to the subject it depends on where the subject is.
 */
#[ImageEffect(
  id: 'photos_sh_crop',
  label: new TranslatableMarkup('Crop to a shape around the subject (Photos.sh)'),
  description: new TranslatableMarkup('Crops to a shape, keeping the subject or the faces in it, using the Photos.sh API.'),
)]
class CropImageEffect extends PhotosShEffectBase {

  use ShapeEffectTrait;

  public const FOCUS = ['subject', 'face'];

  /**
   * The room left around the faces in a close crop, for hair and chin.
   */
  protected const FACE_PADDING = 0.6;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['shape' => '1:1', 'custom' => '', 'focus' => 'subject', 'close' => FALSE];
  }

  /**
   * {@inheritdoc}
   */
  protected function callApi(Photos $client, Input $input, string $extension): ImageResult {
    $close = !empty($this->configuration['close']);
    $face = $this->configuration['focus'] === 'face';
    return $client->crop(
      $input,
      aspect: $this->aspect(),
      zoom: $close ? TRUE : NULL,
      padding: $close && $face ? self::FACE_PADDING : NULL,
      focus: $face ? 'face' : NULL,
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
    // A close crop is as big as the subject, which only the API knows.
    $known = empty($this->configuration['close'])
      && !empty($dimensions['width']) && !empty($dimensions['height']);
    if (!$known) {
      $dimensions['width'] = $dimensions['height'] = NULL;
      return;
    }
    [$dimensions['width'], $dimensions['height']] = Shape::cropSize((int) $dimensions['width'], (int) $dimensions['height'], $this->aspect());
  }

  /**
   * {@inheritdoc}
   */
  protected function logResult(ImageResult $result, ImageInterface $image): void {
    if ($result instanceof CropResult && $result->subjectFound === FALSE) {
      $this->logger->notice('Crop for @image: nothing to centre on was found, so the crop is in the middle.', [
        '@image' => $image->getSource(),
      ]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    $parts = [$this->aspect()];
    $parts[] = $this->configuration['focus'] === 'face' ? $this->t('around the faces') : $this->t('around the subject');
    $parts[] = !empty($this->configuration['close']) ? $this->t('close') : $this->t('largest crop');
    return ['#markup' => implode(', ', $parts)] + parent::getSummary();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $this->addShapeFields($form);
    $form['focus'] = [
      '#type' => 'radios',
      '#title' => $this->t('Keep in the crop'),
      '#description' => $this->t('The subject is found by the model that removes backgrounds. All the faces in a photo count as one area, so a group stays whole. If nothing is found, the crop is in the middle.'),
      '#options' => [
        'subject' => $this->t('The subject'),
        'face' => $this->t('The faces'),
      ],
      '#default_value' => $this->configuration['focus'],
      '#required' => TRUE,
    ];
    $form['close'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Close'),
      '#description' => $this->t('Crop tightly around what is kept, with some room around it. Without this the crop is the largest one of that shape. A close crop can be small, and its size is not known until the image is made.'),
      '#default_value' => !empty($this->configuration['close']),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);
    $this->validateShapeFields($form_state);
    if (!in_array($form_state->getValue('focus'), self::FOCUS, TRUE)) {
      $form_state->setErrorByName('focus', $this->t('Choose what to keep in the crop.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $this->submitShapeFields($form_state);
    $this->configuration['focus'] = (string) $form_state->getValue('focus');
    $this->configuration['close'] = (bool) $form_state->getValue('close');
  }

}
