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
