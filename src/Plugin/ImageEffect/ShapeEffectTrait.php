<?php

declare(strict_types=1);

namespace Drupal\photos_sh\Plugin\ImageEffect;

use Drupal\Core\Form\FormStateInterface;
use Drupal\photos_sh\Shape;

/**
 * The shape setting that crop and expand share.
 *
 * The configuration has "shape" (a key of Shape::options()) and "custom" (the
 * width:height used when the shape is custom).
 */
trait ShapeEffectTrait {

  /**
   * The shape to send to the API.
   */
  protected function aspect(): string {
    return Shape::aspect((string) $this->configuration['shape'], (string) $this->configuration['custom']) ?? '1:1';
  }

  /**
   * Adds the shape fields to a form.
   *
   * @param array<string, mixed> $form
   *   The form.
   */
  protected function addShapeFields(array &$form): void {
    $form['shape'] = [
      '#type' => 'select',
      '#title' => $this->t('Shape'),
      '#options' => Shape::options(),
      '#default_value' => $this->configuration['shape'],
      '#required' => TRUE,
    ];
    $form['custom'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Custom shape'),
      '#description' => $this->t('Width and height as two whole numbers, like 7:5. Neither may be more than 20 times the other.'),
      '#default_value' => $this->configuration['custom'],
      '#size' => 8,
      '#maxlength' => 9,
      '#states' => ['visible' => [':input[name="shape"]' => ['value' => Shape::CUSTOM]]],
    ];
  }

  /**
   * Checks the shape fields.
   */
  protected function validateShapeFields(FormStateInterface $form_state): void {
    $shape = (string) $form_state->getValue('shape');
    if (!isset(Shape::options()[$shape])) {
      $form_state->setErrorByName('shape', $this->t('Choose a shape.'));
    }
    elseif ($shape === Shape::CUSTOM && !Shape::isValidCustom((string) $form_state->getValue('custom'))) {
      $form_state->setErrorByName('custom', $this->t('Enter the shape as two whole numbers, like 7:5. Neither may be more than 20 times the other.'));
    }
  }

  /**
   * Keeps the shape fields' values.
   */
  protected function submitShapeFields(FormStateInterface $form_state): void {
    $this->configuration['shape'] = (string) $form_state->getValue('shape');
    // The custom shape only means something when it is chosen.
    $this->configuration['custom'] = $this->configuration['shape'] === Shape::CUSTOM
      ? (string) Shape::aspect(Shape::CUSTOM, (string) $form_state->getValue('custom'))
      : '';
  }

}
