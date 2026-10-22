<?php declare(strict_types = 1);

namespace MailPoet\Premium\Newsletter\Stats\RestApi\Endpoints;

if (!defined('ABSPATH')) exit;


use MailPoet\API\REST\Request;
use MailPoet\Entities\NewsletterEntity;
use MailPoet\Newsletter\NewslettersRepository;
use MailPoet\Premium\Newsletter\Stats\UnsubscribeReasons;
use MailPoet\Premium\Util\License\DetailedAnalyticsGuard;
use MailPoet\WP\Functions as WPFunctions;

class UnsubscribeReasonsEndpoint extends AbstractNewsletterStatsListingEndpoint {
  /** @var UnsubscribeReasons */
  private $unsubscribeReasons;

  public function __construct(
    NewslettersRepository $newslettersRepository,
    DetailedAnalyticsGuard $detailedAnalyticsGuard,
    WPFunctions $wp,
    UnsubscribeReasons $unsubscribeReasons
  ) {
    parent::__construct($newslettersRepository, $detailedAnalyticsGuard, $wp);
    $this->unsubscribeReasons = $unsubscribeReasons;
  }

  protected function getListingData(
    NewsletterEntity $newsletter,
    Request $request,
    int $perPage,
    int $page
  ): array {
    return $this->unsubscribeReasons->get(
      $this->getLegacyListingParameters($newsletter, $request, $perPage, $page)
    );
  }

  protected function buildItems(array $items): array {
    return array_map(function (array $item): array {
      $reason = isset($item['reason']) && is_scalar($item['reason']) ? (string)$item['reason'] : '';
      $item['reason'] = $reason;
      $item['count'] = isset($item['cnt']) && is_numeric($item['cnt']) ? (int)$item['cnt'] : 0;
      $item['id'] = $reason;
      return $item;
    }, $items);
  }
}
