<?php
/**
 * @file
 * Contains \Drupal\render_cache\RenderCache\RenderStrategy\RenderStrategyInterface
 */

namespace Drupal\render_cache\RenderCache\RenderStrategy;

/**
 * Interface to describe how RenderCache renderer plugin objects are implemented.
 *
 * @ingroup rendercache
 */
interface RenderStrategyInterface {
  public function render(array $placeholders);
}
