<?php

declare(strict_types=1);

namespace Drupal\photos_sh;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Shapes (aspect ratios) for crop and expand, and the sizes they give.
 *
 * The sums follow the engine's, including its rounding, so a size worked out
 * here is the size of the derivative.
 */
final class Shape {

  /**
   * The key of the "custom" choice.
   */
  public const CUSTOM = 'custom';

  /**
   * The shapes offered.
   *
   * @return array<string, \Drupal\Core\StringTranslation\TranslatableMarkup>
   *   Labels keyed by the shape as width:height, and "custom".
   */
  public static function options(): array {
    return [
      '1:1' => new TranslatableMarkup('1:1, square'),
      '4:5' => new TranslatableMarkup('4:5, portrait'),
      '5:4' => new TranslatableMarkup('5:4, landscape'),
      '3:2' => new TranslatableMarkup('3:2, landscape'),
      '2:3' => new TranslatableMarkup('2:3, portrait'),
      '4:3' => new TranslatableMarkup('4:3, landscape'),
      '16:9' => new TranslatableMarkup('16:9, wide'),
      '9:16' => new TranslatableMarkup('9:16, tall'),
      self::CUSTOM => new TranslatableMarkup('Custom'),
    ];
  }

  /**
   * Whether a custom shape is written as width:height and is one the API takes.
   *
   * Whole numbers up to 9999, with neither side more than 20 times the other.
   */
  public static function isValidCustom(string $custom): bool {
    if (!preg_match('/^(\d{1,4}):(\d{1,4})$/', trim($custom), $m)) {
      return FALSE;
    }
    $w = (int) $m[1];
    $h = (int) $m[2];
    return $w > 0 && $h > 0 && $w <= 20 * $h && $h <= 20 * $w;
  }

  /**
   * The shape to send to the API.
   *
   * @param string $shape
   *   A key of ::options().
   * @param string $custom
   *   The custom shape, used when $shape is "custom".
   *
   * @return string|null
   *   Width:height, or NULL when the choice is not valid.
   */
  public static function aspect(string $shape, string $custom): ?string {
    if ($shape === self::CUSTOM) {
      if (!self::isValidCustom($custom)) {
        return NULL;
      }
      [$w, $h] = explode(':', trim($custom));
      return (int) $w . ':' . (int) $h;
    }
    return isset(self::options()[$shape]) ? $shape : NULL;
  }

  /**
   * The size of the largest crop of a shape (what crop gives without zoom).
   *
   * @return array{0: int, 1: int}
   *   Width and height.
   */
  public static function cropSize(int $width, int $height, string $aspect): array {
    $ratio = self::ratio($aspect);
    if ($width / $height > $ratio) {
      $w = $height * $ratio;
      $h = (float) $height;
    }
    else {
      $w = (float) $width;
      $h = $width / $ratio;
    }
    return [
      max(1, min($width, (int) round($w))),
      max(1, min($height, (int) round($h))),
    ];
  }

  /**
   * The size of a photo expanded to a shape (one side grows, never shrinks).
   *
   * @return array{0: int, 1: int}
   *   Width and height.
   */
  public static function expandSize(int $width, int $height, string $aspect): array {
    $ratio = self::ratio($aspect);
    if ($width / $height < $ratio) {
      return [max($width, (int) round($height * $ratio)), $height];
    }
    return [$width, max($height, (int) round($width / $ratio))];
  }

  /**
   * A shape as a number.
   */
  protected static function ratio(string $aspect): float {
    [$w, $h] = array_map('intval', explode(':', $aspect));
    return max($w, 1) / max($h, 1);
  }

}
