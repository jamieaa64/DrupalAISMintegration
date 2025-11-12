<?php

declare(strict_types=1);

namespace Drupal\content_processor_demo\Message;

/**
 * Message to process content asynchronously.
 *
 * This demonstrates Symfony Messenger's type-safe message handling.
 * Unlike Drupal's Queue API which uses arrays, this provides:
 * - Type safety (PHP will error if wrong types are passed).
 * - IDE autocomplete and type checking.
 * - Clear contract of what data the handler receives.
 */
final class ProcessContentMessage {

  /**
   * Constructs a ProcessContentMessage.
   *
   * @param int $nodeId
   *   The node ID to process.
   * @param string $operation
   *   The operation to perform (analyze, update_stats, etc).
   * @param array $options
   *   Additional options for processing.
   */
  public function __construct(
    private readonly int $nodeId,
    private readonly string $operation,
    private readonly array $options = [],
  ) {}

  /**
   * Gets the node ID.
   */
  public function getNodeId(): int {
    return $this->nodeId;
  }

  /**
   * Gets the operation.
   */
  public function getOperation(): string {
    return $this->operation;
  }

  /**
   * Gets the options.
   */
  public function getOptions(): array {
    return $this->options;
  }

}
