# Changelog

## 1.0.0 (unreleased)

First release.

- Settings form at `/admin/config/media/photos-sh`: API key (a Key module key
  when Key is installed, otherwise stored in configuration), base URL, timeout,
  and a status line with the account and the credits left. The
  `PHOTOS_API_KEY` environment variable wins over both. A line on the status
  report.
- Image effects: Remove background, Upscale, Remove text, Fix for a
  marketplace, Run a preset. Derivatives are made through the API when first
  requested. A failed call writes no derivative and is logged to the
  `photos_sh` channel.
- Image effects for the newer tools: Fix light and colour (automatic, or
  brightness, contrast, saturation, sharpening), Remove noise, Colourise,
  Restore faces, Hide faces (blur, pixelate or cover), Crop to a shape around
  the subject or the faces, Expand to a shape (inpainting or a colour) and
  Watermark with text. Crop and Expand report the derivative's size in
  advance where it can be known (not for a close crop). The forms say where a
  tool can mislead (colours are guesses, restored faces are invented, Remove
  noise is slow without a GPU).
- `photos_sh.client` service (a `PhotosSh\Photos` client) for other modules.
- Permission `administer photos_sh`; configuration schema for the settings and
  every effect.
- Supports Drupal 11, PHP 8.3+. The effects work with the GD toolkit and with the
  ImageMagick module's toolkit (optional; any other toolkit makes the effect fail with a log
  message naming it).
