<?php

declare(strict_types=1);

namespace Drupal\content_processor_demo\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;

/**
 * Form to generate test content.
 *
 * This form demonstrates content generation using Drupal's Batch API
 * vs Symfony Messenger approach.
 */
final class ContentGeneratorForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'content_generator_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['description'] = [
      '#type' => 'markup',
      '#markup' => '<p>' . $this->t('Generate test content to demonstrate batch processing.') . '</p>',
    ];

    $form['method'] = [
      '#type' => 'radios',
      '#title' => $this->t('Generation Method'),
      '#options' => [
        'batch' => $this->t('Drupal Batch API (traditional approach)'),
        'messenger' => $this->t('Symfony Messenger (modern async approach)'),
      ],
      '#default_value' => 'batch',
      '#required' => TRUE,
      '#description' => $this->t('Choose how to generate the content. Batch API processes synchronously, while Messenger processes asynchronously.'),
    ];

    $form['count'] = [
      '#type' => 'number',
      '#title' => $this->t('Number of Articles'),
      '#default_value' => 100,
      '#min' => 1,
      '#max' => 10000,
      '#required' => TRUE,
      '#description' => $this->t('Number of test articles to generate. Default is 100 for testing.'),
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Generate Content'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $method = $form_state->getValue('method');
    $count = (int) $form_state->getValue('count');

    if ($method === 'batch') {
      $this->generateWithBatch($count);
    }
    else {
      $this->generateWithMessenger($count);
    }
  }

  /**
   * Generate content using Drupal Batch API.
   *
   * @param int $count
   *   Number of articles to generate.
   */
  private function generateWithBatch(int $count): void {
    $batch = [
      'title' => $this->t('Generating @count articles...', ['@count' => $count]),
      'operations' => [],
      'finished' => [self::class, 'batchFinished'],
      'progress_message' => $this->t('Processed @current out of @total.'),
    ];

    // Create batch operations - process in chunks of 50.
    $chunk_size = 50;
    $chunks = (int) ceil($count / $chunk_size);

    for ($i = 0; $i < $chunks; $i++) {
      $start = $i * $chunk_size;
      $end = min(($i + 1) * $chunk_size, $count);
      $batch['operations'][] = [
        [self::class, 'generateArticlesBatch'],
        [$start, $end],
      ];
    }

    batch_set($batch);
  }

  /**
   * Generate content using Symfony Messenger.
   *
   * @param int $count
   *   Number of articles to generate.
   */
  private function generateWithMessenger(int $count): void {
    // TODO: Implement Symfony Messenger approach.
    // This would dispatch messages to create articles asynchronously.
    $this->messenger()->addWarning(
      $this->t('Symfony Messenger generation not yet implemented. Use Batch API for now.')
    );
  }

  /**
   * Batch operation to generate articles.
   *
   * This is a static method that can be called by the Batch API.
   *
   * @param int $start
   *   Starting index.
   * @param int $end
   *   Ending index.
   * @param array $context
   *   Batch context array.
   */
  public static function generateArticlesBatch(int $start, int $end, array &$context): void {
    if (!isset($context['results']['created'])) {
      $context['results']['created'] = 0;
    }

    for ($i = $start; $i < $end; $i++) {
      try {
        $node = Node::create([
          'type' => 'article',
          'title' => 'Test Article ' . ($i + 1) . ' - ' . date('Y-m-d H:i:s'),
          'field_content' => [
            'value' => self::generateRandomContent(),
            'format' => 'content_format',
          ],
          'status' => 1,
        ]);
        $node->save();
        $context['results']['created']++;

        $context['message'] = t('Created article @num of @total', [
          '@num' => $context['results']['created'],
          '@total' => $end,
        ]);
      }
      catch (\Exception $e) {
        \Drupal::logger('content_processor_demo')->error(
          'Failed to create article: @message',
          ['@message' => $e->getMessage()]
        );
      }
    }
  }

  /**
   * Batch finished callback.
   *
   * @param bool $success
   *   Whether the batch completed successfully.
   * @param array $results
   *   Results array from batch operations.
   * @param array $operations
   *   Remaining operations (if any).
   */
  public static function batchFinished(bool $success, array $results, array $operations): void {
    $messenger = \Drupal::messenger();

    if ($success) {
      $created = $results['created'] ?? 0;
      $messenger->addStatus(t('Successfully created @count articles using Batch API.', [
        '@count' => $created,
      ]));
    }
    else {
      $messenger->addError(t('An error occurred while generating articles.'));
    }
  }

  /**
   * Generate random content for articles.
   *
   * @return string
   *   Random lorem ipsum style content.
   */
  private static function generateRandomContent(): string {
    $paragraphs = [
      'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.',
      'Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.',
      'Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur.',
      'Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.',
      'Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium doloremque laudantium.',
      'Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit.',
      'Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet, consectetur, adipisci velit.',
      'At vero eos et accusamus et iusto odio dignissimos ducimus qui blanditiis praesentium voluptatum.',
    ];

    $num_paragraphs = rand(2, 5);
    $content = [];

    for ($i = 0; $i < $num_paragraphs; $i++) {
      $content[] = '<p>' . $paragraphs[array_rand($paragraphs)] . '</p>';
    }

    return implode("\n\n", $content);
  }

}
