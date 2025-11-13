<?php

declare(strict_types=1);

namespace Drush\Commands;

use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for debugging Symfony Messenger.
 */
final class MessengerDebugCommands extends DrushCommands {

  /**
   * Debug messenger configuration and check for queued messages.
   */
  #[CLI\Command(name: 'messenger:debug', aliases: ['md'])]
  #[CLI\Usage(name: 'drush messenger:debug', description: 'Show messenger configuration and message counts')]
  public function debug(): void {
    $this->output()->writeln('<info>=== Symfony Messenger Debug ===</info>');
    $this->output()->writeln('');

    // Check receiver locator
    try {
      $receiverLocator = \Drupal::service('messenger.receiver_locator');
      $this->output()->writeln('<info>✓ Receiver locator service found</info>');

      // Try to get available receivers
      $receiverNames = ['doctrine', 'default', 'async', 'doctrine.default', 'sync'];
      foreach ($receiverNames as $name) {
        if ($receiverLocator->has($name)) {
          $this->output()->writeln(sprintf('  - Found receiver: <comment>%s</comment>', $name));
        }
      }
    }
    catch (\Exception $e) {
      $this->output()->writeln('<error>✗ Receiver locator not found: ' . $e->getMessage() . '</error>');
    }

    $this->output()->writeln('');

    // Check database tables
    $this->output()->writeln('<info>Checking database tables...</info>');
    $connection = \Drupal::database();
    $tables = $connection->schema()->findTables('%messenger%');

    if (empty($tables)) {
      $this->output()->writeln('<comment>No messenger tables found</comment>');
    }
    else {
      foreach ($tables as $table) {
        try {
          $count = $connection->select($table, 't')
            ->countQuery()
            ->execute()
            ->fetchField();
          $this->output()->writeln(sprintf('  - Table <comment>%s</comment>: %d messages', $table, $count));
        }
        catch (\Exception $e) {
          $this->output()->writeln(sprintf('  - Table <comment>%s</comment>: Error reading', $table));
        }
      }
    }

    $this->output()->writeln('');

    // Check queues
    $this->output()->writeln('<info>Checking Drupal queues...</info>');
    $queueFactory = \Drupal::service('queue');
    $queueNames = ['messenger', 'messenger_messages', 'symfony_messenger', 'default'];

    foreach ($queueNames as $queueName) {
      try {
        $queue = $queueFactory->get($queueName);
        $count = $queue->numberOfItems();
        if ($count > 0) {
          $this->output()->writeln(sprintf('  - Queue <comment>%s</comment>: %d items', $queueName, $count));
        }
      }
      catch (\Exception $e) {
        // Queue doesn't exist, skip
      }
    }

    $this->output()->writeln('');

    // Check configuration
    $this->output()->writeln('<info>Checking sm configuration...</info>');
    try {
      $config = \Drupal::config('sm.settings');
      $transports = $config->get('transports');
      $routing = $config->get('routing');

      if ($transports) {
        $this->output()->writeln('<comment>Transports:</comment>');
        foreach ($transports as $name => $transport) {
          $dsn = $transport['dsn'] ?? 'unknown';
          $this->output()->writeln(sprintf('  - %s: %s', $name, $dsn));
        }
      }
      else {
        $this->output()->writeln('<comment>No transports configured</comment>');
      }

      $this->output()->writeln('');

      if ($routing) {
        $this->output()->writeln('<comment>Message Routing:</comment>');
        foreach ($routing as $messageClass => $transport) {
          $shortClass = substr($messageClass, strrpos($messageClass, '\\') + 1);
          $this->output()->writeln(sprintf('  - %s → %s', $shortClass, $transport));
        }
      }
      else {
        $this->output()->writeln('<comment>No routing configured</comment>');
      }
    }
    catch (\Exception $e) {
      $this->output()->writeln('<error>Could not read sm.settings: ' . $e->getMessage() . '</error>');
    }

    $this->output()->writeln('');
    $this->output()->writeln('<info>=== End Debug ===</info>');
  }

}
