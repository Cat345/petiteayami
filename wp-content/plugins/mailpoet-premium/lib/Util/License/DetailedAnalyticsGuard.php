<?php declare(strict_types = 1);

namespace MailPoet\Premium\Util\License;

if (!defined('ABSPATH')) exit;


use MailPoet\API\REST\ApiException;
use MailPoet\Util\License\Features\CapabilitiesManager;

class DetailedAnalyticsGuard {
  private CapabilitiesManager $capabilitiesManager;

  public function __construct(
    CapabilitiesManager $capabilitiesManager
  ) {
    $this->capabilitiesManager = $capabilitiesManager;
  }

  public function isRestricted(): bool {
    $capability = $this->capabilitiesManager->getCapability('detailedAnalytics');
    return $capability === null || $capability->isRestricted;
  }

  public function getRestrictedMessage(): string {
    return __('Detailed analytics are not available for this site.', 'mailpoet-premium');
  }

  /**
   * @throws ApiException
   */
  public function guardRestAccess(string $errorCode = 'mailpoet_detailed_analytics_restricted'): void {
    if (!$this->isRestricted()) {
      return;
    }
    throw new ApiException($this->getRestrictedMessage(), 403, $errorCode);
  }
}
