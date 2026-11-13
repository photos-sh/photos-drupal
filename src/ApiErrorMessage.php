<?php

declare(strict_types=1);

namespace Drupal\photos_sh;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use PhotosSh\Exception\AccountSuspendedException;
use PhotosSh\Exception\ApiException;
use PhotosSh\Exception\BusyException;
use PhotosSh\Exception\ConnectionException;
use PhotosSh\Exception\InvalidInputException;
use PhotosSh\Exception\PaymentRequiredException;
use PhotosSh\Exception\TimeoutException;
use PhotosSh\Exception\UnauthorizedException;

/**
 * Turns an exception from the Photos.sh library into words for an editor.
 */
final class ApiErrorMessage {

  /**
   * Describes a failure in plain words.
   */
  public static function forException(\Throwable $e): TranslatableMarkup {
    return match (TRUE) {
      $e instanceof UnauthorizedException => new TranslatableMarkup('Photos.sh refused the API key. Check the key in the Photos.sh settings.'),
      $e instanceof PaymentRequiredException => new TranslatableMarkup('Photos.sh is out of credits. Add credits and try again.'),
      $e instanceof AccountSuspendedException => new TranslatableMarkup('The Photos.sh account is suspended.'),
      $e instanceof BusyException => new TranslatableMarkup('Photos.sh is busy. Try again in a moment.'),
      $e instanceof TimeoutException => new TranslatableMarkup('Photos.sh did not answer in time. Try again, or raise the timeout in the Photos.sh settings.'),
      $e instanceof ConnectionException => new TranslatableMarkup('Photos.sh could not be reached. Check the base URL and the network.'),
      $e instanceof InvalidInputException => new TranslatableMarkup('No Photos.sh API key is set.'),
      $e instanceof ApiException => new TranslatableMarkup('Photos.sh could not process the image: @message', ['@message' => $e->getMessage()]),
      default => new TranslatableMarkup('The Photos.sh request failed.'),
    };
  }

}
