<?php

declare(strict_types=1);

namespace Drupal\content_processor_demo\Service;

use Doctrine\DBAL\Connection;
use Drupal\Core\Entity\EntityInterface;

/**
 * Service for content statistics using Doctrine DBAL.
 *
 * This demonstrates Doctrine DBAL advantages over Drupal Database API:
 * - Query builder with fluent interface.
 * - Database-agnostic queries.
 * - Better type handling.
 * - Transaction support.
 * - More efficient bulk operations.
 */
final class ContentStatsService {

  /**
   * The table name for storing analysis data.
   */
  private const TABLE = 'content_processor_analysis';

  /**
   * Constructs a ContentStatsService.
   */
  public function __construct(
    private readonly Connection $connection,
  ) {}

  /**
   * Ensures the analysis table exists.
   */
  public function ensureTable(): void {
    $schema = $this->connection->createSchemaManager();
    $schemaConfig = $this->connection->getSchemaManager()->introspectSchema();

    if ($schemaConfig->hasTable(self::TABLE)) {
      return;
    }

    // Create table using Doctrine's schema builder.
    $table = $schemaConfig->createTable(self::TABLE);
    $table->addColumn('node_id', 'integer', ['unsigned' => TRUE]);
    $table->addColumn('data', 'text');
    $table->addColumn('created', 'integer', ['unsigned' => TRUE]);
    $table->addColumn('updated', 'integer', ['unsigned' => TRUE]);
    $table->setPrimaryKey(['node_id']);
    $table->addIndex(['updated'], 'idx_updated');

    $schema->createTable($table);
  }

  /**
   * Stores analysis data for a node.
   *
   * This uses Doctrine's query builder for upsert operations.
   * Much cleaner than Drupal's merge() query.
   */
  public function storeAnalysis(int $nodeId, array $data): void {
    $this->ensureTable();

    $now = time();
    $queryBuilder = $this->connection->createQueryBuilder();

    // Check if record exists.
    $exists = $queryBuilder
      ->select('node_id')
      ->from(self::TABLE)
      ->where('node_id = :node_id')
      ->setParameter('node_id', $nodeId)
      ->executeQuery()
      ->fetchOne();

    if ($exists) {
      // Update existing record.
      $this->connection->update(
        self::TABLE,
        [
          'data' => json_encode($data),
          'updated' => $now,
        ],
        ['node_id' => $nodeId]
      );
    }
    else {
      // Insert new record.
      $this->connection->insert(
        self::TABLE,
        [
          'node_id' => $nodeId,
          'data' => json_encode($data),
          'created' => $now,
          'updated' => $now,
        ]
      );
    }
  }

  /**
   * Updates node statistics.
   */
  public function updateNodeStats(EntityInterface $node): void {
    $stats = [
      'title_length' => strlen($node->label()),
      'type' => $node->bundle(),
      'status' => $node->isPublished() ? 'published' : 'unpublished',
      'updated_timestamp' => $node->getChangedTime(),
    ];

    $this->storeAnalysis((int) $node->id(), $stats);
  }

  /**
   * Gets bulk statistics using Doctrine's query builder.
   *
   * This demonstrates complex queries with Doctrine.
   * More readable than Drupal's db_select().
   */
  public function getBulkStats(): array {
    $this->ensureTable();

    $queryBuilder = $this->connection->createQueryBuilder();

    // Complex aggregation query.
    $result = $queryBuilder
      ->select([
        'COUNT(*) as total_analyzed',
        'MAX(updated) as last_update',
        'MIN(created) as first_analysis',
      ])
      ->from(self::TABLE)
      ->executeQuery()
      ->fetchAssociative();

    return $result ?: [];
  }

  /**
   * Gets recent analyses.
   */
  public function getRecentAnalyses(int $limit = 10): array {
    $this->ensureTable();

    $queryBuilder = $this->connection->createQueryBuilder();

    $results = $queryBuilder
      ->select('node_id', 'data', 'updated')
      ->from(self::TABLE)
      ->orderBy('updated', 'DESC')
      ->setMaxResults($limit)
      ->executeQuery()
      ->fetchAllAssociative();

    // Decode JSON data.
    return array_map(function ($row) {
      $row['data'] = json_decode($row['data'], TRUE);
      return $row;
    }, $results);
  }

  /**
   * Bulk deletes old analyses.
   *
   * Demonstrates efficient bulk operations with Doctrine.
   */
  public function deleteOldAnalyses(int $olderThan): int {
    $this->ensureTable();

    $queryBuilder = $this->connection->createQueryBuilder();

    return $queryBuilder
      ->delete(self::TABLE)
      ->where('updated < :threshold')
      ->setParameter('threshold', $olderThan)
      ->executeStatement();
  }

}
