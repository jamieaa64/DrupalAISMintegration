<?php

declare(strict_types=1);

namespace Drupal\content_processor_demo\MessageHandler;

use Drupal\content_processor_demo\Message\GeneratePageMessage;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\node\Entity\Node;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handles GeneratePageMessage messages.
 *
 * This demonstrates Symfony Messenger for async page generation:
 * - Type-safe message handling.
 * - Automatic dependency injection.
 * - Background processing via message queue.
 * - Better scalability than synchronous batch operations.
 */
#[AsMessageHandler]
final class GeneratePageMessageHandler {

  /**
   * Constructs a GeneratePageMessageHandler.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly LoggerChannelFactoryInterface $loggerFactory,
  ) {}

  /**
   * Processes the generate page message.
   *
   * @param \Drupal\content_processor_demo\Message\GeneratePageMessage $message
   *   The message to process.
   */
  public function __invoke(GeneratePageMessage $message): void {
    $logger = $this->loggerFactory->get('content_processor_demo');
    $pageNumber = $message->getPageNumber();

    try {
      $node = Node::create([
        'type' => 'page',
        'title' => 'Test Page ' . $pageNumber . ' - ' . date('Y-m-d H:i:s'),
        'field_content' => [
          'value' => $this->generateRandomContent(),
          'format' => 'content_format',
        ],
        'status' => 1,
      ]);

      $node->save();

      $logger->info('Generated page @num (node @nid) via Symfony Messenger', [
        '@num' => $pageNumber,
        '@nid' => $node->id(),
      ]);
    }
    catch (\Exception $e) {
      $logger->error('Failed to generate page @num: @error', [
        '@num' => $pageNumber,
        '@error' => $e->getMessage(),
      ]);
      throw $e;
    }
  }

  /**
   * Generate random content for pages.
   *
   * @return string
   *   Random lorem ipsum style content.
   */
  private function generateRandomContent(): string {
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
