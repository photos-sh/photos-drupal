<?php

declare(strict_types=1);

namespace Drupal\photos_sh\Form;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\photos_sh\ConnectionStatus;
use Drupal\photos_sh\PhotosClientFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Settings for the Photos.sh connection.
 */
class SettingsForm extends ConfigFormBase {

  final public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typedConfigManager,
    protected PhotosClientFactory $clientFactory,
    protected ConnectionStatus $connectionStatus,
    protected ModuleHandlerInterface $moduleHandler,
  ) {
    parent::__construct($config_factory, $typedConfigManager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('photos_sh.client_factory'),
      $container->get('photos_sh.connection_status'),
      $container->get('module_handler'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'photos_sh_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['photos_sh.settings'];
  }

  /**
   * Whether settings.php overrides a setting.
   */
  protected function isOverridden(string $name): bool {
    return $this->configFactory->get('photos_sh.settings')->hasOverrides($name);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    // What is in effect: the saved values with settings.php overrides.
    $config = $this->configFactory->get('photos_sh.settings');
    $status = $this->connectionStatus->check();

    $form['status'] = [
      '#type' => 'item',
      '#title' => $this->t('Status'),
      '#markup' => $status['message'],
      '#wrapper_attributes' => ['class' => ['photos-sh-status', 'photos-sh-status--' . $status['severity']]],
    ];

    $source = $this->clientFactory->getApiKeySource();
    $has_key_module = $this->moduleHandler->moduleExists('key');
    if ($source === PhotosClientFactory::SOURCE_ENV) {
      $form['key_env'] = [
        '#type' => 'item',
        '#title' => $this->t('API key'),
        '#markup' => $this->t('Set by the %name environment variable, which takes precedence over everything below.', ['%name' => PhotosClientFactory::ENV_API_KEY]),
      ];
    }
    elseif ($has_key_module) {
      $form['key_id'] = [
        '#type' => 'key_select',
        '#title' => $this->t('API key'),
        '#description' => $this->t("A key from the Key module that holds your Photos.sh API key. Create API keys on your organisation's API keys page in the Photos.sh console."),
        '#default_value' => $config->get('key_id'),
        '#empty_option' => $this->t('- None -'),
        '#disabled' => $this->isOverridden('key_id'),
      ];
    }
    else {
      $saved = (string) $config->get('api_key');
      $form['api_key'] = [
        '#type' => 'password',
        '#title' => $this->t('API key'),
        '#description' => $saved !== ''
          ? $this->t('A key ending in %tail is saved. Enter a new one to replace it.', ['%tail' => substr($saved, -4)])
          : $this->t("Create API keys on your organisation's API keys page in the Photos.sh console. For better security set the %name environment variable, or install the Key module, instead of storing the key in configuration.", ['%name' => PhotosClientFactory::ENV_API_KEY]),
        '#maxlength' => 255,
        '#attributes' => ['autocomplete' => 'new-password'],
        '#disabled' => $this->isOverridden('api_key'),
      ];
      if ($saved !== '' && !$this->isOverridden('api_key')) {
        $form['clear_key'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Remove the saved key'),
        ];
      }
    }

    $form['advanced'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced'),
      '#open' => $this->isOverridden('base_url') || $config->get('base_url') !== 'https://api.photos.sh',
    ];
    $form['advanced']['base_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Base URL'),
      '#description' => $this->t('The API address. Leave the default unless you run your own engine or are testing.'),
      '#default_value' => $config->get('base_url'),
      '#required' => TRUE,
      '#disabled' => $this->isOverridden('base_url'),
    ];
    $form['advanced']['timeout'] = [
      '#type' => 'number',
      '#title' => $this->t('Timeout'),
      '#description' => $this->t('Seconds to wait for the API. A derivative is made while the page is requested, so keep this below your PHP time limit.'),
      '#field_suffix' => $this->t('seconds'),
      '#default_value' => $config->get('timeout'),
      '#min' => 1,
      '#max' => 600,
      '#step' => 1,
      '#required' => TRUE,
      '#disabled' => $this->isOverridden('timeout'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    $url = rtrim(trim((string) $form_state->getValue('base_url')), '/');
    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    if ($url !== '' && (!in_array($scheme, ['http', 'https'], TRUE) || !UrlHelper::isValid($url, TRUE))) {
      $form_state->setErrorByName('base_url', $this->t('Enter a full http:// or https:// address.'));
    }
    $timeout = $form_state->getValue('timeout');
    if ($timeout !== NULL && (!is_numeric($timeout) || (int) $timeout < 1 || (int) $timeout > 600)) {
      $form_state->setErrorByName('timeout', $this->t('Enter a timeout between 1 and 600 seconds.'));
    }
    $key = (string) $form_state->getValue('api_key');
    if ($key !== '' && !preg_match('/^\S{8,255}$/', trim($key))) {
      $form_state->setErrorByName('api_key', $this->t('The API key is at least 8 characters and has no spaces.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->config('photos_sh.settings');
    if (!$this->isOverridden('base_url')) {
      $config->set('base_url', rtrim(trim((string) $form_state->getValue('base_url')), '/'));
    }
    if (!$this->isOverridden('timeout')) {
      $config->set('timeout', (int) $form_state->getValue('timeout'));
    }
    if ($form_state->hasValue('key_id') && !$this->isOverridden('key_id')) {
      $config->set('key_id', (string) $form_state->getValue('key_id'));
    }
    if (!$this->isOverridden('api_key')) {
      $key = trim((string) $form_state->getValue('api_key'));
      if ($key !== '') {
        $config->set('api_key', $key);
      }
      elseif ($form_state->getValue('clear_key')) {
        $config->set('api_key', '');
      }
    }
    $config->save();

    parent::submitForm($form, $form_state);
  }

}
