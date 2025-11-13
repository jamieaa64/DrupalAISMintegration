# Content Processor Demo

A demonstration module showcasing **Symfony Messenger** and **Doctrine DBAL** advantages over Drupal core alternatives.

## What This Demonstrates

### Symfony Messenger Advantages

1. **Type Safety**: Messages are PHP classes with proper type hints, not arrays
2. **Auto-wiring**: Handlers get dependencies injected automatically
3. **Middleware**: Built-in retry logic, error handling, and validation
4. **Multiple Transports**: Easy to switch between database, Redis, RabbitMQ, etc.
5. **Testability**: Easy to test message handlers without database

**Compare to Drupal Queue API:**
```php
// Drupal Queue API (array-based, no type safety)
$queue = \Drupal::queue('my_queue');
$queue->createItem(['node_id' => 123, 'operation' => 'analyze']);

// Symfony Messenger (type-safe, clean)
$message = new ProcessContentMessage(
  nodeId: 123,
  operation: 'analyze'
);
$messageBus->dispatch($message);
```

### Doctrine DBAL Advantages

1. **Query Builder**: Fluent, chainable interface
2. **Database Agnostic**: Works across MySQL, PostgreSQL, SQLite
3. **Schema Management**: Programmatic table creation
4. **Better Type Handling**: Proper type conversions
5. **Performance**: More efficient bulk operations

**Compare to Drupal Database API:**
```php
// Drupal Database API
$query = \Drupal::database()->select('my_table', 't')
  ->fields('t', ['node_id', 'data'])
  ->orderBy('updated', 'DESC')
  ->range(0, 10);
$results = $query->execute()->fetchAll();

// Doctrine DBAL (more readable, portable)
$results = $queryBuilder
  ->select('node_id', 'data')
  ->from('my_table')
  ->orderBy('updated', 'DESC')
  ->setMaxResults(10)
  ->executeQuery()
  ->fetchAllAssociative();
```

## Features

- **Async Content Processing**: Process nodes asynchronously using Symfony Messenger
- **Multiple Operations**: Analyze content, update statistics, generate summaries
- **Batch Processing**: Process all content with progress tracking
- **Statistics Dashboard**: View processing results stored in Doctrine-managed tables
- **Error Handling**: Automatic retry logic for transient failures

## Usage

1. Enable the module
2. Visit `/admin/config/system/content-processor-demo`
3. Choose an operation and processing mode
4. View results at `/admin/reports/content-processor-stats`

## Architecture

### Message Flow

1. User submits form
2. `ProcessContentMessage` created and dispatched to message bus
3. Message stored in database via Doctrine transport
4. Message worker picks up message
5. `ProcessContentMessageHandler` processes message
6. Results stored via `ContentStatsService` using Doctrine DBAL

### Components

- `ProcessContentMessage`: Type-safe message class
- `ProcessContentMessageHandler`: Async message processor
- `ContentStatsService`: Doctrine DBAL service for data operations
- `ContentProcessorForm`: Admin form to trigger processing
- `StatsController`: Display processing results

## Requirements

- drupal/sm - Symfony Messenger integration
- drupal/sm_transport_doctrine - Doctrine transport for messages
- drupal/dbal - Doctrine DBAL connection
- drupal/batch_messenger - Batch processing support

## Processing Messages

Messages are processed by the Symfony Messenger worker. In production, you would run:

```bash
# Process messages continuously
drush sm:messenger:consume

# Or via cron
drush sm:messenger:consume --time-limit=60
```

For development/testing, messages can be processed synchronously by configuring the transport.
