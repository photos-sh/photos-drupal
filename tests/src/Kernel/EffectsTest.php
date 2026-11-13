<?php

declare(strict_types=1);

namespace Drupal\Tests\photos_sh\Kernel;

use Drupal\image\Entity\ImageStyle;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the image effects against a faked API.
 *
 * @group photos_sh
 */
#[Group('photos_sh')]
#[RunTestsInSeparateProcesses]
class EffectsTest extends PhotosShKernelTestBase {

  /**
   * The source image's URI.
   */
  protected const SOURCE = 'public://source.jpg';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    file_put_contents(self::SOURCE, $this->imageBytes(80, 60, 'jpg'));
  }

  /**
   * Creates an image style with the given effects.
   *
   * @param array<int, array{0: string, 1: array<string, mixed>}> $effects
   *   Plugin ids and configuration.
   * @param string $name
   *   The style's machine name.
   */
  protected function style(array $effects, string $name = 'photos_test'): ImageStyle {
    $style = ImageStyle::create(['name' => $name, 'label' => 'Photos test']);
    foreach ($effects as $weight => [$id, $data]) {
      $style->addImageEffect(['id' => $id, 'weight' => $weight, 'data' => $data]);
    }
    $style->save();
    // Config is validated against the schema on save; check it explicitly too.
    $this->assertConfigSchema($this->container->get('config.typed'), $style->getConfigDependencyName(), $this->config($style->getConfigDependencyName())->get());
    return $style;
  }

  /**
   * Makes the derivative and returns what is known about it.
   *
   * @return array{0: bool, 1: string}
   *   Whether it was created and its URI.
   */
  protected function derive(ImageStyle $style): array {
    $uri = $style->buildUri(self::SOURCE);
    return [$style->createDerivative(self::SOURCE, $uri), $uri];
  }

  /**
   * Effects, their configuration, the API's answer and what comes out.
   */
  public static function provideEffects(): \Generator {
    yield 'remove background, transparent' => [
      'photos_sh_remove_background', ['background' => '', 'crop' => FALSE, 'padding' => 0],
      '/api/v1/remove-background', ['format' => 'png'],
      ['png', 80, 60, TRUE], 'png', [80, 60],
    ];
    yield 'remove background, white and cropped' => [
      'photos_sh_remove_background', ['background' => '#ffffff', 'crop' => TRUE, 'padding' => 12],
      '/api/v1/remove-background', [
        'format' => 'jpg',
        'background' => '#ffffff',
        'crop' => 'true',
        'padding' => '12',
      ],
      ['jpg', 50, 40, FALSE], 'jpg', [NULL, NULL],
    ];
    yield 'upscale' => [
      'photos_sh_upscale', ['scale' => 3],
      '/api/v1/upscale', ['scale' => '3', 'format' => 'jpg'],
      ['jpg', 240, 180, FALSE], 'jpg', [240, 180],
    ];
    yield 'remove text' => [
      'photos_sh_remove_text', ['region' => 'all'],
      '/api/v1/remove-text', ['region' => 'all', 'format' => 'jpg'],
      ['jpg', 80, 60, FALSE], 'jpg', [80, 60],
    ];
    yield 'fix for a marketplace' => [
      'photos_sh_fix', ['marketplace' => 'ebay', 'format' => 'jpg'],
      '/api/v1/fix', ['marketplace' => 'ebay'],
      ['jpg', 100, 100, FALSE], 'jpg', [NULL, NULL],
    ];
    yield 'preset' => [
      'photos_sh_preset', ['preset' => 'cutout', 'format' => 'png'],
      '/api/v1/run', ['preset' => 'cutout'],
      ['png', 70, 55, TRUE], 'png', [NULL, NULL],
    ];
    yield 'fix light and colour, automatic' => [
      'photos_sh_enhance', ['mode' => 'auto', 'brightness' => 0, 'contrast' => 0, 'saturation' => 0, 'sharpen' => 0],
      '/api/v1/enhance', ['auto' => 'true', 'format' => 'jpg'],
      ['jpg', 80, 60, FALSE], 'jpg', [80, 60],
    ];
    yield 'fix light and colour, own settings' => [
      'photos_sh_enhance', [
        'mode' => 'custom',
        'brightness' => 10,
        'contrast' => 0,
        'saturation' => -20,
        'sharpen' => 30,
      ],
      '/api/v1/enhance', [
        'brightness' => '10',
        'contrast' => '0',
        'saturation' => '-20',
        'sharpen' => '30',
        'format' => 'jpg',
      ],
      ['jpg', 80, 60, FALSE], 'jpg', [80, 60],
    ];
    yield 'remove noise' => [
      'photos_sh_denoise', ['strength' => 70],
      '/api/v1/denoise', ['strength' => '70', 'format' => 'jpg'],
      ['jpg', 80, 60, FALSE], 'jpg', [80, 60],
    ];
    yield 'colourise' => [
      'photos_sh_colorize', ['strength' => 100],
      '/api/v1/colorize', ['strength' => '100', 'format' => 'jpg'],
      ['jpg', 80, 60, FALSE], 'jpg', [80, 60],
    ];
    yield 'restore faces' => [
      'photos_sh_restore_faces', ['strength' => 80],
      '/api/v1/restore-faces', ['strength' => '80', 'format' => 'jpg'],
      ['jpg', 80, 60, FALSE], 'jpg', [80, 60],
    ];
    yield 'hide faces, blur' => [
      'photos_sh_blur_faces', ['mode' => 'blur', 'strength' => 60, 'color' => 'black'],
      '/api/v1/blur-faces', ['mode' => 'blur', 'strength' => '60', 'format' => 'jpg'],
      ['jpg', 80, 60, FALSE], 'jpg', [80, 60],
    ];
    yield 'hide faces, cover with a colour' => [
      'photos_sh_blur_faces', ['mode' => 'fill', 'strength' => 50, 'color' => '#112233'],
      '/api/v1/blur-faces', ['mode' => 'fill', 'color' => '#112233', 'format' => 'jpg'],
      ['jpg', 80, 60, FALSE], 'jpg', [80, 60],
    ];
    yield 'crop, largest 4:5 around the subject' => [
      'photos_sh_crop', ['shape' => '4:5', 'custom' => '', 'focus' => 'subject', 'close' => FALSE],
      '/api/v1/crop', ['aspect' => '4:5', 'format' => 'jpg'],
      ['jpg', 48, 60, FALSE], 'jpg', [48, 60],
    ];
    yield 'crop, custom shape' => [
      'photos_sh_crop', ['shape' => 'custom', 'custom' => '2:1', 'focus' => 'subject', 'close' => FALSE],
      '/api/v1/crop', ['aspect' => '2:1', 'format' => 'jpg'],
      ['jpg', 80, 40, FALSE], 'jpg', [80, 40],
    ];
    yield 'crop, close around the faces' => [
      'photos_sh_crop', ['shape' => '1:1', 'custom' => '', 'focus' => 'face', 'close' => TRUE],
      '/api/v1/crop', ['aspect' => '1:1', 'zoom' => 'true', 'padding' => '0.6', 'focus' => 'face', 'format' => 'jpg'],
      ['jpg', 30, 30, FALSE], 'jpg', [NULL, NULL],
    ];
    yield 'expand to a shape by inpainting' => [
      'photos_sh_expand', ['shape' => '1:1', 'custom' => '', 'fill' => 'inpaint', 'colour' => '#ffffff'],
      '/api/v1/expand', ['aspect' => '1:1', 'fill' => 'inpaint', 'format' => 'jpg'],
      ['jpg', 80, 80, FALSE], 'jpg', [80, 80],
    ];
    yield 'expand to a shape with a colour' => [
      'photos_sh_expand', ['shape' => '16:9', 'custom' => '', 'fill' => 'colour', 'colour' => '#ffffff'],
      '/api/v1/expand', ['aspect' => '16:9', 'fill' => '#ffffff', 'format' => 'jpg'],
      ['jpg', 107, 60, FALSE], 'jpg', [107, 60],
    ];
    yield 'watermark with text' => [
      'photos_sh_watermark', ['text' => 'My Shop', 'position' => 'bottom-left', 'size' => 30, 'opacity' => 40],
      '/api/v1/watermark', [
        'text' => 'My Shop',
        'position' => 'bottom-left',
        'scale' => '30',
        'opacity' => '40',
        'format' => 'jpg',
      ],
      ['jpg', 80, 60, FALSE], 'jpg', [80, 60],
    ];
  }

  /**
   * Each effect sends the image, loads the answer and keeps its promises.
   *
   * @dataProvider provideEffects
   */
  #[DataProvider('provideEffects')]
  public function testEffect(string $plugin, array $config, string $path, array $query, array $answer, string $extension, array $dimensions): void {
    [$type, $width, $height, $transparent] = $answer;
    $mime = $type === 'jpg' ? 'image/jpeg' : 'image/' . $type;
    $this->queueImage($this->imageBytes($width, $height, $type, $transparent), $mime, $width, $height);

    $style = $this->style([[$plugin, $config]]);
    [$created, $uri] = $this->derive($style);

    $this->assertTrue($created);
    // The URI promises the extension the effect declares.
    $this->assertStringEndsWith($extension, $uri);
    $info = getimagesize($uri);
    $this->assertSame($mime, $info['mime']);
    $this->assertSame([$width, $height], [$info[0], $info[1]]);

    // One POST, to the right endpoint, with the key and the options.
    $requests = $this->requests();
    $this->assertCount(1, $requests);
    $request = $requests[0];
    $this->assertSame('POST', $request->getMethod());
    $this->assertSame($path, $request->getUri()->getPath());
    parse_str($request->getUri()->getQuery(), $sent);
    $this->assertEquals($query, array_intersect_key($sent, $query));
    $this->assertSame('Bearer ' . self::API_KEY, $request->getHeaderLine('Authorization'));
    $this->assertStringContainsString('multipart/form-data', $request->getHeaderLine('Content-Type'));
    $body = (string) $request->getBody();
    $this->assertStringContainsString('name="image"', $body);
    // The image sent is the source's current state (a JPEG).
    $this->assertStringContainsString('filename="image.jpg"', $body);

    // transformDimensions says what to expect without processing.
    $dims = ['width' => 80, 'height' => 60];
    $style->transformDimensions($dims, self::SOURCE);
    $this->assertSame($dimensions, [$dims['width'], $dims['height']]);
  }

  /**
   * Remove background without a colour is a PNG whatever goes in.
   */
  public function testRemoveBackgroundExtension(): void {
    $style = $this->style([['photos_sh_remove_background', ['background' => '', 'crop' => FALSE, 'padding' => 0]]]);
    $this->assertStringEndsWith('source.jpg.png', $style->buildUri(self::SOURCE));
    $this->assertSame('png', $style->getDerivativeExtension('jpg'));
    $this->assertSame('png', $style->getDerivativeExtension('webp'));

    $style = $this->style([[
      'photos_sh_remove_background',
      ['background' => 'white', 'crop' => FALSE, 'padding' => 0],
    ],
    ], 'photos_test_white');
    $this->assertSame('jpg', $style->getDerivativeExtension('jpg'));
    $this->assertSame('webp', $style->getDerivativeExtension('webp'));
    $this->assertSame('png', $style->getDerivativeExtension('gif'));
  }

  /**
   * A result in another format than promised is converted.
   */
  public function testResultConvertedToPromisedFormat(): void {
    $this->queueImage($this->imageBytes(64, 48, 'png'), 'image/png', 64, 48);
    $style = $this->style([['photos_sh_fix', ['marketplace' => 'amazon', 'format' => 'jpg']]]);
    [$created, $uri] = $this->derive($style);
    $this->assertTrue($created);
    $this->assertSame('image/jpeg', getimagesize($uri)['mime']);
  }

  /**
   * Later effects work on the result, not on the original.
   */
  public function testLaterEffectsSeeTheResult(): void {
    $this->queueImage($this->imageBytes(40, 30, 'jpg'), 'image/jpeg', 40, 30);
    $style = $this->style([
      ['photos_sh_remove_text', ['region' => 'background']],
      ['image_scale', ['width' => 20, 'height' => NULL, 'upscale' => FALSE]],
    ]);
    [$created, $uri] = $this->derive($style);
    $this->assertTrue($created);
    $info = getimagesize($uri);
    // 80x60 went to the API, 40x30 came back, then core scaled it to 20x15.
    $this->assertSame([20, 15], [$info[0], $info[1]]);
  }

  /**
   * Two Photos.sh effects in a row each make a request on the previous result.
   */
  public function testTwoEffectsChained(): void {
    $this->queueImage($this->imageBytes(80, 60, 'png', TRUE), 'image/png', 80, 60);
    $this->queueImage($this->imageBytes(160, 120, 'png', TRUE), 'image/png', 160, 120);
    $style = $this->style([
      ['photos_sh_remove_background', ['background' => '', 'crop' => FALSE, 'padding' => 0]],
      ['photos_sh_upscale', ['scale' => 2]],
    ]);
    $this->assertStringEndsWith('source.jpg.png', $style->buildUri(self::SOURCE));
    [$created, $uri] = $this->derive($style);
    $this->assertTrue($created);

    $requests = $this->requests();
    $this->assertCount(2, $requests);
    // The second request carries the first result: a PNG.
    $this->assertStringContainsString('filename="image.png"', (string) $requests[1]->getBody());
    $this->assertSame('format=png&scale=2', $this->sortedQuery($requests[1]->getUri()->getQuery()));
    $this->assertSame([160, 120], array_slice(getimagesize($uri), 0, 2));
  }

  /**
   * Query string with sorted keys.
   */
  protected function sortedQuery(string $query): string {
    parse_str($query, $parts);
    ksort($parts);
    return http_build_query($parts);
  }

  /**
   * An effect after core effects sends the image as it is by then.
   */
  public function testEffectAfterOthersSendsTheCurrentState(): void {
    $this->queueImage($this->imageBytes(80, 60, 'jpg'), 'image/jpeg', 80, 60);
    $style = $this->style([
      ['image_scale', ['width' => 40, 'height' => NULL, 'upscale' => FALSE]],
      ['photos_sh_upscale', ['scale' => 2]],
    ]);
    [$created, $uri] = $this->derive($style);
    $this->assertTrue($created);
    $this->assertSame([40, 30, 'image.jpg'], $this->sentImage(0));
    $this->assertSame([80, 60], array_slice(getimagesize($uri), 0, 2));
  }

  /**
   * A convert before the effect changes the format that is sent.
   */
  public function testEffectAfterConvertSendsTheNewFormat(): void {
    $this->queueImage($this->imageBytes(80, 60, 'png'), 'image/png', 80, 60);
    $style = $this->style([
      ['image_convert', ['extension' => 'png']],
      ['photos_sh_remove_text', ['region' => 'background']],
    ]);
    $this->assertStringEndsWith('source.jpg.png', $style->buildUri(self::SOURCE));
    [$created, $uri] = $this->derive($style);
    $this->assertTrue($created);
    $this->assertSame([80, 60, 'image.png'], $this->sentImage(0));
    $this->assertSame('image/png', getimagesize($uri)['mime']);
  }

  /**
   * Transparency in the result survives into the derivative.
   */
  public function testTransparencySurvives(): void {
    $this->queueImage($this->imageBytes(40, 30, 'png', TRUE), 'image/png', 40, 30);
    $style = $this->style([['photos_sh_remove_background', ['background' => '', 'crop' => FALSE, 'padding' => 0]]]);
    [$created, $uri] = $this->derive($style);
    $this->assertTrue($created);
    $image = imagecreatefrompng($uri);
    // ImageMagick may write a palette PNG.
    imagepalettetotruecolor($image);
    // The alpha of a pixel: 127 is fully transparent.
    $this->assertSame(127, (imagecolorat($image, 5, 5) >> 24) & 0x7F);
  }

  /**
   * API failures.
   */
  public static function provideFailures(): \Generator {
    yield 'out of credits' => [402, 'payment_required', 'No credits left', 'HTTP 402 payment_required'];
    yield 'key refused' => [401, 'unauthorized', 'Invalid API key', 'HTTP 401 unauthorized'];
    yield 'cannot process' => [422, 'unprocessable', 'No subject found', 'HTTP 422 unprocessable'];
    yield 'server error' => [500, 'internal', 'Something broke', 'HTTP 500 internal'];
  }

  /**
   * A failed call leaves no derivative, is logged, and never logs the key.
   *
   * @dataProvider provideFailures
   */
  #[DataProvider('provideFailures')]
  public function testFailureLeavesNoDerivative(int $status, string $code, string $message, string $logged): void {
    $this->queueError($status, $code, $message);
    $style = $this->style([['photos_sh_upscale', ['scale' => 2]]]);
    [$created, $uri] = $this->derive($style);

    $this->assertFalse($created);
    $this->assertFileDoesNotExist($uri);
    $log = $this->photosLog();
    $this->assertCount(1, $log);
    $this->assertStringContainsString($logged, $log[0]);
    $this->assertStringContainsString($message, $log[0]);
    $this->assertStringNotContainsString(self::API_KEY, implode("\n", array_column(TestLogger::$records, 'message')));
    // The original is untouched.
    $this->assertSame([80, 60], array_slice(getimagesize(self::SOURCE), 0, 2));
  }

  /**
   * An unreachable API also leaves no derivative.
   */
  public function testTimeoutLeavesNoDerivative(): void {
    self::$mock->append(new ConnectException(
      'cURL error 28: Operation timed out',
      new Request('POST', 'http://engine.test/'),
      NULL,
      ['errno' => 28],
    ));
    $style = $this->style([['photos_sh_remove_text', ['region' => 'background']]]);
    [$created, $uri] = $this->derive($style);
    $this->assertFalse($created);
    $this->assertFileDoesNotExist($uri);
    $this->assertStringContainsString('failed', $this->photosLog()[0]);
    $this->assertStringNotContainsString(self::API_KEY, implode("\n", $this->photosLog()));
  }

  /**
   * A 200 that is not an image leaves no derivative.
   */
  public function testGarbageResultLeavesNoDerivative(): void {
    $this->queueImage('not an image', 'image/png', 10, 10);
    $style = $this->style([['photos_sh_upscale', ['scale' => 2]]]);
    [$created, $uri] = $this->derive($style);
    $this->assertFalse($created);
    $this->assertFileDoesNotExist($uri);
    $this->assertNotEmpty($this->photosLog());
  }

  /**
   * Without an API key no request is made and no derivative written.
   */
  public function testNoKeyMakesNoRequest(): void {
    $this->config('photos_sh.settings')->set('api_key', '')->save();
    $style = $this->style([['photos_sh_upscale', ['scale' => 2]]]);
    [$created, $uri] = $this->derive($style);
    $this->assertFalse($created);
    $this->assertFileDoesNotExist($uri);
    $this->assertCount(0, $this->requests());
    $this->assertStringContainsString('No Photos.sh API key', $this->photosLog()[0]);
  }

  /**
   * A failure is retried on the next request.
   */
  public function testRetriedOnNextRequest(): void {
    $this->queueError(500, 'internal', 'boom');
    $this->queueImage($this->imageBytes(160, 120, 'jpg'), 'image/jpeg', 160, 120);
    $style = $this->style([['photos_sh_upscale', ['scale' => 2]]]);
    [$created] = $this->derive($style);
    $this->assertFalse($created);
    [$created, $uri] = $this->derive($style);
    $this->assertTrue($created);
    $this->assertFileExists($uri);
    $this->assertCount(2, $this->requests());
  }

  /**
   * A busy API is not waited for: one request, and the next visit tries anew.
   */
  public function testBusyIsNotRetriedWithinTheRequest(): void {
    $this->queueError(503, 'busy', 'all workers are busy; retry shortly');
    $this->queueImage($this->imageBytes(160, 120, 'jpg'), 'image/jpeg', 160, 120);
    $style = $this->style([['photos_sh_upscale', ['scale' => 2]]]);
    [$created, $uri] = $this->derive($style);
    $this->assertFalse($created);
    $this->assertFileDoesNotExist($uri);
    $this->assertCount(1, $this->requests());
    [$created] = $this->derive($style);
    $this->assertTrue($created);
    $this->assertCount(2, $this->requests());
  }

  /**
   * A failed first effect stops later Photos.sh effects from calling the API.
   */
  public function testFailureStopsTheChain(): void {
    $this->queueError(402, 'payment_required', 'No credits');
    $style = $this->style([
      ['photos_sh_remove_background', ['background' => '', 'crop' => FALSE, 'padding' => 0]],
      ['photos_sh_upscale', ['scale' => 2]],
    ]);
    [$created, $uri] = $this->derive($style);
    $this->assertFalse($created);
    $this->assertFileDoesNotExist($uri);
    $this->assertCount(1, $this->requests());
  }

  /**
   * Settings that do not apply are not sent.
   */
  public function testSettingsThatDoNotApplyAreNotSent(): void {
    $this->queueImage($this->imageBytes(80, 60, 'jpg'), 'image/jpeg', 80, 60);
    $this->queueImage($this->imageBytes(80, 60, 'jpg'), 'image/jpeg', 80, 60);
    $this->queueImage($this->imageBytes(80, 60, 'jpg'), 'image/jpeg', 80, 60);
    $this->derive($this->style([[
      'photos_sh_enhance',
      ['mode' => 'custom', 'brightness' => 5, 'contrast' => 0, 'saturation' => 0, 'sharpen' => 0],
    ],
    ], 'photos_enhance'));
    $this->derive($this->style([[
      'photos_sh_blur_faces',
      ['mode' => 'fill', 'strength' => 50, 'color' => 'white'],
    ],
    ], 'photos_fill'));
    $this->derive($this->style([[
      'photos_sh_crop',
      ['shape' => '1:1', 'custom' => '', 'focus' => 'subject', 'close' => FALSE],
    ],
    ], 'photos_crop'));
    parse_str($this->requests()[0]->getUri()->getQuery(), $enhance);
    $this->assertArrayNotHasKey('auto', $enhance);
    parse_str($this->requests()[1]->getUri()->getQuery(), $fill);
    $this->assertArrayNotHasKey('strength', $fill);
    parse_str($this->requests()[2]->getUri()->getQuery(), $crop);
    $this->assertArrayNotHasKey('zoom', $crop);
    $this->assertArrayNotHasKey('padding', $crop);
    $this->assertArrayNotHasKey('focus', $crop);
  }

  /**
   * A crop of a known shape tells its size before it is made.
   */
  public function testCropAndExpandDimensions(): void {
    $crop = $this->style([[
      'photos_sh_crop',
      ['shape' => '16:9', 'custom' => '', 'focus' => 'subject', 'close' => FALSE],
    ],
    ], 'photos_crop_dims');
    $dims = ['width' => 1600, 'height' => 1600];
    $crop->transformDimensions($dims, self::SOURCE);
    $this->assertSame([1600, 900], [$dims['width'], $dims['height']]);

    // An unknown size stays unknown.
    $dims = ['width' => NULL, 'height' => NULL];
    $crop->transformDimensions($dims, self::SOURCE);
    $this->assertSame([NULL, NULL], [$dims['width'], $dims['height']]);

    $expand = $this->style([[
      'photos_sh_expand',
      ['shape' => '16:9', 'custom' => '', 'fill' => 'inpaint', 'colour' => '#ffffff'],
    ],
    ], 'photos_expand_dims');
    $dims = ['width' => 1600, 'height' => 1600];
    $expand->transformDimensions($dims, self::SOURCE);
    $this->assertSame([2844, 1600], [$dims['width'], $dims['height']]);
    // A photo that already has the shape or is wider is left as it is.
    $dims = ['width' => 1600, 'height' => 900];
    $expand->transformDimensions($dims, self::SOURCE);
    $this->assertSame([1600, 900], [$dims['width'], $dims['height']]);
  }

  /**
   * A crop with nothing to centre on, and a hide with no face, are logged.
   */
  public function testNothingFoundIsLogged(): void {
    $this->queue(200, $this->imageBytes(48, 60, 'jpg'), [
      'Content-Type' => 'image/jpeg',
      'X-Image-Width' => '48',
      'X-Image-Height' => '60',
      'X-Subject-Found' => 'false',
    ]);
    $this->queue(200, $this->imageBytes(80, 60, 'jpg'), [
      'Content-Type' => 'image/jpeg',
      'X-Image-Width' => '80',
      'X-Image-Height' => '60',
      'X-Faces' => '0',
    ]);
    [$created] = $this->derive($this->style([[
      'photos_sh_crop',
      ['shape' => '4:5', 'custom' => '', 'focus' => 'subject', 'close' => FALSE],
    ],
    ], 'photos_crop_log'));
    $this->assertTrue($created);
    [$created] = $this->derive($this->style([[
      'photos_sh_blur_faces',
      ['mode' => 'blur', 'strength' => 50, 'color' => 'black'],
    ],
    ], 'photos_faces_log'));
    $this->assertTrue($created);
    $log = $this->photosLog();
    $this->assertCount(2, $log);
    $this->assertStringContainsString('nothing to centre on', $log[0]);
    $this->assertStringContainsString('no face was found', $log[1]);
  }

  /**
   * The new effects fail like the others: no derivative, and a log line.
   *
   * @dataProvider provideNewEffectIds
   */
  #[DataProvider('provideNewEffectIds')]
  public function testNewEffectsLeaveNoDerivative(string $plugin, array $config): void {
    $this->queueError(503, 'busy', 'all workers are busy; retry shortly');
    $style = $this->style([[$plugin, $config]], 'photos_failing');
    [$created, $uri] = $this->derive($style);
    $this->assertFalse($created);
    $this->assertFileDoesNotExist($uri);
    $this->assertCount(1, $this->requests());
    $log = $this->photosLog();
    $this->assertCount(1, $log);
    $this->assertStringContainsString('HTTP 503 busy', $log[0]);
    $this->assertStringNotContainsString(self::API_KEY, $log[0]);
  }

  /**
   * The new effects with a configuration each.
   */
  public static function provideNewEffectIds(): \Generator {
    yield 'enhance' => [
      'photos_sh_enhance',
      ['mode' => 'auto', 'brightness' => 0, 'contrast' => 0, 'saturation' => 0, 'sharpen' => 0],
    ];
    yield 'denoise' => ['photos_sh_denoise', ['strength' => 100]];
    yield 'colorize' => ['photos_sh_colorize', ['strength' => 100]];
    yield 'restore faces' => ['photos_sh_restore_faces', ['strength' => 100]];
    yield 'hide faces' => ['photos_sh_blur_faces', ['mode' => 'blur', 'strength' => 50, 'color' => 'black']];
    yield 'crop' => ['photos_sh_crop', ['shape' => '1:1', 'custom' => '', 'focus' => 'subject', 'close' => FALSE]];
    yield 'expand' => [
      'photos_sh_expand',
      ['shape' => '1:1', 'custom' => '', 'fill' => 'inpaint', 'colour' => '#ffffff'],
    ];
    yield 'watermark' => [
      'photos_sh_watermark',
      ['text' => 'Shop', 'position' => 'bottom-right', 'size' => 25, 'opacity' => 50],
    ];
  }

  /**
   * The effects are discoverable with labels.
   */
  public function testEffectsAreDiscovered(): void {
    $ids = array_keys($this->container->get('plugin.manager.image.effect')->getDefinitions());
    $expected = [
      'photos_sh_remove_background',
      'photos_sh_upscale',
      'photos_sh_remove_text',
      'photos_sh_fix',
      'photos_sh_preset',
      'photos_sh_enhance',
      'photos_sh_denoise',
      'photos_sh_colorize',
      'photos_sh_restore_faces',
      'photos_sh_blur_faces',
      'photos_sh_crop',
      'photos_sh_expand',
      'photos_sh_watermark',
    ];
    foreach ($expected as $id) {
      $this->assertContains($id, $ids);
    }
  }

}
