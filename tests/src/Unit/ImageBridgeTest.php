<?php

declare(strict_types=1);

namespace Drupal\Tests\photos_sh\Unit;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Image\ImageInterface;
use Drupal\photos_sh\Exception\ImageBridgeException;
use Drupal\photos_sh\ImageBridge;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests how the bridge treats toolkits it does not support.
 *
 * @coversDefaultClass \Drupal\photos_sh\ImageBridge
 * @group photos_sh
 */
#[Group('photos_sh')]
class ImageBridgeTest extends UnitTestCase {

  /**
   * Builds an image that reports another toolkit.
   */
  protected function image(string $toolkit): ImageInterface {
    $image = $this->createMock(ImageInterface::class);
    $image->method('isValid')->willReturn(TRUE);
    $image->method('getToolkitId')->willReturn($toolkit);
    return $image;
  }

  /**
   * Sending from an unsupported toolkit fails, naming the toolkit.
   */
  public function testExportNamesTheToolkit(): void {
    $bridge = new ImageBridge($this->createMock(FileSystemInterface::class));
    $this->expectException(ImageBridgeException::class);
    $this->expectExceptionMessage('The "imagick" image toolkit is not supported; use GD or ImageMagick.');
    $bridge->export($this->image('imagick'));
  }

  /**
   * Loading into an unsupported toolkit fails, naming the toolkit.
   */
  public function testImportNamesTheToolkit(): void {
    $bridge = new ImageBridge($this->createMock(FileSystemInterface::class));
    $this->expectException(ImageBridgeException::class);
    $this->expectExceptionMessage('"vips" image toolkit');
    $bridge->import($this->image('vips'), 'bytes', 'png');
  }

  /**
   * An invalid image is refused.
   */
  public function testInvalidImage(): void {
    $image = $this->createMock(ImageInterface::class);
    $image->method('isValid')->willReturn(FALSE);
    $bridge = new ImageBridge($this->createMock(FileSystemInterface::class));
    $this->expectException(ImageBridgeException::class);
    $bridge->export($image);
  }

}
