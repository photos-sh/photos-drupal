<?php

declare(strict_types=1);

namespace Drupal\photos_sh;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use PhotosSh\Exception\PhotosException;
use PhotosSh\Exception\UnauthorizedException;

/**
 * Asks the API who the configured key belongs to.
 *
 * Used for the settings form and the status report.
 */
class ConnectionStatus {

  public const OK = 'ok';

  public const WARNING = 'warning';

  public const ERROR = 'error';

  public function __construct(protected PhotosClientFactory $clientFactory) {
  }

  /**
   * Checks the connection.
   *
   * @return array{severity: string, message: \Drupal\Core\StringTranslation\TranslatableMarkup, account: array<string, mixed>|null}
   *   The severity (one of the constants above), a sentence for the user, and
   *   the account the API reported, if it did.
   */
  public function check(): array {
    if (!$this->clientFactory->isConfigured()) {
      return [
        'severity' => self::WARNING,
        'message' => new TranslatableMarkup('No API key is set yet.'),
        'account' => NULL,
      ];
    }

    try {
      $account = $this->clientFactory->get()->account();
    }
    catch (PhotosException $e) {
      return [
        'severity' => $e instanceof UnauthorizedException ? self::ERROR : self::WARNING,
        'message' => ApiErrorMessage::forException($e),
        'account' => NULL,
      ];
    }

    $name = (string) ($account['account'] ?? '');
    if (!empty($account['unlimited']) || !isset($account['credits'])) {
      $message = $name !== ''
        ? new TranslatableMarkup('Connected to account @account, with unlimited credits.', ['@account' => $name])
        : new TranslatableMarkup('Connected, with unlimited credits.');
    }
    else {
      $credits = number_format((int) $account['credits']);
      $message = $name !== ''
        ? new TranslatableMarkup('Connected to account @account, @credits credits left.', [
          '@account' => $name,
          '@credits' => $credits,
        ])
        : new TranslatableMarkup('Connected, @credits credits left.', ['@credits' => $credits]);
    }

    return ['severity' => self::OK, 'message' => $message, 'account' => $account];
  }

}
