<?php // phpcs:ignore SlevomatCodingStandard.TypeHints.DeclareStrictTypes.DeclareStrictTypesMissing

namespace MailPoet\Premium\Subscriber\Stats;

if (!defined('ABSPATH')) exit;


use MailPoet\Entities\NewsletterEntity;
use MailPoet\Entities\NewsletterLinkEntity;
use MailPoet\Entities\StatisticsClickEntity;
use MailPoet\Entities\StatisticsOpenEntity;
use MailPoet\Entities\StatisticsUnsubscribeEntity;
use MailPoet\Entities\StatisticsWooCommercePurchaseEntity;
use MailPoet\Listing\ListingDefinition;
use MailPoet\Listing\ListingRepository;
use MailPoet\WooCommerce\Helper as WCHelper;
use MailPoetVendor\Doctrine\DBAL\ArrayParameterType;
use MailPoetVendor\Doctrine\DBAL\ParameterType;
use MailPoetVendor\Doctrine\ORM\EntityManager;
use MailPoetVendor\Doctrine\ORM\QueryBuilder;

class SubscriberNewsletterStatsRepository extends ListingRepository {
  const SORT_BY_DATE = 'date';
  const SORT_BY_EVENT_TYPE = 'eventType';

  /** @var EntityManager */
  private $entityManager;

  /** @var WCHelper */
  private $wooCommerceHelper;

  public function __construct(
    EntityManager $entityManager,
    WCHelper $wooCommerceHelper
  ) {
    parent::__construct($entityManager);
    $this->entityManager = $entityManager;
    $this->wooCommerceHelper = $wooCommerceHelper;
  }

  /**
   * @return SubscriberActivityEvent[]
   */
  public function getActivityData(ListingDefinition $definition): array {
    $index = $this->buildEventIndexQuery($definition);
    if ($index === null) {
      return [];
    }

    $sql = $index['sql'] . ' ' . $this->buildOrderBy($definition->getSortBy(), $definition->getSortOrder())
      . ' LIMIT :limit OFFSET :offset';
    $rows = $this->entityManager->getConnection()->executeQuery(
      $sql,
      $index['params'] + ['limit' => $definition->getLimit(), 'offset' => $definition->getOffset()],
      $index['types'] + ['limit' => ParameterType::INTEGER, 'offset' => ParameterType::INTEGER]
    )->fetchAllAssociative();

    return $this->hydrateEvents($rows);
  }

  public function getActivityCount(ListingDefinition $definition): int {
    $index = $this->buildEventIndexQuery($definition);
    if ($index === null) {
      return 0;
    }
    $sql = 'SELECT COUNT(*) FROM (' . $index['sql'] . ') events';
    $count = $this->entityManager->getConnection()->executeQuery($sql, $index['params'], $index['types'])->fetchOne();
    return is_numeric($count) ? (int)$count : 0;
  }

  /**
   * Builds a UNION ALL over the stats tables that yields one (type, id,
   * created_at) row per activity event, so sorting, counting and paging happen
   * in SQL. Only the rows of the requested page are hydrated afterwards.
   *
   * @return array{sql: string, params: array<string, mixed>, types: array<string, int|string>}|null
   */
  private function buildEventIndexQuery(ListingDefinition $definition): ?array {
    $subscriberId = (int)($definition->getParameters()['id'] ?? 0);
    if ($subscriberId <= 0) {
      return null;
    }

    $filters = $definition->getFilters() ?: [];
    $types = $this->normalizeEventTypes($filters['eventType'] ?? null);
    $from = $this->parseDate($filters['fromDate'] ?? null);
    $to = $this->parseDate($filters['toDate'] ?? null);
    // `toDate` arrives as a whole-day boundary (Y-m-d), so make it inclusive of
    // the entire day to match the logs listing's date-range semantics.
    if ($to instanceof \DateTimeImmutable) {
      $to = $to->setTime(23, 59, 59);
    }
    $search = $definition->getSearch();
    $search = ($search !== null && strlen(trim($search)) > 0) ? trim($search) : null;

    $params = ['subscriber' => $subscriberId];
    $paramTypes = ['subscriber' => ParameterType::INTEGER];
    $dateConstraint = '';
    if ($from instanceof \DateTimeInterface) {
      $dateConstraint .= ' AND stat.created_at >= :fromDate';
      $params['fromDate'] = $from->format('Y-m-d H:i:s');
      $paramTypes['fromDate'] = ParameterType::STRING;
    }
    if ($to instanceof \DateTimeInterface) {
      $dateConstraint .= ' AND stat.created_at <= :toDate';
      $params['toDate'] = $to->format('Y-m-d H:i:s');
      $paramTypes['toDate'] = ParameterType::STRING;
    }
    if ($search !== null) {
      $params['search'] = '%' . $this->escapeLike($search) . '%';
      $paramTypes['search'] = ParameterType::STRING;
    }

    $newslettersTable = $this->getTableName(NewsletterEntity::class);
    $queries = [];
    if (in_array(SubscriberActivityEvent::TYPE_OPEN, $types, true)) {
      $queries[] = $this->buildIndexSelect(
        SubscriberActivityEvent::TYPE_OPEN,
        $this->getTableName(StatisticsOpenEntity::class),
        "INNER JOIN {$newslettersTable} n ON n.id = stat.newsletter_id",
        $dateConstraint,
        $search !== null ? ' AND n.subject LIKE :search' : ''
      );
    }
    if (in_array(SubscriberActivityEvent::TYPE_CLICK, $types, true)) {
      $linksTable = $this->getTableName(NewsletterLinkEntity::class);
      $queries[] = $this->buildIndexSelect(
        SubscriberActivityEvent::TYPE_CLICK,
        $this->getTableName(StatisticsClickEntity::class),
        "INNER JOIN {$linksTable} l ON l.id = stat.link_id INNER JOIN {$newslettersTable} n ON n.id = stat.newsletter_id",
        $dateConstraint,
        $search !== null ? ' AND (n.subject LIKE :search OR l.url LIKE :search)' : ''
      );
    }
    if (in_array(SubscriberActivityEvent::TYPE_PURCHASE, $types, true)) {
      $params['states'] = $this->wooCommerceHelper->getPurchaseStates();
      $paramTypes['states'] = ArrayParameterType::STRING;
      $queries[] = $this->buildIndexSelect(
        SubscriberActivityEvent::TYPE_PURCHASE,
        $this->getTableName(StatisticsWooCommercePurchaseEntity::class),
        "LEFT JOIN {$newslettersTable} n ON n.id = stat.newsletter_id",
        $dateConstraint . ' AND stat.status IN (:states)',
        $search !== null ? ' AND (n.subject LIKE :search OR CAST(stat.order_id AS CHAR) LIKE :search)' : ''
      );
    }
    if (in_array(SubscriberActivityEvent::TYPE_UNSUBSCRIBE, $types, true)) {
      $queries[] = $this->buildIndexSelect(
        SubscriberActivityEvent::TYPE_UNSUBSCRIBE,
        $this->getTableName(StatisticsUnsubscribeEntity::class),
        "LEFT JOIN {$newslettersTable} n ON n.id = stat.newsletter_id",
        $dateConstraint,
        $search !== null ? ' AND (n.subject LIKE :search OR stat.reason_text LIKE :search)' : ''
      );
    }

    return [
      'sql' => implode(' UNION ALL ', $queries),
      'params' => $params,
      'types' => $paramTypes,
    ];
  }

  /**
   * One member of the UNION ALL: `(type, id, created_at)` for a single stats
   * table, aliased `stat`, scoped to the subscriber.
   *
   * @param string $type one of the SubscriberActivityEvent constants, so inlining it is safe
   * @param string $table the stats table name
   * @param string $joins JOIN clauses; the newsletter must be aliased `n`
   * @param string $dateConstraint extra `AND ...` conditions, already parameterized
   * @param string $searchConstraint extra `AND ...` search condition, or empty
   */
  private function buildIndexSelect(string $type, string $table, string $joins, string $dateConstraint, string $searchConstraint): string {
    return "SELECT '{$type}' AS type, stat.id AS id, stat.created_at AS created_at"
      . " FROM {$table} stat {$joins}"
      . " WHERE stat.subscriber_id = :subscriber{$dateConstraint}{$searchConstraint}";
  }

  private function buildOrderBy(string $sortBy, string $sortOrder): string {
    $direction = strtolower($sortOrder) === 'asc' ? 'ASC' : 'DESC';
    $orderBy = [];
    if ($sortBy === self::SORT_BY_EVENT_TYPE) {
      // Sort by the translated label the user actually sees, not the internal
      // type, so the order matches the visible Event column.
      $labels = [];
      foreach ($this->normalizeEventTypes(null) as $type) {
        $labels[$type] = $this->getEventTypeLabel($type);
      }
      asort($labels, SORT_STRING);
      $cases = '';
      foreach (array_keys($labels) as $rank => $type) {
        $cases .= " WHEN '{$type}' THEN {$rank}";
      }
      $orderBy[] = "CASE type{$cases} END {$direction}";
    }
    $orderBy[] = "created_at {$direction}";
    // `id` is only unique within one stats table, so `type` makes the order
    // total across the UNION and keeps paging stable.
    $orderBy[] = "type {$direction}";
    $orderBy[] = "id {$direction}";
    return 'ORDER BY ' . implode(', ', $orderBy);
  }

  /**
   * @param array<int, array<string, mixed>> $rows
   * @return SubscriberActivityEvent[]
   */
  private function hydrateEvents(array $rows): array {
    $index = [];
    foreach ($rows as $row) {
      $type = $row['type'] ?? null;
      $id = $row['id'] ?? null;
      if (is_string($type) && is_numeric($id)) {
        $index[] = ['type' => $type, 'id' => (int)$id];
      }
    }

    $idsByType = [];
    foreach ($index as $row) {
      $idsByType[$row['type']][] = $row['id'];
    }

    $entities = [
      SubscriberActivityEvent::TYPE_OPEN => $this->findByIds(StatisticsOpenEntity::class, 'o', 'o, n', ['o.newsletter' => 'n'], $idsByType[SubscriberActivityEvent::TYPE_OPEN] ?? []),
      SubscriberActivityEvent::TYPE_CLICK => $this->findByIds(StatisticsClickEntity::class, 'c', 'c, l, n', ['c.link' => 'l', 'c.newsletter' => 'n'], $idsByType[SubscriberActivityEvent::TYPE_CLICK] ?? []),
      SubscriberActivityEvent::TYPE_PURCHASE => $this->findByIds(StatisticsWooCommercePurchaseEntity::class, 'wp', 'wp, n', ['wp.newsletter' => 'n'], $idsByType[SubscriberActivityEvent::TYPE_PURCHASE] ?? []),
      SubscriberActivityEvent::TYPE_UNSUBSCRIBE => $this->findByIds(StatisticsUnsubscribeEntity::class, 'u', 'u, n', ['u.newsletter' => 'n'], $idsByType[SubscriberActivityEvent::TYPE_UNSUBSCRIBE] ?? []),
    ];

    $events = [];
    foreach ($index as $row) {
      $entity = $entities[$row['type']][$row['id']] ?? null;
      $createdAt = $entity ? $entity->getCreatedAt() : null;
      if (!$createdAt instanceof \DateTimeInterface) {
        continue;
      }
      if ($entity instanceof StatisticsOpenEntity) {
        $events[] = SubscriberActivityEvent::forOpen($entity, $createdAt);
      } elseif ($entity instanceof StatisticsClickEntity) {
        $events[] = SubscriberActivityEvent::forClick($entity, $createdAt);
      } elseif ($entity instanceof StatisticsWooCommercePurchaseEntity) {
        $events[] = SubscriberActivityEvent::forPurchase($entity, $createdAt);
      } elseif ($entity instanceof StatisticsUnsubscribeEntity) {
        $events[] = SubscriberActivityEvent::forUnsubscribe($entity, $createdAt);
      }
    }
    return $events;
  }

  /**
   * @template T of object
   * @param class-string<T> $entityClass
   * @param array<string, string> $joins association => alias
   * @param int[] $ids
   * @return array<int, T>
   */
  private function findByIds(string $entityClass, string $alias, string $select, array $joins, array $ids): array {
    if (!$ids) {
      return [];
    }
    $queryBuilder = clone $this->queryBuilder;
    $queryBuilder
      ->select($select)
      ->from($entityClass, $alias)
      ->where("{$alias}.id IN (:ids)")
      ->setParameter('ids', $ids);
    foreach ($joins as $association => $joinAlias) {
      $queryBuilder->leftJoin($association, $joinAlias);
    }

    $byId = [];
    foreach ($queryBuilder->getQuery()->getResult() as $entity) {
      $byId[(int)$entity->getId()] = $entity;
    }
    return $byId;
  }

  /**
   * @param class-string $entityClass
   */
  private function getTableName(string $entityClass): string {
    return $this->entityManager->getClassMetadata($entityClass)->getTableName();
  }

  private function getEventTypeLabel(string $type): string {
    $labels = [
      SubscriberActivityEvent::TYPE_OPEN => __('Email open', 'mailpoet-premium'),
      SubscriberActivityEvent::TYPE_CLICK => __('Link click', 'mailpoet-premium'),
      SubscriberActivityEvent::TYPE_PURCHASE => __('Purchase', 'mailpoet-premium'),
      SubscriberActivityEvent::TYPE_UNSUBSCRIBE => __('Unsubscribe', 'mailpoet-premium'),
    ];
    return $labels[$type] ?? $type;
  }

  /**
   * @param mixed $eventType
   * @return string[]
   */
  private function normalizeEventTypes($eventType): array {
    $all = [
      SubscriberActivityEvent::TYPE_OPEN,
      SubscriberActivityEvent::TYPE_CLICK,
      SubscriberActivityEvent::TYPE_PURCHASE,
      SubscriberActivityEvent::TYPE_UNSUBSCRIBE,
    ];
    if ($eventType === null || $eventType === '' || $eventType === 'all') {
      return $all;
    }
    $requested = is_array($eventType) ? $eventType : [$eventType];
    $requested = array_filter($requested, function ($value): bool {
      return is_string($value);
    });
    $filtered = array_values(array_intersect($all, $requested));
    return empty($filtered) ? $all : $filtered;
  }

  /**
   * @param mixed $value
   */
  private function parseDate($value): ?\DateTimeInterface {
    if (!is_string($value) || trim($value) === '') {
      return null;
    }
    try {
      return new \DateTimeImmutable($value);
    } catch (\Exception $e) {
      return null;
    }
  }

  private function escapeLike(string $search): string {
    return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
  }

  protected function applySelectClause(QueryBuilder $queryBuilder) {
    $queryBuilder->select('o, n');
  }

  protected function applyFromClause(QueryBuilder $queryBuilder) {
    $queryBuilder->from(StatisticsOpenEntity::class, 'o')
      ->join('o.newsletter', 'n');
  }

  protected function applyGroup(QueryBuilder $queryBuilder, string $group) {
    // No groups for this listing
  }

  /**
   * @param QueryBuilder $queryBuilder
   * @param string $search
   * @param array<mixed> $parameters
   * @return void
   */
  protected function applySearch(QueryBuilder $queryBuilder, string $search, array $parameters = []) {
    $search = $this->escapeLike($search);
    $queryBuilder
      ->andWhere('n.subject LIKE :search')
      ->setParameter('search', "%$search%");
  }

  /**
   * @param QueryBuilder $queryBuilder
   * @param array<int|string> $filters
   */
  protected function applyFilters(QueryBuilder $queryBuilder, array $filters) {
    // Filtering is handled by buildEvents() for the activity feed
  }

  /**
   * @param QueryBuilder $queryBuilder
   * @param array<int|string> $parameters
   */
  protected function applyParameters(QueryBuilder $queryBuilder, array $parameters) {
    $subscriberId = $parameters['id'] ?? null;
    if ($subscriberId) {
      $queryBuilder
        ->andWhere('o.subscriber = :subscriber')
        ->setParameter('subscriber', $subscriberId);
    }
  }

  protected function applySorting(QueryBuilder $queryBuilder, string $sortBy, string $sortOrder) {
    $queryBuilder->addOrderBy('n.sentAt', 'desc');
  }
}
