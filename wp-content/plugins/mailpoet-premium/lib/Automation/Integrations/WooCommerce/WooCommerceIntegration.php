<?php declare(strict_types = 1);

namespace MailPoet\Premium\Automation\Integrations\WooCommerce;

if (!defined('ABSPATH')) exit;


use MailPoet\Automation\Engine\Integration;
use MailPoet\Automation\Engine\Registry;
use MailPoet\Automation\Engine\WordPress;
use MailPoet\Premium\Automation\Integrations\WooCommerce\Actions\Extenders\ReviewCrossSellHandler;
use MailPoet\Premium\Automation\Integrations\WooCommerce\Subjects\ReviewSubject;
use MailPoet\Premium\Automation\Integrations\WooCommerce\Triggers\MadeAReviewTrigger;

class WooCommerceIntegration implements Integration {

  /** @var MadeAReviewTrigger */
  private $madeAReview;

  /** @var ReviewSubject */
  private $reviewSubject;

  /** @var ReviewCrossSellHandler */
  private $reviewCrossSellHandler;

  /** @var WordPress */
  private $wordPress;

  public function __construct(
    MadeAReviewTrigger $madeAReview,
    ReviewSubject $reviewSubject,
    ReviewCrossSellHandler $reviewCrossSellHandler,
    WordPress $wordPress
  ) {
    $this->madeAReview = $madeAReview;
    $this->reviewSubject = $reviewSubject;
    $this->reviewCrossSellHandler = $reviewCrossSellHandler;
    $this->wordPress = $wordPress;
  }

  public function register(Registry $registry): void {
    $registry->addTrigger($this->madeAReview);
    $registry->addSubject($this->reviewSubject);

    $this->wordPress->addFilter('mailpoet_automation_send_email_action_meta', [$this->reviewCrossSellHandler, 'addReviewCrossSells'], 10, 2);
    $this->wordPress->addFilter('mailpoet_dynamic_products_meta', [$this->reviewCrossSellHandler, 'handleDynamicProductsMeta'], 10, 2);
  }
}
