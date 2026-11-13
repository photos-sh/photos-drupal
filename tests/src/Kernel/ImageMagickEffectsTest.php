<?php

declare(strict_types=1);

namespace Drupal\Tests\photos_sh\Kernel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Process\ExecutableFinder;

/**
 * Runs the effect tests again with the ImageMagick toolkit.
 *
 * Skipped when the ImageMagick module (and the modules it needs) or the
 * ImageMagick binary is missing.
 *
 * @group photos_sh
 */
#[Group('photos_sh')]
#[RunTestsInSeparateProcesses]
class ImageMagickEffectsTest extends EffectsTest {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'file',
    'image',
    'file_mdm',
    'sophron',
    'imagemagick',
    'photos_sh',
  ];

  /**
   * The real directory used as the public file directory.
   */
  protected string $realFiles = '';

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    if ($this->realFiles !== '' && is_dir($this->realFiles)) {
      \Drupal::service('file_system')->deleteRecursive($this->realFiles);
    }
    parent::tearDown();
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    // Skip before Drupal boots: a missing module would fail the boot.
    foreach (['imagemagick', 'file_mdm', 'sophron'] as $module) {
      if (!self::moduleAvailable($module)) {
        $this->markTestSkipped("The $module module is not available.");
      }
    }
    $finder = new ExecutableFinder();
    $magick = $finder->find('magick');
    $convert = $finder->find('convert');
    if ($magick === NULL && $convert === NULL) {
      $this->markTestSkipped('No ImageMagick binary (magick or convert) found.');
    }

    parent::setUp();

    // Kernel tests keep public:// in a virtual file system that the
    // ImageMagick executable cannot read. Use a real directory instead.
    $this->realFiles = sys_get_temp_dir() . '/photos_sh_kernel_' . bin2hex(random_bytes(4));
    mkdir($this->realFiles, 0777, TRUE);
    $this->setSetting('file_public_path', $this->realFiles);
    file_put_contents(self::SOURCE, $this->imageBytes(80, 60, 'jpg'));

    $this->installConfig(['file_mdm', 'sophron', 'imagemagick']);
    $this->config('imagemagick.settings')
      ->set('imagemagick_version', $magick !== NULL ? 'v7' : 'v6')
      ->set('path_to_binaries', dirname($magick ?? $convert) . '/')
      ->save();
    $this->config('system.image')->set('toolkit', 'imagemagick')->save();
  }

  /**
   * Whether a module is in one of the usual module directories.
   */
  protected static function moduleAvailable(string $module): bool {
    $patterns = [
      DRUPAL_ROOT . "/modules/$module/$module.info.yml",
      DRUPAL_ROOT . "/modules/*/$module/$module.info.yml",
      DRUPAL_ROOT . "/modules/*/*/$module/$module.info.yml",
      DRUPAL_ROOT . "/core/modules/$module/$module.info.yml",
    ];
    foreach ($patterns as $pattern) {
      if (glob($pattern) !== []) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * The effects run under the ImageMagick toolkit, not GD.
   */
  public function testToolkitIsImageMagick(): void {
    $image = $this->container->get('image.factory')->get(self::SOURCE);
    $this->assertSame('imagemagick', $image->getToolkitId());
  }

  /**
   * The load operation is registered for ImageMagick only.
   */
  public function testLoadOperationRegistered(): void {
    $definitions = $this->container->get('image.toolkit.operation.manager')->getDefinitions();
    $this->assertSame('imagemagick', $definitions['photos_sh_imagemagick_load']['toolkit']);
    $this->assertSame('gd', $definitions['photos_sh_gd_load']['toolkit']);
  }

}
