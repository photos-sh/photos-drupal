<?php

declare(strict_types=1);

namespace Drupal\photos_sh\Hook;

use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\photos_sh\ConnectionStatus;

/**
 * The Photos.sh line on the status report.
 *
 * On Drupal 11.3 and later this class is the hook implementation. On older
 * versions (10.3 to 11.2, which do not know runtime_requirements or, before
 * 11.1, OOP hooks) photos_sh.install calls it from hook_requirements().
 */
class RequirementsHooks {

  use StringTranslationTrait;

  public function __construct(
    protected ConnectionStatus $connectionStatus,
    protected AccountProxyInterface $currentUser,
  ) {
  }

  /**
   * Implements hook_runtime_requirements().
   *
   * @return array<string, array<string, mixed>>
   *   The requirements.
   */
  #[Hook('runtime_requirements')]
  public function runtime(): array {
    $status = $this->connectionStatus->check();
    $requirements = [
      'photos_sh' => [
        'title' => $this->t('Photos.sh'),
        'value' => $status['message'],
        'severity' => $this->severity($status['severity']),
      ],
    ];
    if ($status['severity'] !== ConnectionStatus::OK && $this->currentUser->hasPermission('administer photos_sh')) {
      $requirements['photos_sh']['description'] = $this->t('<a href=":url">Photos.sh settings</a>', [
        ':url' => Url::fromRoute('photos_sh.settings')->toString(),
      ]);
    }
    return $requirements;
  }

  /**
   * Maps a status severity to what this Drupal version expects.
   *
   * Drupal 11.1 and later use the RequirementSeverity enum; older versions use
   * the REQUIREMENT_* integer constants.
   */
  protected function severity(string $severity): mixed {
    if (enum_exists(RequirementSeverity::class)) {
      return match ($severity) {
        ConnectionStatus::OK => RequirementSeverity::OK,
        ConnectionStatus::ERROR => RequirementSeverity::Error,
        default => RequirementSeverity::Warning,
      };
    }
    return match ($severity) {
      ConnectionStatus::OK => constant('REQUIREMENT_OK'),
      ConnectionStatus::ERROR => constant('REQUIREMENT_ERROR'),
      default => constant('REQUIREMENT_WARNING'),
    };
  }

}
