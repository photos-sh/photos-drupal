<?php

declare(strict_types=1);

namespace Drupal\Tests\photos_sh\Kernel;

use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\Form\FormState;
use Drupal\photos_sh\Form\SettingsForm;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests settings, schema, form, client service and status report.
 *
 * @group photos_sh
 */
#[Group('photos_sh')]
#[RunTestsInSeparateProcesses]
class SettingsTest extends PhotosShKernelTestBase {

  /**
   * The install defaults match the schema and the documented defaults.
   */
  public function testInstallDefaultsAndSchema(): void {
    // setUp() overrides the key and URL; read what the install config gave.
    $defaults = \Drupal::service('config.storage')->read('photos_sh.settings');
    $this->assertSame('', $defaults['api_key'] === self::API_KEY ? '' : $defaults['api_key']);
    $this->assertSame(60, $defaults['timeout']);
    $this->assertSame('', $defaults['key_id']);
    $this->assertConfigSchema($this->container->get('config.typed'), 'photos_sh.settings', $this->config('photos_sh.settings')->get());
  }

  /**
   * Every effect's configuration has a schema.
   */
  public function testEffectSchemas(): void {
    $typed = $this->container->get('config.typed');
    foreach (['remove_background', 'upscale', 'remove_text', 'fix', 'preset'] as $name) {
      $this->assertTrue($typed->hasConfigSchema('image.effect.photos_sh_' . $name), $name);
    }
  }

  /**
   * The permission exists and is restricted.
   */
  public function testPermission(): void {
    $permissions = $this->container->get('user.permissions')->getPermissions();
    $this->assertArrayHasKey('administer photos_sh', $permissions);
    $this->assertTrue($permissions['administer photos_sh']['restrict access']);
  }

  /**
   * The route and menu link exist.
   */
  public function testRouteAndMenuLink(): void {
    $this->assertSame('/admin/config/media/photos-sh', $this->container->get('router.route_provider')->getRouteByName('photos_sh.settings')->getPath());
    $links = $this->container->get('plugin.manager.menu.link')->getDefinitions();
    $this->assertSame('system.admin_config_media', $links['photos_sh.settings']['parent']);
  }

  /**
   * The form shows the account, and saves valid values.
   */
  public function testFormSavesValues(): void {
    $this->queue(200, '{"account":"acme","credits":42}');
    $this->queue(200, '{"account":"acme","credits":42}');
    $form_state = (new FormState())->setValues([
      'api_key' => ' new-key-0123456789 ',
      'base_url' => 'http://127.0.0.1:8098/api/',
      'timeout' => '90',
    ]);
    $this->container->get('form_builder')->submitForm(SettingsForm::class, $form_state);

    $this->assertSame([], $form_state->getErrors());
    $config = $this->config('photos_sh.settings');
    $this->assertSame('new-key-0123456789', $config->get('api_key'));
    $this->assertSame('http://127.0.0.1:8098/api', $config->get('base_url'));
    $this->assertSame(90, $config->get('timeout'));
  }

  /**
   * Leaving the key field empty keeps the saved key; the checkbox removes it.
   */
  public function testKeyKeptOrRemoved(): void {
    for ($i = 0; $i < 4; $i++) {
      $this->queue(200, '{"account":"acme","credits":42}');
    }
    $values = ['api_key' => '', 'base_url' => 'http://engine.test/api', 'timeout' => '60'];
    $form_state = (new FormState())->setValues($values);
    $this->container->get('form_builder')->submitForm(SettingsForm::class, $form_state);
    $this->assertSame(self::API_KEY, $this->config('photos_sh.settings')->get('api_key'));

    $form_state = (new FormState())->setValues($values + ['clear_key' => 1]);
    $this->container->get('form_builder')->submitForm(SettingsForm::class, $form_state);
    $this->assertSame('', $this->config('photos_sh.settings')->get('api_key'));
  }

  /**
   * Bad values are refused.
   */
  public function testFormValidation(): void {
    for ($i = 0; $i < 2; $i++) {
      $this->queue(200, '{"account":"acme","credits":42}');
    }
    $form_state = (new FormState())->setValues([
      'api_key' => 'has spaces in it',
      'base_url' => 'ftp://engine.test/api',
      'timeout' => '0',
    ]);
    $this->container->get('form_builder')->submitForm(SettingsForm::class, $form_state);
    $errors = $form_state->getErrors();
    $this->assertArrayHasKey('api_key', $errors);
    $this->assertArrayHasKey('base_url', $errors);
    $this->assertArrayHasKey('timeout', $errors);
    $this->assertSame(self::API_KEY, $this->config('photos_sh.settings')->get('api_key'));
  }

  /**
   * The form's status line names the account; the key is not printed.
   */
  public function testFormShowsStatusWithoutKey(): void {
    $this->queue(200, '{"account":"acme","credits":42}');
    $form_state = new FormState();
    $form = $this->container->get('form_builder')->buildForm(SettingsForm::class, $form_state);
    $this->assertSame('Connected to account acme, 42 credits left.', (string) $form['status']['#markup']);
    $this->assertStringNotContainsString(self::API_KEY, json_encode($form['api_key']));
    $this->assertStringContainsString(substr(self::API_KEY, -4), (string) $form['api_key']['#description']);
  }

  /**
   * The client service is built from the settings and works.
   */
  public function testClientService(): void {
    $this->queue(200, '{"account":"acme","credits":7}');
    $client = $this->container->get('photos_sh.client');
    $this->assertSame(['account' => 'acme', 'credits' => 7], $client->account());
    $request = $this->requests()[0];
    $this->assertSame('http://engine.test/api/v1/account', (string) $request->getUri());
    $this->assertSame('Bearer ' . self::API_KEY, $request->getHeaderLine('Authorization'));
  }

  /**
   * The environment variable wins over the saved key.
   */
  public function testEnvironmentKeyWins(): void {
    $this->queue(200, '{"account":"acme","credits":7}');
    putenv('PHOTOS_API_KEY=env-key-0123456789');
    try {
      $this->container->get('photos_sh.client')->account();
    }
    finally {
      putenv('PHOTOS_API_KEY');
    }
    $this->assertSame('Bearer env-key-0123456789', $this->requests()[0]->getHeaderLine('Authorization'));
  }

  /**
   * The status report has a Photos.sh line with the account.
   */
  public function testStatusReport(): void {
    $this->queue(200, '{"account":"acme","credits":42}');
    $requirements = $this->requirements();
    $this->assertSame('Connected to account acme, 42 credits left.', (string) $requirements['photos_sh']['value']);
    $this->assertSame([], photos_sh_requirements('install'));
  }

  /**
   * A refused key shows as an error on the status report.
   */
  public function testStatusReportKeyRefused(): void {
    $this->queueError(401, 'unauthorized', 'bad key');
    $requirements = $this->requirements();
    $this->assertStringContainsString('refused the API key', (string) $requirements['photos_sh']['value']);
    $error = enum_exists(RequirementSeverity::class) ? RequirementSeverity::Error : REQUIREMENT_ERROR;
    $this->assertSame($error, $requirements['photos_sh']['severity']);
  }

  /**
   * The requirements the way this Drupal version collects them.
   *
   * @return array<string, array<string, mixed>>
   *   The photos_sh requirements.
   */
  protected function requirements(): array {
    if (version_compare(\Drupal::VERSION, '11.3', '>=')) {
      return $this->container->get('module_handler')->invoke('photos_sh', 'runtime_requirements') ?? [];
    }
    return photos_sh_requirements('runtime');
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    require_once dirname(__DIR__, 3) . '/photos_sh.install';
  }

}
