<?php

declare(strict_types=1);

namespace Drupal\content_processor_demo\Controller;

use Drupal\content_processor_demo\Service\ContentStatsService;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for viewing content statistics.
 */
final class StatsController extends ControllerBase {

  /**
   * Constructs a StatsController.
   */
  public function __construct(
    private readonly ContentStatsService $statsService,
    private readonly DateFormatterInterface $dateFormatter,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): StatsController {
    return new self(
      $container->get('content_processor_demo.stats'),
      $container->get('date.formatter'),
    );
  }

  /**
   * Displays statistics.
   */
  public function view(): array {
    $bulkStats = $this->statsService->getBulkStats();
    $recentAnalyses = $this->statsService->getRecentAnalyses(20);

    $build = [];

    $build['intro'] = [
      '#type' => 'markup',
      '#markup' => '<p>' . $this->t('This page displays data stored using <strong>Doctrine DBAL</strong> and processed via <strong>Symfony Messenger</strong>.') . '</p>',
    ];

    // Bulk statistics.
    $build['bulk_stats'] = [
      '#type' => 'details',
      '#title' => $this->t('Overall Statistics'),
      '#open' => TRUE,
    ];

    if (!empty($bulkStats['total_analyzed'])) {
      $build['bulk_stats']['stats'] = [
        '#theme' => 'item_list',
        '#items' => [
          $this->t('Total Analyzed: @count', ['@count' => $bulkStats['total_analyzed']]),
          $this->t('Last Update: @time', [
            '@time' => $this->dateFormatter->format($bulkStats['last_update'], 'short'),
          ]),
          $this->t('First Analysis: @time', [
            '@time' => $this->dateFormatter->format($bulkStats['first_analysis'], 'short'),
          ]),
        ],
      ];
    }
    else {
      $build['bulk_stats']['empty'] = [
        '#markup' => '<p>' . $this->t('No analyses yet. <a href="@url">Process some content</a>!', [
          '@url' => '/admin/config/system/content-processor-demo',
        ]) . '</p>',
      ];
    }

    // Recent analyses table.
    $build['recent'] = [
      '#type' => 'details',
      '#title' => $this->t('Recent Analyses'),
      '#open' => TRUE,
    ];

    if (!empty($recentAnalyses)) {
      $rows = [];
      foreach ($recentAnalyses as $analysis) {
        $data = $analysis['data'];
        $dataDisplay = [];
        foreach ($data as $key => $value) {
          if (!is_array($value)) {
            $dataDisplay[] = "<strong>{$key}:</strong> " . htmlspecialchars((string) $value);
          }
        }

        $rows[] = [
          $analysis['node_id'],
          implode('<br>', $dataDisplay),
          $this->dateFormatter->format($analysis['updated'], 'short'),
        ];
      }

      $build['recent']['table'] = [
        '#theme' => 'table',
        '#header' => [
          $this->t('Node ID'),
          $this->t('Analysis Data'),
          $this->t('Updated'),
        ],
        '#rows' => $rows,
      ];
    }
    else {
      $build['recent']['empty'] = [
        '#markup' => '<p>' . $this->t('No recent analyses.') . '</p>',
      ];
    }

    // Doctrine advantages.
    $build['advantages'] = [
      '#type' => 'details',
      '#title' => $this->t('Why Doctrine DBAL?'),
    ];

    $build['advantages']['content'] = [
      '#markup' => '<p>' . $this->t('This data was queried using Doctrine DBAL\'s query builder:') . '</p>
        <pre><code>$queryBuilder = $this->connection->createQueryBuilder();
$results = $queryBuilder
  ->select(\'node_id\', \'data\', \'updated\')
  ->from(\'content_processor_analysis\')
  ->orderBy(\'updated\', \'DESC\')
  ->setMaxResults(20)
  ->executeQuery()
  ->fetchAllAssociative();</code></pre>
        <p>' . $this->t('Compare this to Drupal\'s db_select() - cleaner, more readable, and database-agnostic!') . '</p>',
    ];

    return $build;
  }

}
