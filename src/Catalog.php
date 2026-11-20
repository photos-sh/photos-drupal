<?php

declare(strict_types=1);

namespace Drupal\photos_sh;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * The marketplaces and presets the effects offer.
 *
 * These follow the Photos.sh API reference. A preset or marketplace the API
 * adds later needs a release of this module before it is offered here.
 */
final class Catalog {

  /**
   * What a colour setting may be: #rrggbb, white or black.
   */
  public const COLOUR_PATTERN = '/^(#[0-9a-f]{6}|white|black)$/i';

  /**
   * Positions for a mark on a photo, as the API names them.
   *
   * @return array<string, \Drupal\Core\StringTranslation\TranslatableMarkup>
   *   Labels keyed by the API's position name.
   */
  public static function positions(): array {
    return [
      'top-left' => new TranslatableMarkup('Top left'),
      'top' => new TranslatableMarkup('Top'),
      'top-right' => new TranslatableMarkup('Top right'),
      'left' => new TranslatableMarkup('Left'),
      'center' => new TranslatableMarkup('Centre'),
      'right' => new TranslatableMarkup('Right'),
      'bottom-left' => new TranslatableMarkup('Bottom left'),
      'bottom' => new TranslatableMarkup('Bottom'),
      'bottom-right' => new TranslatableMarkup('Bottom right'),
    ];
  }

  /**
   * Marketplaces the API can fix photos for.
   *
   * @return array<string, \Drupal\Core\StringTranslation\TranslatableMarkup>
   *   Labels keyed by the API's marketplace name.
   */
  public static function marketplaces(): array {
    return [
      'amazon' => new TranslatableMarkup('Amazon'),
      'ebay' => new TranslatableMarkup('eBay'),
      'etsy' => new TranslatableMarkup('Etsy'),
      'shopify' => new TranslatableMarkup('Shopify'),
      'google' => new TranslatableMarkup('Google Merchant Center'),
      'allegro' => new TranslatableMarkup('Allegro'),
      'zalando' => new TranslatableMarkup('Zalando'),
    ];
  }

  /**
   * The model background removal uses when none is chosen.
   */
  public const DEFAULT_MODEL = 'atlas';

  /**
   * The models background removal can find the subject with.
   *
   * Each costs the same.
   *
   * @return array<string, \Drupal\Core\StringTranslation\TranslatableMarkup>
   *   Labels keyed by the API's model name.
   */
  public static function models(): array {
    return [
      'atlas' => new TranslatableMarkup('Atlas (default)'),
      'lumen' => new TranslatableMarkup('Lumen'),
      'muse' => new TranslatableMarkup('Muse'),
    ];
  }

  /**
   * What each background removal model is recommended for.
   *
   * @return array<string, \Drupal\Core\StringTranslation\TranslatableMarkup>
   *   Descriptions keyed by the API's model name.
   */
  public static function modelDescriptions(): array {
    return [
      'atlas' => new TranslatableMarkup('Everyday product photos: a product alone or with its box, on any backdrop.'),
      'lumen' => new TranslatableMarkup("Pale packaging on a light backdrop, such as a cream or wooden box beside a bottle, and close-ups where the product fills the photo. On those, Atlas can keep the bottle and drop the box. It may keep a dark backdrop at the photo's edge, and can cut away part of an open frame or wire stand."),
      'muse' => new TranslatableMarkup('People: portraits, and models wearing the product.'),
    ];
  }

  /**
   * Built-in presets.
   *
   * @return array<string, \Drupal\Core\StringTranslation\TranslatableMarkup>
   *   Labels keyed by the API's preset name.
   */
  public static function presets(): array {
    return [
      'marketplace' => new TranslatableMarkup('Marketplace: white background, square, product fills 85%'),
      'studio' => new TranslatableMarkup('Studio: soft grey gradient and contact shadow'),
      'social' => new TranslatableMarkup('Social: 4:5 post over a blurred copy of the photo'),
      'cutout' => new TranslatableMarkup('Cutout: transparent, cropped to the product'),
      'web' => new TranslatableMarkup('Web: longest side 1600 px, small file'),
    ];
  }

  /**
   * The format each preset normally delivers.
   *
   * Used as the form's starting value for the output format.
   */
  public static function presetFormat(string $preset): string {
    return match ($preset) {
      'cutout' => 'png',
      'studio', 'web' => 'webp',
      default => 'jpg',
    };
  }

  /**
   * Output formats an effect can promise for its derivative.
   *
   * @return array<string, \Drupal\Core\StringTranslation\TranslatableMarkup>
   *   Labels keyed by file extension.
   */
  public static function formats(): array {
    return [
      'jpg' => new TranslatableMarkup('JPEG'),
      'png' => new TranslatableMarkup('PNG'),
      'webp' => new TranslatableMarkup('WebP'),
    ];
  }

}
