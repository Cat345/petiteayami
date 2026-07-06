<?php declare(strict_types = 1);

namespace MailPoet\Premium\Newsletter\Stats;

if (!defined('ABSPATH')) exit;


use MailPoet\Entities\NewsletterEntity;
use MailPoet\Entities\StatisticsBounceEntity;
use MailPoet\Entities\StatisticsClickEntity;
use MailPoet\Entities\StatisticsNewsletterEntity;
use MailPoet\Entities\StatisticsOpenEntity;
use MailPoet\Entities\StatisticsUnsubscribeEntity;
use MailPoet\Entities\SubscriberEntity;
use MailPoet\Entities\UserAgentEntity;
use MailPoet\Settings\TrackingConfig;
use MailPoetVendor\Doctrine\DBAL\ParameterType;
use MailPoetVendor\Doctrine\ORM\EntityManager;

/**
 * Premium-side per-recipient exporter. Builds one row per subscriber that
 * received the campaign, joining open/click/bounce/unsubscribe stats. Hooked
 * into the free plugin via the `mailpoet_statistics_export_recipient_rows`
 * filter.
 *
 * Column order MUST match StatisticsExporter::getRecipientHeaders() in the
 * free plugin.
 */
class RecipientsExporter {
  /** @var EntityManager */
  private $entityManager;

  /** @var TrackingConfig */
  private $trackingConfig;

  public function __construct(
    EntityManager $entityManager,
    TrackingConfig $trackingConfig
  ) {
    $this->entityManager = $entityManager;
    $this->trackingConfig = $trackingConfig;
  }

  /**
   * @param array<array<int|string|float|null>> $rows
   * @return array<array<int|string|float|null>>
   */
  public function getRows(array $rows, NewsletterEntity $newsletter): array {
    $newsletterId = (int)$newsletter->getId();
    if ($newsletterId <= 0) {
      return $rows;
    }

    $subscribersTable = $this->entityManager->getClassMetadata(SubscriberEntity::class)->getTableName();
    $sentTable = $this->entityManager->getClassMetadata(StatisticsNewsletterEntity::class)->getTableName();
    $opensTable = $this->entityManager->getClassMetadata(StatisticsOpenEntity::class)->getTableName();
    $clicksTable = $this->entityManager->getClassMetadata(StatisticsClickEntity::class)->getTableName();
    $unsubscribesTable = $this->entityManager->getClassMetadata(StatisticsUnsubscribeEntity::class)->getTableName();
    $bouncesTable = $this->entityManager->getClassMetadata(StatisticsBounceEntity::class)->getTableName();

    $humanType = UserAgentEntity::USER_AGENT_TYPE_HUMAN;
    $machineType = UserAgentEntity::USER_AGENT_TYPE_MACHINE;

    $countedOpensCondition = $this->trackingConfig->areOpensSeparated()
      ? '(counted_opens.user_agent_type = :humanType OR counted_opens.user_agent_type IS NULL)'
      : '(counted_opens.user_agent_type != :machineType OR counted_opens.user_agent_type IS NULL)';

    $sql = "SELECT
        s.id AS subscriber_id,
        s.email AS email,
        s.first_name AS first_name,
        s.last_name AS last_name,
        s.status AS status,
        MIN(counted_opens.created_at) AS first_open_at,
        COUNT(DISTINCT counted_opens.id) AS open_count,
        COUNT(DISTINCT machine_opens.id) AS machine_open_count,
        COUNT(DISTINCT clicks.id) AS click_count,
        MAX(CASE WHEN unsubscribes.id IS NULL THEN 0 ELSE 1 END) AS unsubscribed,
        MAX(CASE WHEN bounces.id IS NULL THEN 0 ELSE 1 END) AS bounced
      FROM {$sentTable} sent
      INNER JOIN {$subscribersTable} s ON s.id = sent.subscriber_id
      LEFT JOIN {$opensTable} counted_opens
        ON counted_opens.subscriber_id = sent.subscriber_id
        AND counted_opens.newsletter_id = sent.newsletter_id
        AND {$countedOpensCondition}
      LEFT JOIN {$opensTable} machine_opens
        ON machine_opens.subscriber_id = sent.subscriber_id
        AND machine_opens.newsletter_id = sent.newsletter_id
        AND machine_opens.user_agent_type = :machineType
      LEFT JOIN {$clicksTable} clicks
        ON clicks.subscriber_id = sent.subscriber_id
        AND clicks.newsletter_id = sent.newsletter_id
        AND (clicks.user_agent_type = :humanType OR clicks.user_agent_type IS NULL)
      LEFT JOIN {$unsubscribesTable} unsubscribes
        ON unsubscribes.subscriber_id = sent.subscriber_id
        AND unsubscribes.newsletter_id = sent.newsletter_id
      LEFT JOIN {$bouncesTable} bounces
        ON bounces.subscriber_id = sent.subscriber_id
        AND bounces.newsletter_id = sent.newsletter_id
      WHERE sent.newsletter_id = :newsletterId
      GROUP BY s.id, s.email, s.first_name, s.last_name, s.status
      ORDER BY s.email ASC";

    $statement = $this->entityManager->getConnection()->executeQuery(
      $sql,
      [
        'newsletterId' => $newsletterId,
        'humanType' => $humanType,
        'machineType' => $machineType,
      ],
      [
        'newsletterId' => ParameterType::INTEGER,
        'humanType' => ParameterType::INTEGER,
        'machineType' => ParameterType::INTEGER,
      ]
    );

    foreach ($statement->iterateAssociative() as $row) {
      /** @var array{
       *   subscriber_id: int|string,
       *   email: string,
       *   first_name: string|null,
       *   last_name: string|null,
       *   status: string|null,
       *   first_open_at: string|null,
       *   open_count: int|string,
       *   machine_open_count: int|string,
       *   click_count: int|string,
       *   unsubscribed: int|string,
       *   bounced: int|string,
       * } $row */
      $openCount = (int)$row['open_count'];
      $machineOpenCount = (int)$row['machine_open_count'];
      $clickCount = (int)$row['click_count'];
      $rows[] = [
        (int)$row['subscriber_id'],
        $row['email'],
        $row['first_name'] ?? '',
        $row['last_name'] ?? '',
        $row['status'] ?? '',
        $openCount > 0 ? __('Yes', 'mailpoet-premium') : __('No', 'mailpoet-premium'),
        $this->formatExportDateTimeCell($row['first_open_at'] ?? null),
        $openCount,
        $machineOpenCount > 0 ? __('Yes', 'mailpoet-premium') : __('No', 'mailpoet-premium'),
        $clickCount > 0 ? __('Yes', 'mailpoet-premium') : __('No', 'mailpoet-premium'),
        $clickCount,
        ((int)$row['bounced']) === 1 ? __('Yes', 'mailpoet-premium') : __('No', 'mailpoet-premium'),
        ((int)$row['unsubscribed']) === 1 ? __('Yes', 'mailpoet-premium') : __('No', 'mailpoet-premium'),
      ];
    }

    return $rows;
  }

  /**
   * @param \DateTimeInterface|string|null $value
   */
  private function formatExportDateTimeCell($value): string {
    if ($value instanceof \DateTimeInterface) {
      return $value->format('Y-m-d H:i:s');
    }
    if ($value === null || $value === '') {
      return '';
    }
    return $value;
  }
}
