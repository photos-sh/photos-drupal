<?php

declare(strict_types=1);

namespace Drupal\Tests\photos_sh\Unit;

use Drupal\photos_sh\Shape;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the shapes for crop and expand and the sizes they give.
 *
 * @coversDefaultClass \Drupal\photos_sh\Shape
 * @group photos_sh
 */
#[Group('photos_sh')]
class ShapeTest extends UnitTestCase {

  /**
   * Custom shapes and whether the API takes them.
   */
  public static function provideCustom(): \Generator {
    yield 'plain' => ['7:5', TRUE];
    yield 'spaces around' => [' 7:5 ', TRUE];
    yield 'leading zeros' => ['07:05', TRUE];
    yield 'widest' => ['20:1', TRUE];
    yield 'tallest' => ['1:20', TRUE];
    yield 'too wide' => ['21:1', FALSE];
    yield 'too tall' => ['1:21', FALSE];
    yield 'zero width' => ['0:5', FALSE];
    yield 'zero height' => ['5:0', FALSE];
    yield 'a number' => ['1.5', FALSE];
    yield 'decimals' => ['1.5:1', FALSE];
    yield 'negative' => ['-4:5', FALSE];
    yield 'words' => ['wide', FALSE];
    yield 'empty' => ['', FALSE];
    yield 'too many digits' => ['10000:9999', FALSE];
    yield 'three parts' => ['1:2:3', FALSE];
  }

  /**
   * @covers ::isValidCustom
   * @dataProvider provideCustom
   */
  #[DataProvider('provideCustom')]
  public function testIsValidCustom(string $custom, bool $valid): void {
    $this->assertSame($valid, Shape::isValidCustom($custom));
  }

  /**
   * The shape sent to the API.
   *
   * @covers ::aspect
   */
  public function testAspect(): void {
    $this->assertSame('4:5', Shape::aspect('4:5', ''));
    // The custom text is ignored for a fixed shape.
    $this->assertSame('4:5', Shape::aspect('4:5', '7:5'));
    $this->assertSame('7:5', Shape::aspect('custom', ' 07:05 '));
    $this->assertNull(Shape::aspect('custom', 'wide'));
    $this->assertNull(Shape::aspect('7:3', ''));
    $this->assertNull(Shape::aspect('', ''));
  }

  /**
   * The options include the shapes the task asks for.
   *
   * @covers ::options
   */
  public function testOptions(): void {
    $options = array_keys(Shape::options());
    foreach (['1:1', '4:5', '16:9', 'custom'] as $shape) {
      $this->assertContains($shape, $options);
    }
    // Every fixed shape is one the API takes.
    foreach ($options as $shape) {
      if ($shape !== Shape::CUSTOM) {
        $this->assertTrue(Shape::isValidCustom($shape), $shape);
      }
    }
  }

  /**
   * Sizes of the largest crop (the engine's sum, with its rounding).
   */
  public static function provideCrops(): \Generator {
    yield 'wide photo to 4:5' => [80, 60, '4:5', [48, 60]];
    yield 'wide photo to a square' => [1600, 1000, '1:1', [1000, 1000]];
    yield 'square to 16:9' => [1600, 1600, '16:9', [1600, 900]];
    yield 'rounds' => [1000, 1000, '16:9', [1000, 563]];
    yield 'already that shape' => [1600, 900, '16:9', [1600, 900]];
    yield 'tall to wide' => [600, 4000, '3:2', [600, 400]];
    yield 'to tall' => [1920, 1080, '9:16', [608, 1080]];
    yield 'custom shape' => [100, 100, '7:5', [100, 71]];
    yield 'never zero' => [1, 1000, '20:1', [1, 1]];
  }

  /**
   * @covers ::cropSize
   * @dataProvider provideCrops
   *
   * @param int $width
   *   The photo's width.
   * @param int $height
   *   The photo's height.
   * @param string $aspect
   *   The shape.
   * @param array{0: int, 1: int} $expected
   *   The size of the crop.
   */
  #[DataProvider('provideCrops')]
  public function testCropSize(int $width, int $height, string $aspect, array $expected): void {
    $this->assertSame($expected, Shape::cropSize($width, $height, $aspect));
  }

  /**
   * Sizes after expanding: one side grows, nothing shrinks.
   */
  public static function provideExpands(): \Generator {
    yield 'square from wide' => [80, 60, '1:1', [80, 80]];
    yield 'wide from square' => [1600, 1600, '16:9', [2844, 1600]];
    yield 'tall from wide' => [400, 300, '9:16', [400, 711]];
    yield 'already that shape' => [1600, 900, '16:9', [1600, 900]];
    yield 'already wider' => [400, 300, '1:1', [400, 400]];
    yield 'already taller' => [300, 400, '4:3', [533, 400]];
  }

  /**
   * @covers ::expandSize
   * @dataProvider provideExpands
   *
   * @param int $width
   *   The photo's width.
   * @param int $height
   *   The photo's height.
   * @param string $aspect
   *   The shape.
   * @param array{0: int, 1: int} $expected
   *   The new size.
   */
  #[DataProvider('provideExpands')]
  public function testExpandSize(int $width, int $height, string $aspect, array $expected): void {
    $this->assertSame($expected, Shape::expandSize($width, $height, $aspect));
  }

}
