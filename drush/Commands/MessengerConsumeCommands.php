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

    // Try to get receiver via receiver locator
    $receiver = NULL;
    $receiverNames = ['doctrine', 'default', 'async', 'doctrine.default'];

    foreach ($receiverNames as $receiverName) {
      try {
        $receiverLocator = \Drupal::service('messenger.receiver_locator');
        if ($receiverLocator->has($receiverName)) {
          $receiver = $receiverLocator->get($receiverName);
          $this->output()->writeln(sprintf('<comment>Using receiver: %s</comment>', $receiverName));
          break;
        }
      }
      catch (\Exception $e) {
        continue;
      }
    }

    if (!$receiver) {
      $this->output()->writeln('<comment>No message receiver found. Trying database queue...</comment>');
      $this->processViaDatabase($limit);
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
   * Process messages directly from database table.
   */
  private function processViaDatabase(int $limit): void {
    $connection = \Drupal::database();
    $tableNames = ['messenger', 'symfony_messenger', 'messenger_messages'];

    foreach ($tableNames as $tableName) {
      try {
        if (!$connection->schema()->tableExists($tableName)) {
          continue;
        }

        $query = $connection->select($tableName, 'm')
          ->fields('m')
          ->range(0, $limit);
        $results = $query->execute()->fetchAll();

        if (empty($results)) {
          continue;
        }

        $this->output()->writeln(sprintf('<info>Found %d messages in table: %s</info>', count($results), $tableName));

        $processed = 0;
        foreach ($results as $row) {
          try {
            // Unserialize the message body
            $body = unserialize($row->body);

            if (is_object($body)) {
              $this->messageBus->dispatch($body);

              // Delete the processed message
              $connection->delete($tableName)
                ->condition('id', $row->id)
                ->execute();

              $processed++;
              $messageName = get_class($body);
              $this->output()->writeln(sprintf('<info>✓ Processed message %d: %s</info>', $processed, $messageName));
            }
          }
          catch (\Exception $e) {
            $this->output()->writeln(sprintf('<error>✗ Error processing message ID %s: %s</error>', $row->id ?? 'unknown', $e->getMessage()));
          }
        }

        $this->output()->writeln('');
        $this->output()->writeln(sprintf('<info>Processed %d messages from table.</info>', $processed));
        return;
      }
      catch (\Exception $e) {
        continue;
      }
    }

    $this->output()->writeln('<comment>No messages found in database tables.</comment>');
    $this->processViaQueueRunner($limit);
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
