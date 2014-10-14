<?php
/**
 * @file
 * Contains \Drupal\render_cache\RenderCache\ValidationStrategy\ValidationStrategyInterface
 */

namespace Drupal\render_cache\RenderCache\ValidationStrategy;

/**
 * Interface for RenderCache ValidationStrategy plugin objects.
 *
 * @ingroup rendercache
 */
interface ValidationStrategyInterface {
  public function validate(array $objects);
  public function generate(array $objects);
}
