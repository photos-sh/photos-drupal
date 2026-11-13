# Photos.sh for Drupal

Image effects for Drupal image styles, powered by the
[Photos.sh](https://photos.sh) API: remove the background, upscale with AI,
remove text and watermarks, fix a photo for a marketplace, run a preset, fix
light and colour, remove noise, colourise, restore or hide faces, crop or
expand to a shape, and write a text on a photo.

- Drupal 10.3 or 11, PHP 8.3 or later.
- An image toolkit: GD (Drupal's default) or the
  [ImageMagick](https://www.drupal.org/project/imagemagick) module's toolkit
  (see Toolkits below). The ImageMagick module is optional.
- A Photos.sh API key. Create one on your organisation's API keys page in the
  Photos.sh console.
- The [PHP library](https://github.com/photos-sh/photos-php)
  (`photos-sh/photos`), which Composer installs.

## Install

```bash
composer require drupal/photos_sh
drush en photos_sh
```

Composer brings the PHP library, `photos-sh/photos`, from Packagist.

## Configure

Go to **Configuration > Media > Photos.sh**
(`/admin/config/media/photos-sh`, permission `administer photos_sh`).

- **Status** shows the account and the credits left, as soon as a key works
  (the same line is on the status report).
- **API key.** Where it comes from, in this order:
  1. The `PHOTOS_API_KEY` environment variable. It wins and the field is
     replaced by a note.
  2. A key from the [Key](https://www.drupal.org/project/key) module, when that
     module is installed (the form then shows a key selector).
  3. Otherwise a key saved in configuration. It is never shown back in full,
     and it is not logged.

  Configuration is the weakest place for a secret (it is exported with the rest
  of the site's config). Prefer the environment variable or a Key.
- **Base URL** (under Advanced) defaults to `https://api.photos.sh`.
- **Timeout** (under Advanced) defaults to 60 seconds.

`settings.php` can override any setting, and the form then shows it as read
only:

```php
$config['photos_sh.settings']['api_key'] = getenv('MY_SECRET');
$config['photos_sh.settings']['base_url'] = 'http://127.0.0.1:8098/api';
```

## Image effects

Add them to an image style at **Configuration > Media > Image styles**.

| Effect | Options | Result |
|---|---|---|
| Remove background | Background colour (`#rrggbb`, `white`, `black`, or empty), crop to the subject, padding | A transparent **PNG** when there is no colour. With a colour the image keeps its format (PNG, JPEG or WebP; anything else becomes PNG). Cropping makes the size unknown until the image is made. |
| Upscale | 2x, 3x or 4x | The same format, each side multiplied by the scale. |
| Remove text | Region: text off the product, or all text | The same size and format. |
| Fix for a marketplace | Marketplace (Amazon, eBay, Etsy, Shopify, Google, Allegro, Zalando), output format | The marketplace decides the size and background. The format is the one you choose (JPEG by default); a result in another format is converted. |
| Run a preset | Preset (marketplace, studio, social, cutout, web), output format | The preset decides the size. Format as above. |
| Fix light and colour | Automatic, or brightness, contrast, saturation and sharpening (-100 to 100; sharpening 0 to 100) | The same size and format. Choosing your own settings needs at least one that is not 0: with all at 0 the API would do the automatic fix. |
| Remove noise | Strength (1 to 100) | The same size and format. Slow without a GPU, and photos up to 12 megapixels only. |
| Colourise | Strength (1 to 100) | The same size and format. The colours are guesses. |
| Restore faces | Strength (1 to 100) | The same size and format. The model invents detail: not for identity photos or evidence. |
| Hide faces | Blur, pixelate or cover with a colour; strength (blur and pixelate) or colour (cover) | The same size and format. Faces the model does not find stay visible. |
| Crop to a shape | Shape (1:1, 4:5, 5:4, 3:2, 2:3, 4:3, 16:9, 9:16 or custom), around the subject or the faces, close or not | The same format. Without "close" the crop is the largest one of that shape and its size is known in advance. A close crop is as big as the subject, so its size is unknown until it is made. |
| Expand to a shape | Shape (as above), new area continued by inpainting or filled with a colour | The same format. One side grows and the photo stays in the middle, so the size is known in advance. |
| Watermark with text | Text (up to 200 characters), position (nine), size and opacity (1 to 100) | The same size and format. The text is white, in a bold sans-serif font with Latin, Greek and Cyrillic letters. |

Remove noise, Colourise, Restore faces, Crop and Expand use models and can take
seconds (Remove noise most of all on a server without a GPU), and the page that
first asks for the derivative waits for them. Warm those derivatives as
described below. The forms say where a tool can mislead.

Each effect declares the file extension and the size it will produce, so image
style URLs and `width` and `height` attributes are right before anything is
processed. Where the API decides the size, the size is reported as unknown.

### How it behaves

- **Derivatives are made when first requested.** The page that asks for the
  image waits for the API (a few seconds for background removal or marketplace
  fixes), then the file is kept like any derivative. Warm the ones you need
  (open the URLs, or use a queue) rather than letting the first visitor wait.
  Flushing a style (or `drush image:flush`) means the API is called again,
  and each call uses credits.
- **A busy API is not waited for.** If Photos.sh answers "busy" or cannot be
  reached, the derivative fails at once (no file is written) and the next
  request for the image tries again.
- **Some results are only as good as the model.** Crop falls back to the
  middle of the photo when it finds no subject or face, and Hide faces leaves
  the photo as it is when it finds no face. Both write a note to the
  `photos_sh` log channel; the derivative is still made.
- **Failure leaves no derivative.** If the API fails (out of credits, key
  refused, busy, unreachable, a photo it cannot process), the effect fails, no
  derivative is written, the request gets Drupal's usual "Error generating
  image" and the next request tries again. The reason goes to the `photos_sh`
  log channel (Reports > Recent log messages), never with the key.
- **Originals are never touched.** Only derivatives are written.
- **The image is sent as it is at that point in the style.** Effects before
  it (scale, crop, convert, ...) are applied first, and effects after it
  (scale, crop, convert, other Photos.sh effects) work on its result.
- **The image is encoded once to be sent.** The toolkit has to write its
  current state to a temporary file, so a JPEG is re-encoded at the toolkit's
  JPEG quality before it goes to the API (see Toolkits). Put a Photos.sh effect
  first in the style and set a high quality if that matters.

## Toolkits

Image styles run on an image toolkit (`/admin/config/media/image-toolkit`).
The effects work with:

- **GD** (core). The image is held in memory; it is written to a temporary
  file, sent, and the API's answer replaces it in memory. The JPEG quality is
  the "JPEG quality" setting of GD (default 75).
- **ImageMagick**, from the contrib module
  [`drupal/imagemagick`](https://www.drupal.org/project/imagemagick) (4.x on
  Drupal 10.3 and 11.0 to 11.2, 5.x on 11.3 and later; the module is not
  required). That toolkit does not hold pixels: it queues command line
  arguments and runs `magick` or `convert` once, when the image is saved. So
  to send the image, the queued operations are run once into a temporary file
  (the JPEG quality is ImageMagick's "Image quality" setting, default 75); the
  API's answer is written to another temporary file and becomes the toolkit's
  new source, with the queue empty. Effects after it queue onto that file. The
  cost is one extra `magick` run and one re-encode per Photos.sh effect in a
  style; temporary files are removed when the request ends. Transparency
  survives (PNG and WebP carry the alpha channel).

Any other toolkit makes the effect fail, and the log message names the
toolkit.

## For developers

```php
/** @var \PhotosSh\Photos $photos */
$photos = \Drupal::service('photos_sh.client');
$cutout = $photos->removeBackground('/path/to/shoe.jpg');
```

`photos_sh.client` is the PHP library's client built from the settings above,
using Drupal's `http_client` (so Drupal's proxy and TLS settings apply). It
throws `PhotosSh\Exception\InvalidInputException` when there is no key; check
`\Drupal::service('photos_sh.client_factory')->isConfigured()` first if that
matters. See the library's README for everything the client can do (checks,
barcodes, label reading, batch jobs, ...).

`Drupal\photos_sh\ApiErrorMessage::forException()` turns a library exception
into a sentence for editors.

## Tests

The unit and kernel tests run with Drupal core's PHPUnit setup, in a Drupal
project that has the module:

```bash
composer require --dev drupal/core-dev
cd web
../vendor/bin/phpunit -c core modules/contrib/photos_sh/tests/src/Unit
SIMPLETEST_DB=sqlite://localhost//tmp/test.sqlite \
  ../vendor/bin/phpunit -c core modules/contrib/photos_sh/tests/src/Kernel
../vendor/bin/phpcs --standard=Drupal,DrupalPractice modules/contrib/photos_sh
```

To work on the module, install it from source, which leaves a git checkout
in `web/modules/contrib/photos_sh`:

```bash
composer require drupal/photos_sh:1.x-dev --prefer-source
```

The unit tests include `ShapeTest` (the shapes and the derivative sizes of
crop and expand). The kernel tests cover every effect's form, summary, schema,
request and failure behaviour.

No test calls the network: the kernel tests replace `http_client` with a mock
handler.

## Continuous integration

`.gitlab-ci.yml` uses the drupal.org GitLab templates (the project is meant to
live on drupal.org). `.github/workflows/ci.yml` runs phpcs and the unit tests
on GitHub. Both install the library, `photos-sh/photos`, from Packagist.

The branch is `1.x` and releases are tagged without a `v` (`1.0.0`), as
drupal.org expects.

## License

GPL-2.0-or-later. See [LICENSE.txt](LICENSE.txt). The PHP library it uses is
MIT-licensed, which is compatible.
