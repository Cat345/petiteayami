<?php declare(strict_types = 1);

namespace MailPoet\Premium\Automation\Integrations\MailPoetPremium\Analytics\Endpoints;

if (!defined('ABSPATH')) exit;


use MailPoet\API\REST\Request;
use MailPoet\API\REST\Response;
use MailPoet\Automation\Engine\API\Endpoint;
use MailPoet\Automation\Engine\Exceptions;
use MailPoet\Automation\Engine\Storage\AutomationStorage;
use MailPoet\Automation\Integrations\MailPoet\Analytics\Entities\Query;
use MailPoet\Premium\Automation\Integrations\MailPoetPremium\Analytics\Controller\SubscriberController;
use MailPoet\Premium\Util\License\DetailedAnalyticsGuard;
use MailPoet\Validator\Builder;

class SubscriberEndpoint extends Endpoint {
  /** @var AutomationStorage */
  private $automationStorage;

  /** @var SubscriberController */
  private $subscriberController;

  /** @var DetailedAnalyticsGuard */
  private $detailedAnalyticsGuard;

  public function __construct(
    AutomationStorage $automationStorage,
    SubscriberController $subscriberController,
    DetailedAnalyticsGuard $detailedAnalyticsGuard
  ) {
    $this->automationStorage = $automationStorage;
    $this->subscriberController = $subscriberController;
    $this->detailedAnalyticsGuard = $detailedAnalyticsGuard;
  }

  public static function getRequestSchema(): array {
    return [
      'id' => Builder::integer()->required(),
      'query' => Query::getRequestSchema(),
    ];
  }

  public function handle(Request $request): Response {
    $this->detailedAnalyticsGuard->guardRestAccess();

    $id = absint(is_numeric($request->getParam('id')) ? $request->getParam('id') : 0);
    $automation = $this->automationStorage->getAutomation($id);
    if (!$automation) {
      throw Exceptions::automationNotFound($id);
    }

    $query = Query::fromRequest($request);
    $result = $this->subscriberController->getSubscribersForAutomation($automation, $query);
    return new Response($result);
  }
}
