<?php declare(strict_types = 1);

namespace MailPoet\Premium\Newsletter\Stats\RestApi\Endpoints;

if (!defined('ABSPATH')) exit;


use MailPoet\API\REST\Request;
use MailPoet\Entities\NewsletterEntity;
use MailPoet\Newsletter\NewslettersRepository;
use MailPoet\Premium\Newsletter\Stats\Bounces;
use MailPoet\Premium\Util\License\DetailedAnalyticsGuard;
use MailPoet\WP\Functions as WPFunctions;

class BouncesEndpoint extends AbstractNewsletterStatsListingEndpoint {
  /** @var Bounces */
  private $bounces;

  public function __construct(
    NewslettersRepository $newslettersRepository,
    DetailedAnalyticsGuard $detailedAnalyticsGuard,
    WPFunctions $wp,
    Bounces $bounces
  ) {
    parent::__construct($newslettersRepository, $detailedAnalyticsGuard, $wp);
    $this->bounces = $bounces;
  }

  protected function getListingData(
    NewsletterEntity $newsletter,
    Request $request,
    int $perPage,
    int $page
  ): array {
    return $this->bounces->get(
      $this->getLegacyListingParameters($newsletter, $request, $perPage, $page)
    );
  }
}
