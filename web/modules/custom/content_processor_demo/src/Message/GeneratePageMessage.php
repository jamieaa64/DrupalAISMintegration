<?php

declare(strict_types=1);

namespace Drupal\content_processor_demo\Message;

/**
 * Message to generate a page asynchronously.
 *
 * This demonstrates Symfony Messenger's type-safe message handling
 * for content generation tasks.
 */
final class GeneratePageMessage {

  /**
   * Constructs a GeneratePageMessage.
   *
   * @param int $pageNumber
   *   The page number/index (for unique titles).
   * @param array $options
   *   Additional options for page generation.
   */
  public function __construct(
    private readonly int $pageNumber,
    private readonly array $options = [],
  ) {}

  /**
   * Gets the page number.
   */
  public function getPageNumber(): int {
    return $this->pageNumber;
  }

  /**
   * Gets the options.
   */
  public function getOptions(): array {
    return $this->options;
  }

}
