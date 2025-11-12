<?php

declare(strict_types=1);

namespace Drupal\content_processor_demo\MessageHandler;

use Drupal\content_processor_demo\Message\ProcessContentMessage;
use Drupal\content_processor_demo\Service\ContentStatsService;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

/**
 * Handles ProcessContentMessage messages.
 *
 * This demonstrates Symfony Messenger's advantages:
 * - Automatic dependency injection via constructor.
 * - Type-safe message handling.
 * - Built-in retry logic (configured in services).
 * - Exception handling with recoverable vs unrecoverable errors.
 */
#[AsMessageHandler]
final class ProcessContentMessageHandler {

  /**
   * Constructs a ProcessContentMessageHandler.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ContentStatsService $statsService,
    private readonly LoggerChannelFactoryInterface $loggerFactory,
  ) {}

  /**
   * Processes the content message.
   *
   * @param \Drupal\content_processor_demo\Message\ProcessContentMessage $message
   *   The message to process.
   *
   * @throws \Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException
   *   If the error is unrecoverable.
   */
  public function __invoke(ProcessContentMessage $message): void {
    $logger = $this->loggerFactory->get('content_processor_demo');
    $nodeId = $message->getNodeId();

    try {
      // Load the node.
      $node = $this->entityTypeManager->getStorage('node')->load($nodeId);

      if (!$node) {
        // Node doesn't exist - this is unrecoverable, don't retry.
        throw new UnrecoverableMessageHandlingException(
          sprintf('Node %d not found', $nodeId)
        );
      }

      $logger->info('Processing node @nid with operation @op', [
        '@nid' => $nodeId,
        '@op' => $message->getOperation(),
      ]);

      // Perform the operation.
      match ($message->getOperation()) {
        'analyze' => $this->analyzeContent($node),
        'update_stats' => $this->statsService->updateNodeStats($node),
        'generate_summary' => $this->generateSummary($node),
        default => throw new \InvalidArgumentException(
          'Unknown operation: ' . $message->getOperation()
        ),
      };

      $logger->info('Successfully processed node @nid', ['@nid' => $nodeId]);

    }
    catch (UnrecoverableMessageHandlingException $e) {
      // Re-throw unrecoverable exceptions.
      throw $e;
    }
    catch (\Exception $e) {
      // Log and re-throw for retry.
      $logger->error('Error processing node @nid: @error', [
        '@nid' => $nodeId,
        '@error' => $e->getMessage(),
      ]);
      throw $e;
    }
  }

  /**
   * Analyzes content.
   */
  private function analyzeContent($node): void {
    // Simulate complex analysis.
    $wordCount = str_word_count(strip_tags($node->body->value ?? ''));

    // Store analysis results using Doctrine.
    $this->statsService->storeAnalysis($node->id(), [
      'word_count' => $wordCount,
      'analyzed_at' => time(),
    ]);
  }

  /**
   * Generates a summary.
   */
  private function generateSummary($node): void {
    // Simulate summary generation.
    $text = strip_tags($node->body->value ?? '');
    $summary = substr($text, 0, 200);

    $this->statsService->storeAnalysis($node->id(), [
      'summary' => $summary,
      'summarized_at' => time(),
    ]);
  }

}
