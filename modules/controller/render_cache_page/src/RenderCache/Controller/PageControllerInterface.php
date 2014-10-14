<?php
/**
 * @file
 * Contains \Drupal\render_cache_page\RenderCache\Controller\PageControllerInterface
 */

namespace Drupal\render_cache_page\RenderCache\Controller;

/**
 * Special interface for the render cache page controller.
 */
interface PageControllerInterface {
  /**
   * Implements delegated hook_init().
   */
  public function hook_init();
}
