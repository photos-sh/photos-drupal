<?php

declare(strict_types=1);

namespace Drupal\photos_sh\Plugin\ImageEffect;

use Drupal\Core\Form\FormStateInterface;

/**
 * Base for the effects with one setting: how strong, from 1 to 100.
 *
 * The image keeps its size and format (png, jpg or webp; anything else
 * becomes png).
 */
abstract class StrengthImageEffectBase extends PhotosShEffectBase {

  /**
   * The strength a new effect starts with.
   */
  protected const DEFAULT_STRENGTH = 100;

  /**
   * What the strength does, for the field's description.
   */
  abstract protected function strengthDescription(): string;

  /**
   * One plain sentence of caution for the form, or an empty string.
   */
  protected function caution(): string {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['strength' => static::DEFAULT_STRENGTH];
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
    return ['#markup' => $this->t('strength @strength%', ['@strength' => (int) $this->configuration['strength']])] + parent::getSummary();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $caution = $this->caution();
    if ($caution !== '') {
      $form['caution'] = [
        '#type' => 'item',
        '#markup' => $caution,
      ];
    }
    $form['strength'] = [
      '#type' => 'number',
      '#title' => $this->t('Strength'),
      '#description' => $this->strengthDescription(),
      '#field_suffix' => '%',
      '#default_value' => (int) $this->configuration['strength'],
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
    if (!self::isWholeNumber($form_state->getValue('strength'), 1, 100)) {
      $form_state->setErrorByName('strength', $this->t('Enter a whole number from 1 to 100.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['strength'] = (int) $form_state->getValue('strength');
  }

}
