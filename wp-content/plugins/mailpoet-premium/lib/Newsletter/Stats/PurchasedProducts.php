<?php // phpcs:ignore SlevomatCodingStandard.TypeHints.DeclareStrictTypes.DeclareStrictTypesMissing

namespace MailPoet\Premium\Newsletter\Stats;

if (!defined('ABSPATH')) exit;


use MailPoet\Entities\NewsletterEntity;
use MailPoet\Newsletter\NewslettersRepository;
use MailPoet\WooCommerce\Helper as WCHelper;
use MailPoet\WooCommerce\OrderAttributionRevenueReader;
use MailPoet\WP\Functions as WPFunctions;

class PurchasedProducts {

  /** @var WCHelper */
  private $woocommerceHelper;

  /** @var WPFunctions */
  private $wp;

  /** @var NewslettersRepository */
  private $newslettersRepository;

  /** @var OrderAttributionRevenueReader */
  private $orderAttributionRevenueReader;

  public function __construct(
    WCHelper $woocommerceHelper,
    NewslettersRepository $newslettersRepository,
    WPFunctions $wp,
    OrderAttributionRevenueReader $orderAttributionRevenueReader
  ) {
    $this->woocommerceHelper = $woocommerceHelper;
    $this->wp = $wp;
    $this->newslettersRepository = $newslettersRepository;
    $this->orderAttributionRevenueReader = $orderAttributionRevenueReader;
  }

  public function getStats($newsletterId) {
    if (!$newsletterId || !$this->woocommerceHelper->isWooCommerceActive()) {
      return [];
    }
    $newsletter = $this->newslettersRepository->findOneById($newsletterId);
    if (!$newsletter instanceof NewsletterEntity) {
      return [];
    }

    $revenueStatus = $this->woocommerceHelper->getPurchaseStates();
    $currency = $this->woocommerceHelper->getWoocommerceCurrency();

    $orderIds = $this->getWooBackedOrderIds((int)$newsletterId, $currency, $revenueStatus);
    if ($orderIds === null) {
      $orderIds = $this->getLegacyOrderIds((int)$newsletterId, $currency, $revenueStatus);
    }

    $result = $this->aggregateOrderItems($orderIds);
    $result = $this->formatPrices($result, $currency);
    $result = $this->addThumbnails($result);
    $result = $this->sortProducts($result);
    return $result;
  }

  /**
   * Orders attributed to the newsletter by the Woo order-attribution read
   * model, or null when that model is unavailable.
   *
   * @param string[] $revenueStatus
   * @return int[]|null
   */
  private function getWooBackedOrderIds(int $newsletterId, string $currency, array $revenueStatus): ?array {
    $rows = $this->orderAttributionRevenueReader->getNewsletterOrderRows(
      [$newsletterId],
      new \DateTimeImmutable('@0'),
      new \DateTimeImmutable('now', new \DateTimeZone('UTC'))
    );
    if ($rows === null) {
      return null;
    }

    $orderIds = [];
    foreach ($rows as $row) {
      $status = (string)$row['status'];
      if (strpos($status, 'wc-') === 0) {
        $status = substr($status, 3);
      }
      if (in_array($status, $revenueStatus, true)) {
        $orderIds[] = (int)$row['order_id'];
      }
    }
    return $this->filterOrderIdsByCurrency(array_values(array_unique($orderIds)), $currency);
  }

  /**
   * @param string[] $revenueStatus
   * @return int[]
   */
  private function getLegacyOrderIds(int $newsletterId, string $currency, array $revenueStatus): array {
    if (!$revenueStatus) {
      return [];
    }
    global $wpdb;

    // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Dynamic fragments are trusted placeholders; values are prepared below.
    $query = $wpdb->prepare(
      '
        SELECT DISTINCT order_id
        FROM %i
        WHERE newsletter_id = %d
          AND order_currency = %s
          AND status IN (' . implode(',', array_fill(0, count($revenueStatus), '%s')) . ')
      ',
      array_merge(
        [$wpdb->prefix . 'mailpoet_statistics_woocommerce_purchases', $newsletterId, $currency],
        $revenueStatus
      )
    );
    // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

    return array_map('intval', (array)$wpdb->get_col($query)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared above.
  }

  /**
   * @param int[] $orderIds
   * @return int[]
   */
  private function filterOrderIdsByCurrency(array $orderIds, string $currency): array {
    if (!$orderIds) {
      return [];
    }
    global $wpdb;

    $placeholders = implode(',', array_fill(0, count($orderIds), '%d'));
    // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Dynamic fragments are trusted placeholders; values are prepared below.
    if ($this->woocommerceHelper->isWooCommerceCustomOrdersTableEnabled()) {
      $query = $wpdb->prepare(
        'SELECT id FROM %i WHERE id IN (' . $placeholders . ') AND currency = %s',
        array_merge([$wpdb->prefix . 'wc_orders'], $orderIds, [$currency])
      );
    } else {
      $query = $wpdb->prepare(
        'SELECT post_id FROM %i WHERE post_id IN (' . $placeholders . ') AND meta_key = %s AND meta_value = %s',
        array_merge([$wpdb->postmeta], $orderIds, ['_order_currency', $currency])
      );
    }
    // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

    return array_map('intval', (array)$wpdb->get_col($query)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared above.
  }

  /**
   * Sums quantity and line total per product across the given orders in one
   * query, so the stats never hydrate individual WC_Order objects.
   *
   * @param int[] $orderIds
   * @return array<int, array<string, int|float|string>>
   */
  private function aggregateOrderItems(array $orderIds): array {
    if (!$orderIds) {
      return [];
    }
    global $wpdb;

    $placeholders = implode(',', array_fill(0, count($orderIds), '%d'));
    // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Dynamic fragments are trusted identifiers/placeholders; values are prepared below.
    $query = $wpdb->prepare(
      '
        SELECT
          product.meta_value AS product_id,
          MIN(items.order_item_name) AS name,
          SUM(qty.meta_value) AS count,
          SUM(total.meta_value) AS total
        FROM %i items
        INNER JOIN %i product ON product.order_item_id = items.order_item_id AND product.meta_key = %s
        INNER JOIN %i qty ON qty.order_item_id = items.order_item_id AND qty.meta_key = %s
        INNER JOIN %i total ON total.order_item_id = items.order_item_id AND total.meta_key = %s
        WHERE items.order_item_type = %s
          AND items.order_id IN (' . $placeholders . ')
        GROUP BY product.meta_value
      ',
      array_merge(
        [
          $wpdb->prefix . 'woocommerce_order_items',
          $wpdb->prefix . 'woocommerce_order_itemmeta',
          '_product_id',
          $wpdb->prefix . 'woocommerce_order_itemmeta',
          '_qty',
          $wpdb->prefix . 'woocommerce_order_itemmeta',
          '_line_total',
          'line_item',
        ],
        $orderIds
      )
    );
    // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

    $result = [];
    foreach ((array)$wpdb->get_results($query, ARRAY_A) as $row) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared above.
      $productId = (int)$row['product_id'];
      $result[$productId] = [
        'name' => (string)$row['name'],
        'count' => (int)(float)$row['count'],
        'total' => (float)$row['total'],
        'product_id' => $productId,
      ];
    }
    return $result;
  }

  private function formatPrices($products, $currency) {
    $result = [];
    foreach ($products as $productId => $product) {
      $product['formatted_total'] = $this->woocommerceHelper->getRawPrice($product['total'], ['currency' => $currency]);
      $result[$productId] = $product;
    }
    return $result;
  }

  private function addThumbnails($products) {
    $result = [];
    foreach ($products as $productId => $product) {
      $image = $this->getProductImage($productId);
      if (is_array($image)) {
        list($src) = $image;
        $product['image_url'] = $src;
      }
      $result[$productId] = $product;
    }
    return $result;
  }

  private function getProductImage($productId) {
    $wooProduct = $this->woocommerceHelper->wcGetProduct($productId);
    if (!$wooProduct) {
      return false;
    }
    return $this->wp->wpGetAttachmentImageSrc($wooProduct->get_image_id());
  }

  private function sortProducts($products) {
    usort($products, function($a, $b) {
      $retval = $b['count'] - $a['count'];
      if ($retval === 0) {
        $retval = $b['total'] - $a['total'];
      }
      return (int)ceil($retval);
    });
    return $products;
  }
}
