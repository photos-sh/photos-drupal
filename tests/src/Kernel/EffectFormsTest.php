<?php

declare(strict_types=1);

namespace Drupal\Tests\photos_sh\Kernel;

use Drupal\Core\Form\FormState;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the effect configuration forms: validation, saving and summaries.
 *
 * @group photos_sh
 */
#[Group('photos_sh')]
#[RunTestsInSeparateProcesses]
class EffectFormsTest extends PhotosShKernelTestBase {

  /**
   * Builds an effect plugin.
   */
  protected function effect(string $id) {
    return $this->container->get('plugin.manager.image.effect')->createInstance($id);
  }

  /**
   * Runs the effect's form validation and returns the field names in error.
   *
   * @param string $id
   *   The effect plugin ID.
   * @param array<string, mixed> $values
   *   The submitted values.
   *
   * @return string[]
   *   Names of the fields with errors.
   */
  protected function errorsFor(string $id, array $values): array {
    $effect = $this->effect($id);
    $form_state = (new FormState())->setValues($values);
    $form = $effect->buildConfigurationForm([], $form_state);
    $effect->validateConfigurationForm($form, $form_state);
    return array_keys($form_state->getErrors());
  }

  /**
   * Invalid submissions and the fields they fail on.
   */
  public static function provideInvalid(): \Generator {
    yield 'colour not hex' => [
      'photos_sh_remove_background',
      ['background' => 'red', 'crop' => 0, 'padding' => 0],
      ['background'],
    ];
    yield 'short hex' => [
      'photos_sh_remove_background',
      ['background' => '#fff', 'crop' => 0, 'padding' => 0],
      ['background'],
    ];
    yield 'scale out of range' => [
      'photos_sh_upscale',
      ['scale' => 8],
      ['scale'],
    ];
    yield 'unknown region' => [
      'photos_sh_remove_text',
      ['region' => 'everything'],
      ['region'],
    ];
    yield 'unknown marketplace' => [
      'photos_sh_fix',
      ['marketplace' => 'ebayy', 'format' => 'jpg'],
      ['marketplace'],
    ];
    yield 'unknown format' => [
      'photos_sh_fix',
      ['marketplace' => 'ebay', 'format' => 'tiff'],
      ['format'],
    ];
    yield 'unknown preset' => [
      'photos_sh_preset',
      ['preset' => 'nope', 'format' => 'png'],
      ['preset'],
    ];
    yield 'enhance without any change' => [
      'photos_sh_enhance',
      ['mode' => 'custom', 'brightness' => 0, 'contrast' => '0', 'saturation' => 0, 'sharpen' => 0],
      ['mode'],
    ];
    yield 'enhance out of range' => [
      'photos_sh_enhance',
      ['mode' => 'custom', 'brightness' => 101, 'contrast' => -101, 'saturation' => 5, 'sharpen' => -1],
      ['brightness', 'contrast', 'sharpen'],
    ];
    yield 'enhance unknown mode' => [
      'photos_sh_enhance',
      ['mode' => 'magic'],
      ['mode'],
    ];
    yield 'denoise strength 0' => ['photos_sh_denoise', ['strength' => 0], ['strength']];
    yield 'colorize strength 101' => ['photos_sh_colorize', ['strength' => 101], ['strength']];
    yield 'restore faces strength not a number' => ['photos_sh_restore_faces', ['strength' => 'high'], ['strength']];
    yield 'hide faces unknown mode' => [
      'photos_sh_blur_faces',
      ['mode' => 'erase', 'strength' => 50, 'color' => 'black'],
      ['mode'],
    ];
    yield 'hide faces bad colour' => [
      'photos_sh_blur_faces',
      ['mode' => 'fill', 'strength' => 50, 'color' => 'red'],
      ['color'],
    ];
    yield 'hide faces strength too high' => [
      'photos_sh_blur_faces',
      ['mode' => 'pixelate', 'strength' => 500, 'color' => 'black'],
      ['strength'],
    ];
    yield 'crop unknown shape' => [
      'photos_sh_crop',
      ['shape' => '7:3', 'custom' => '', 'focus' => 'subject', 'close' => 0],
      ['shape'],
    ];
    yield 'crop custom shape not a ratio' => [
      'photos_sh_crop',
      ['shape' => 'custom', 'custom' => 'wide', 'focus' => 'subject', 'close' => 0],
      ['custom'],
    ];
    yield 'crop custom shape too extreme' => [
      'photos_sh_crop',
      ['shape' => 'custom', 'custom' => '30:1', 'focus' => 'subject', 'close' => 0],
      ['custom'],
    ];
    yield 'crop unknown focus' => [
      'photos_sh_crop',
      ['shape' => '1:1', 'custom' => '', 'focus' => 'hands', 'close' => 0],
      ['focus'],
    ];
    yield 'expand colour not hex' => [
      'photos_sh_expand',
      ['shape' => '1:1', 'custom' => '', 'fill' => 'colour', 'colour' => '#fff'],
      ['colour'],
    ];
    yield 'expand unknown fill' => [
      'photos_sh_expand',
      ['shape' => '1:1', 'custom' => '', 'fill' => 'stretch', 'colour' => ''],
      ['fill'],
    ];
    yield 'expand custom shape zero' => [
      'photos_sh_expand',
      ['shape' => 'custom', 'custom' => '0:5', 'fill' => 'inpaint', 'colour' => ''],
      ['custom'],
    ];
    yield 'watermark without text' => [
      'photos_sh_watermark',
      ['text' => '  ', 'position' => 'center', 'size' => 25, 'opacity' => 50],
      ['text'],
    ];
    yield 'watermark text too long' => [
      'photos_sh_watermark',
      ['text' => str_repeat('é', 201), 'position' => 'center', 'size' => 25, 'opacity' => 50],
      ['text'],
    ];
    yield 'watermark bad position, size and opacity' => [
      'photos_sh_watermark',
      ['text' => 'Shop', 'position' => 'middle', 'size' => 0, 'opacity' => 101],
      ['position', 'size', 'opacity'],
    ];
  }

  /**
   * Bad values are refused.
   *
   * @param string $id
   *   The effect plugin ID.
   * @param array<string, mixed> $values
   *   The submitted values.
   * @param string[] $fields
   *   The fields expected to fail.
   *
   * @dataProvider provideInvalid
   */
  #[DataProvider('provideInvalid')]
  public function testInvalidValues(string $id, array $values, array $fields): void {
    $this->assertSame($fields, $this->errorsFor($id, $values));
  }

  /**
   * Good values pass.
   */
  public function testValidValues(): void {
    $id = 'photos_sh_remove_background';
    $white = ['background' => '#FFFFFF', 'crop' => 1, 'padding' => 5];
    $empty = ['background' => '', 'crop' => 0, 'padding' => 0];
    $this->assertSame([], $this->errorsFor($id, $white));
    $this->assertSame([], $this->errorsFor($id, $empty));
    $this->assertSame([], $this->errorsFor('photos_sh_upscale', ['scale' => '4']));
    $this->assertSame([], $this->errorsFor('photos_sh_remove_text', ['region' => 'all']));
    $this->assertSame([], $this->errorsFor('photos_sh_enhance', ['mode' => 'auto']));
    $this->assertSame([], $this->errorsFor('photos_sh_enhance', [
      'mode' => 'custom',
      'brightness' => '-100',
      'contrast' => 0,
      'saturation' => 0,
      'sharpen' => 100,
    ]));
    foreach (['photos_sh_denoise', 'photos_sh_colorize', 'photos_sh_restore_faces'] as $id) {
      $this->assertSame([], $this->errorsFor($id, ['strength' => '1']), $id);
      $this->assertSame([], $this->errorsFor($id, ['strength' => 100]), $id);
    }
    $this->assertSame([], $this->errorsFor('photos_sh_blur_faces', [
      'mode' => 'blur',
      'strength' => 50,
      'color' => '',
    ]));
    // The colour only matters for covering.
    $this->assertSame([], $this->errorsFor('photos_sh_blur_faces', [
      'mode' => 'fill',
      'strength' => '',
      'color' => '#00ff00',
    ]));
    $this->assertSame([], $this->errorsFor('photos_sh_crop', [
      'shape' => '16:9',
      'custom' => '',
      'focus' => 'face',
      'close' => 1,
    ]));
    $this->assertSame([], $this->errorsFor('photos_sh_crop', [
      'shape' => 'custom',
      'custom' => ' 7:5 ',
      'focus' => 'subject',
      'close' => 0,
    ]));
    $this->assertSame([], $this->errorsFor('photos_sh_expand', [
      'shape' => '4:5',
      'custom' => '',
      'fill' => 'inpaint',
      'colour' => '',
    ]));
    $this->assertSame([], $this->errorsFor('photos_sh_expand', [
      'shape' => '4:5',
      'custom' => '',
      'fill' => 'colour',
      'colour' => 'Black',
    ]));
    $this->assertSame([], $this->errorsFor('photos_sh_watermark', [
      'text' => 'Shop',
      'position' => 'top',
      'size' => '100',
      'opacity' => '1',
    ]));
    $this->assertSame([], $this->errorsFor('photos_sh_watermark', [
      'text' => str_repeat('é', 200),
      'position' => 'top',
      'size' => 1,
      'opacity' => 100,
    ]));
  }

  /**
   * Submitting stores normalised configuration.
   */
  public function testSubmitStoresConfiguration(): void {
    $effect = $this->effect('photos_sh_remove_background');
    $form_state = (new FormState())->setValues([
      'background' => ' #FFFFFF ',
      'crop' => 0,
      'padding' => 30,
      'studio' => 1,
    ]);
    $form = [];
    $effect->submitConfigurationForm($form, $form_state);
    // Padding only means something with cropping.
    $this->assertSame([
      'background' => '#ffffff',
      'crop' => FALSE,
      'padding' => 0,
      'studio' => TRUE,
    ], $effect->getConfiguration()['data']);

    $effect = $this->effect('photos_sh_upscale');
    $effect->submitConfigurationForm($form, (new FormState())->setValues(['scale' => '3']));
    $this->assertSame(['scale' => 3], $effect->getConfiguration()['data']);
  }

  /**
   * Submitting the new effects stores clean configuration.
   */
  public function testSubmitStoresConfigurationOfNewEffects(): void {
    $form = [];
    $submit = function (string $id, array $values) use (&$form): array {
      $effect = $this->effect($id);
      $effect->submitConfigurationForm($form, (new FormState())->setValues($values));
      return $effect->getConfiguration()['data'];
    };
    // Automatic forgets the manual numbers.
    $this->assertSame(
      ['mode' => 'auto', 'brightness' => 0, 'contrast' => 0, 'saturation' => 0, 'sharpen' => 0],
      $submit('photos_sh_enhance', [
        'mode' => 'auto',
        'brightness' => '40',
        'contrast' => 0,
        'saturation' => 0,
        'sharpen' => 0,
      ]),
    );
    $this->assertSame(
      ['mode' => 'custom', 'brightness' => -10, 'contrast' => 0, 'saturation' => 0, 'sharpen' => 25],
      $submit('photos_sh_enhance', [
        'mode' => 'custom',
        'brightness' => '-10',
        'contrast' => '0',
        'saturation' => '0',
        'sharpen' => '25',
      ]),
    );
    $this->assertSame(['strength' => 40], $submit('photos_sh_denoise', ['strength' => '40']));
    $this->assertSame(
      ['mode' => 'fill', 'strength' => 50, 'color' => '#aabbcc'],
      $submit('photos_sh_blur_faces', ['mode' => 'fill', 'strength' => '90', 'color' => ' #AABBCC ']),
    );
    $this->assertSame(
      ['mode' => 'pixelate', 'strength' => 90, 'color' => 'black'],
      $submit('photos_sh_blur_faces', ['mode' => 'pixelate', 'strength' => '90', 'color' => '#ffffff']),
    );
    // A custom shape is written the same way every time, and dropped for a
    // fixed shape.
    $this->assertSame(
      ['shape' => 'custom', 'custom' => '7:5', 'focus' => 'face', 'close' => TRUE],
      $submit('photos_sh_crop', ['shape' => 'custom', 'custom' => ' 07:05 ', 'focus' => 'face', 'close' => 1]),
    );
    $this->assertSame(
      ['shape' => '4:5', 'custom' => '', 'focus' => 'subject', 'close' => FALSE],
      $submit('photos_sh_crop', ['shape' => '4:5', 'custom' => '7:5', 'focus' => 'subject', 'close' => 0]),
    );
    $this->assertSame(
      ['shape' => '16:9', 'custom' => '', 'fill' => 'inpaint', 'colour' => '#ffffff'],
      $submit('photos_sh_expand', ['shape' => '16:9', 'custom' => '', 'fill' => 'inpaint', 'colour' => 'black']),
    );
    $this->assertSame(
      ['shape' => '1:1', 'custom' => '', 'fill' => 'colour', 'colour' => 'black'],
      $submit('photos_sh_expand', ['shape' => '1:1', 'custom' => '', 'fill' => 'colour', 'colour' => 'Black']),
    );
    $this->assertSame(
      ['text' => '© Shop', 'position' => 'top', 'size' => 20, 'opacity' => 60],
      $submit('photos_sh_watermark', ['text' => ' © Shop ', 'position' => 'top', 'size' => '20', 'opacity' => '60']),
    );
  }

  /**
   * The effects that can mislead say so, in a plain sentence of the form.
   *
   * @dataProvider provideCautions
   */
  #[DataProvider('provideCautions')]
  public function testCautionsAreOnTheForm(string $id, string $words): void {
    $form = $this->effect($id)->buildConfigurationForm([], new FormState());
    $this->assertArrayHasKey('caution', $form);
    $this->assertStringContainsString($words, (string) $form['caution']['#markup']);
  }

  /**
   * Effects with a caution and what it says.
   */
  public static function provideCautions(): \Generator {
    yield 'colourise' => ['photos_sh_colorize', 'guesses its colours'];
    yield 'restore faces' => ['photos_sh_restore_faces', 'identity photos or evidence'];
    yield 'remove noise' => ['photos_sh_denoise', 'slow without a GPU'];
    yield 'remove noise, size' => ['photos_sh_denoise', '12 megapixels'];
    yield 'remove noise, seconds' => ['photos_sh_denoise', 'can take seconds'];
    yield 'hide faces' => ['photos_sh_blur_faces', 'can be missed'];
    yield 'expand' => ['photos_sh_expand', 'does not invent content'];
  }

  /**
   * Summaries say what the effect does.
   */
  public function testSummaries(): void {
    $render = fn (string $id, array $data) => (string) $this->container->get('plugin.manager.image.effect')
      ->createInstance($id, ['data' => $data])->getSummary()['#markup'];
    $this->assertSame('transparent', $render('photos_sh_remove_background', []));
    $data = ['background' => '#fff000', 'crop' => TRUE, 'padding' => 8];
    $this->assertSame(
      'background #fff000, cropped, padding 8 px',
      $render('photos_sh_remove_background', $data),
    );
    $this->assertSame('transparent, studio shot', $render('photos_sh_remove_background', ['studio' => TRUE]));
    $this->assertSame('3×', $render('photos_sh_upscale', ['scale' => 3]));
    $this->assertSame('all text', $render('photos_sh_remove_text', ['region' => 'all']));
    $this->assertSame('eBay, JPG', $render('photos_sh_fix', ['marketplace' => 'ebay']));
    $this->assertSame('web, WEBP', $render('photos_sh_preset', ['preset' => 'web', 'format' => 'webp']));
    $this->assertSame('automatic', $render('photos_sh_enhance', ['mode' => 'auto']));
    $this->assertSame(
      'brightness 10, saturation -20',
      $render('photos_sh_enhance', [
        'mode' => 'custom',
        'brightness' => 10,
        'contrast' => 0,
        'saturation' => -20,
        'sharpen' => 0,
      ]),
    );
    $this->assertSame('strength 70%', $render('photos_sh_denoise', ['strength' => 70]));
    $this->assertSame('strength 100%', $render('photos_sh_colorize', ['strength' => 100]));
    $this->assertSame('strength 90%', $render('photos_sh_restore_faces', ['strength' => 90]));
    $this->assertSame('blur, strength 60%', $render('photos_sh_blur_faces', ['mode' => 'blur', 'strength' => 60]));
    $this->assertSame('pixelate, strength 30%', $render('photos_sh_blur_faces', [
      'mode' => 'pixelate',
      'strength' => 30,
    ]));
    $this->assertSame('cover with #000000', $render('photos_sh_blur_faces', [
      'mode' => 'fill',
      'color' => '#000000',
    ]));
    $this->assertSame(
      '4:5, around the subject, largest crop',
      $render('photos_sh_crop', ['shape' => '4:5', 'custom' => '', 'focus' => 'subject', 'close' => FALSE]),
    );
    $this->assertSame(
      '7:5, around the faces, close',
      $render('photos_sh_crop', ['shape' => 'custom', 'custom' => '7:5', 'focus' => 'face', 'close' => TRUE]),
    );
    $this->assertSame(
      '16:9, continued by inpainting',
      $render('photos_sh_expand', ['shape' => '16:9', 'custom' => '', 'fill' => 'inpaint', 'colour' => '#ffffff']),
    );
    $this->assertSame(
      '1:1, colour #ffffff',
      $render('photos_sh_expand', ['shape' => '1:1', 'custom' => '', 'fill' => 'colour', 'colour' => '#ffffff']),
    );
    // The text is escaped in the summary.
    $this->assertSame(
      '"A &amp; B", bottom right, size 25%, opacity 50%',
      $render('photos_sh_watermark', [
        'text' => 'A & B',
        'position' => 'bottom-right',
        'size' => 25,
        'opacity' => 50,
      ]),
    );
  }

}
