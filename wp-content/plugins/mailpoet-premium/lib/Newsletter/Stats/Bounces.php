<?php declare(strict_types = 1);

namespace MailPoet\Premium\Newsletter\Stats;

if (!defined('ABSPATH')) exit;


use MailPoet\Entities\StatisticsBounceEntity;
use MailPoet\Entities\SubscriberEntity;
use MailPoet\Listing;
use MailPoetVendor\Doctrine\DBAL\ParameterType;
use MailPoetVendor\Doctrine\ORM\EntityManager;

class Bounces {
  use StatsListingFilterTrait;

  const STATUS_BOUNCED = 'bounced';

  /** @var Listing\Handler */
  private $listingHandler;

  /** @var EntityManager */
  private $entityManager;

  public function __construct(
    Listing\Handler $listingHandler,
    EntityManager $entityManager
  ) {
    $this->listingHandler = $listingHandler;
    $this->entityManager = $entityManager;
  }

  /**
   * @param array<string, mixed> $data
   * @return Listing\ListingDefinition
   */
  private function parseData($data): Listing\ListingDefinition {
    // check if sort order was specified or default to "desc"
    $data['sort_order'] = ($data['sort_order'] ?? null) === 'asc' ? 'asc' : 'desc';

    // sanitize sort by
    $sortableColumns = ['email', 'status', 'created_at'];
    $sortBy = (!empty($data['sort_by']) && in_array($data['sort_by'], $sortableColumns, true))
      ? $data['sort_by']
      : '';

    if (empty($sortBy)) {
      $sortBy = 'created_at';
    }
    $data['sort_by'] = $sortBy;
    return $this->listingHandler->getListingDefinition($data);
  }

  /**
   * @param array<string, mixed> $data
   *
   * @return array{
   *   count: int,
   *   filters: array{},
   *   groups: array{},
   *   items: array<int, array<string, mixed>>
   * }
   */
  public function get($data = []): array {
    $definition = $this->parseData($data);
    $connection = $this->entityManager->getConnection();

    $parameters = ['newsletterId' => intval($definition->getParameters()['id'])];
    $types = ['newsletterId' => ParameterType::INTEGER];
    $search = $this->sanitizeSearchLike($definition->getSearch());
    if ($search !== null) {
      $parameters['search'] = $search;
      $types['search'] = ParameterType::STRING;
    }

    $queryBody = $this->getBouncesQueryBody($definition, $search !== null);
    $count = $connection->executeQuery('SELECT COUNT(*) ' . $queryBody, $parameters, $types)->fetchOne();
    $count = is_numeric($count) ? (int)$count : 0;

    $query = 'SELECT ' . join(', ', self::getFields()) . ' ' . $queryBody
      . " ORDER BY {$definition->getSortBy()} {$definition->getSortOrder()} LIMIT :limit OFFSET :offset ";
    $items = $connection
      ->executeQuery(
        $query,
        $parameters + ['limit' => $definition->getLimit(), 'offset' => $definition->getOffset()],
        $types + ['limit' => ParameterType::INTEGER, 'offset' => ParameterType::INTEGER]
      )
      ->fetchAllAssociative();

    return [
      'count' => $count,
      'filters' => [],
      'groups' => [],
      'items' => $items,
    ];
  }

  /**
   * Everything after SELECT, shared by the count and page queries. The search is
   * applied to the joined subscribers only, so it never scans the whole
   * subscriber table.
   */
  private function getBouncesQueryBody(Listing\ListingDefinition $definition, bool $withSearch): string {
    $subscriberTable = $this->entityManager->getClassMetadata(SubscriberEntity::class)->getTableName();
    $bouncesTable = $this->entityManager->getClassMetadata(StatisticsBounceEntity::class)->getTableName();

    $searchConstraint = $withSearch
      ? ' AND (subscribers.email LIKE :search OR subscribers.first_name LIKE :search OR subscribers.last_name LIKE :search) '
      : '';
    $dateRange = $this->sanitizeDateRange($definition->getFilters());
    $dateConstraint = $this->buildDateRangeConstraint('bounces.created_at', $dateRange['from'], $dateRange['to']);

    return 'FROM ' . $bouncesTable . ' bounces '
      . 'LEFT JOIN ' . $subscriberTable . ' subscribers ON subscribers.id = bounces.subscriber_id '
      . 'WHERE bounces.newsletter_id = :newsletterId ' . $searchConstraint . $dateConstraint;
  }

  /**
   * @return array<int, string>
   */
  private static function getFields(): array {
    return [
      'bounces.id',
      'bounces.created_at',
      'bounces.subscriber_id',
      "'" . self::STATUS_BOUNCED . "' as status",
      'subscribers.email',
      'subscribers.first_name',
      'subscribers.last_name',
    ];
  }
}
