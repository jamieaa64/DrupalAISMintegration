<?php

declare(strict_types=1);

namespace Drupal\content_processor_demo\Form;

use Drupal\content_processor_demo\Message\ProcessContentMessage;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Form to trigger content processing.
 */
final class ContentProcessorForm extends FormBase {

  /**
   * Constructs a ContentProcessorForm.
   */
  public function __construct(
    private readonly MessageBusInterface $messageBus,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('messenger.default_bus'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'content_processor_demo_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';

    $form['description'] = [
      '#type' => 'markup',
      '#markup' => '<p>' . $this->t('This demo showcases <strong>Symfony Messenger</strong> and <strong>Doctrine DBAL</strong> advantages over Drupal core alternatives.') . '</p>',
    ];

    $form['advantages'] = [
      '#type' => 'details',
      '#title' => $this->t('What makes this better?'),
      '#open' => TRUE,
    ];

    $form['advantages']['messenger'] = [
      '#type' => 'markup',
      '#markup' => '<h3>' . $this->t('Symfony Messenger Advantages:') . '</h3>
        <ul>
          <li>' . $this->t('<strong>Type Safety:</strong> Messages are PHP classes with proper types, not arrays') . '</li>
          <li>' . $this->t('<strong>Auto-wiring:</strong> Handlers get dependencies injected automatically') . '</li>
          <li>' . $this->t('<strong>Middleware:</strong> Built-in retry logic, error handling, and validation') . '</li>
          <li>' . $this->t('<strong>Multiple Transports:</strong> Easy to switch between database, Redis, RabbitMQ, etc') . '</li>
          <li>' . $this->t('<strong>Testability:</strong> Easy to test without database') . '</li>
        </ul>',
    ];

    $form['advantages']['doctrine'] = [
      '#type' => 'markup',
      '#markup' => '<h3>' . $this->t('Doctrine DBAL Advantages:') . '</h3>
        <ul>
          <li>' . $this->t('<strong>Query Builder:</strong> Fluent, chainable interface') . '</li>
          <li>' . $this->t('<strong>Database Agnostic:</strong> Works across MySQL, PostgreSQL, SQLite') . '</li>
          <li>' . $this->t('<strong>Schema Management:</strong> Programmatic table creation') . '</li>
          <li>' . $this->t('<strong>Performance:</strong> Better bulk operations') . '</li>
        </ul>',
    ];

    $form['operation'] = [
      '#type' => 'radios',
      '#title' => $this->t('Operation'),
      '#options' => [
        'analyze' => $this->t('Analyze Content (count words, extract metadata)'),
        'update_stats' => $this->t('Update Statistics (type, status, timestamps)'),
        'generate_summary' => $this->t('Generate Summary (first 200 chars)'),
      ],
      '#default_value' => 'analyze',
      '#required' => TRUE,
    ];

    $form['mode'] = [
      '#type' => 'radios',
      '#title' => $this->t('Processing Mode'),
      '#options' => [
        'single' => $this->t('Process Single Node (immediate demonstration)'),
        'batch' => $this->t('Process All Content (batch with progress tracking)'),
      ],
      '#default_value' => 'single',
      '#required' => TRUE,
    ];

    $form['node_id'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Node to Process'),
      '#target_type' => 'node',
      '#states' => [
        'visible' => [
          ':input[name="mode"]' => ['value' => 'single'],
        ],
        'required' => [
          ':input[name="mode"]' => ['value' => 'single'],
        ],
      ],
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Process Content'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $operation = $form_state->getValue('operation');
    $mode = $form_state->getValue('mode');

    if ($mode === 'single') {
      $nodeId = $form_state->getValue('node_id');

      // Dispatch message to Symfony Messenger.
      // This is type-safe and will be processed asynchronously!
      $message = new ProcessContentMessage(
        nodeId: (int) $nodeId,
        operation: $operation,
      );

      $this->messageBus->dispatch($message);

      $this->messenger()->addStatus(
        $this->t('Message dispatched! Node @nid will be processed asynchronously. Check <a href="@url">the statistics page</a> after a moment.', [
          '@nid' => $nodeId,
          '@url' => '/admin/reports/content-processor-stats',
        ])
      );
    }
    else {
      // Batch mode - dispatch messages for all nodes.
      $query = $this->entityTypeManager->getStorage('node')->getQuery();
      $query->accessCheck(FALSE);
      $nids = $query->execute();

      $count = 0;
      foreach ($nids as $nid) {
        $message = new ProcessContentMessage(
          nodeId: (int) $nid,
          operation: $operation,
        );
        $this->messageBus->dispatch($message);
        $count++;
      }

      $this->messenger()->addStatus(
        $this->t('Dispatched @count messages! Content will be processed asynchronously. Check the <a href="@url">statistics page</a> to see results.', [
          '@count' => $count,
          '@url' => '/admin/reports/content-processor-stats',
        ])
      );
    }
  }

}
