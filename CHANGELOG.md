# Changelog

## Unreleased

## 1.1.0 (2026-11-20)

- Remove background: a "Model" choice for the model that finds the subject:
  Atlas (the default; everyday product photos), Lumen (pale packaging on a
  light backdrop, and close-ups where the product fills the photo) or Muse
  (people: portraits, and models wearing the product). Each costs the same.
  The API is sent `model=lumen` or `model=muse` only when one of those is
  chosen; with Atlas the parameter is left out and the API's default applies.
  Radios with a description of each model on the effect's form; in the
  effect's summary and its configuration schema (`model`). Image styles saved
  before keep working, with Atlas.
- Needs photos-sh/photos 1.3 (`removeBackground(model: …)`).

## 1.0.1 (2026-11-16)

- Remove background: a "Studio shot" option (`studio`, off by default) for
  product photos on a plain light backdrop (a light tent). Background removal
  then also keeps the box, case or lid shown with the product. The API is sent
  `studio=true` only when it is on. In the effect's summary and its
  configuration schema. Image styles saved before keep working, with the
  option off.
- Needs photos-sh/photos 1.1 (`removeBackground(studio: …)`).

## 1.0.0 (2026-11-13)

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
