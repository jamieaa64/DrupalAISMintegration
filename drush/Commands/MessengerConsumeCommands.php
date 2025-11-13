<?php

declare(strict_types=1);

namespace Drush\Commands;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;

/**
 * Drush commands for consuming Symfony Messenger messages.
 */
final class MessengerConsumeCommands extends DrushCommands {

  /**
   * Constructs a MessengerConsumeCommands object.
   */
  public function __construct(
    private readonly MessageBusInterface $messageBus,
    private readonly LoggerChannelFactoryInterface $loggerFactory,
  ) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('messenger.default_bus'),
      $container->get('logger.factory'),
    );
  }

  /**
   * Consume messages from Symfony Messenger queue.
   *
   * @param array $options
   *   Command options.
   */
  #[CLI\Command(name: 'messenger:consume', aliases: ['mc'])]
  #[CLI\Option(name: 'limit', description: 'Limit the number of messages to process')]
  #[CLI\Option(name: 'time-limit', description: 'Time limit in seconds')]
  #[CLI\Usage(name: 'drush messenger:consume', description: 'Process all queued messages')]
  #[CLI\Usage(name: 'drush messenger:consume --limit=10', description: 'Process up to 10 messages')]
  #[CLI\Usage(name: 'drush messenger:consume --time-limit=60', description: 'Process messages for 60 seconds')]
  public function consume(array $options = ['limit' => NULL, 'time-limit' => NULL]): void {
    $logger = $this->loggerFactory->get('messenger_consume');
    $limit = $options['limit'] ? (int) $options['limit'] : PHP_INT_MAX;
    $timeLimit = $options['time-limit'] ? (int) $options['time-limit'] : NULL;
    $startTime = time();

    $this->output()->writeln('<info>Starting message consumer...</info>');
    $this->output()->writeln(sprintf('<comment>Limit: %s messages</comment>', $limit === PHP_INT_MAX ? 'unlimited' : $limit));
    if ($timeLimit) {
      $this->output()->writeln(sprintf('<comment>Time limit: %s seconds</comment>', $timeLimit));
    }
    $this->output()->writeln('');

    // Get the message receiver from container
    try {
      $receiver = \Drupal::service('messenger.receiver.doctrine');
    }
    catch (\Exception $e) {
      $this->output()->writeln('<error>Failed to get message receiver: ' . $e->getMessage() . '</error>');
      $this->output()->writeln('<info>Trying to process via queue runner...</info>');
      $this->processViaQueueRunner($limit);
      return;
    }

    $processed = 0;

    while ($processed < $limit) {
      // Check time limit
      if ($timeLimit && (time() - $startTime) >= $timeLimit) {
        $this->output()->writeln('<comment>Time limit reached.</comment>');
        break;
      }

      // Get messages from transport
      $envelopes = $receiver->get();

      if (empty($envelopes)) {
        $this->output()->writeln('<info>No more messages in queue.</info>');
        break;
      }

      foreach ($envelopes as $envelope) {
        try {
          // Dispatch the message
          $this->messageBus->dispatch($envelope->getMessage());

          // Acknowledge the message
          $receiver->ack($envelope);

          $processed++;
          $messageName = get_class($envelope->getMessage());
          $this->output()->writeln(sprintf('<info>✓ Processed message %d: %s</info>', $processed, $messageName));

          $logger->info('Processed message: @message', ['@message' => $messageName]);

          if ($processed >= $limit) {
            break 2;
          }

        }
        catch (\Exception $e) {
          $this->output()->writeln(sprintf('<error>✗ Error processing message: %s</error>', $e->getMessage()));
          $logger->error('Error processing message: @error', ['@error' => $e->getMessage()]);

          // Reject the message
          $receiver->reject($envelope);
        }
      }
    }

    $this->output()->writeln('');
    $this->output()->writeln(sprintf('<info>Finished. Processed %d message(s).</info>', $processed));
  }

  /**
   * Process messages via Drupal's queue runner as fallback.
   */
  private function processViaQueueRunner(int $limit): void {
    $queueFactory = \Drupal::service('queue');
    $queueNames = ['messenger_messages', 'symfony_messenger', 'default'];

    foreach ($queueNames as $queueName) {
      try {
        $queue = $queueFactory->get($queueName);
        $count = $queue->numberOfItems();

        if ($count > 0) {
          $this->output()->writeln(sprintf('<info>Found %d items in queue: %s</info>', $count, $queueName));

          $processed = 0;
          while ($item = $queue->claimItem()) {
            try {
              // Process the queue item
              if (isset($item->data) && is_object($item->data)) {
                $this->messageBus->dispatch($item->data);
                $queue->deleteItem($item);
                $processed++;
                $this->output()->writeln(sprintf('<info>✓ Processed item %d</info>', $processed));

                if ($processed >= $limit) {
                  break;
                }
              }
            }
            catch (\Exception $e) {
              $this->output()->writeln(sprintf('<error>✗ Error: %s</error>', $e->getMessage()));
              $queue->releaseItem($item);
            }
          }

          $this->output()->writeln(sprintf('<info>Processed %d items from queue.</info>', $processed));
          return;
        }
      }
      catch (\Exception $e) {
        continue;
      }
    }

    $this->output()->writeln('<comment>No messages found in any queue.</comment>');
  }

}
