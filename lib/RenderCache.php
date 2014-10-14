<?php
/**
 * @file
 * Contains RenderCache
 */

use Drupal\render_cache\Controller\BaseController;

/**
 * Static Service Container wrapper.
 *
 * Generally, code in Drupal should accept its dependencies via either
 * constructor injection or setter method injection. However, there are cases,
 * particularly in legacy procedural code, where that is infeasible. This
 * class acts as a unified global accessor to arbitrary services within the
 * system in order to ease the transition from procedural code to injected OO
 * code.
 *
 */
class RenderCache {
  /**
   * Overrides drupal_render().
   *
   * If we really need to render early, at least collect the cache tags, etc.
   *
   * @param array $render
   *
   * @return string
   */
  public static function drupalRender(&$render) {
    return BaseController::drupalRender($render);
  }

  /**
   * Returns if we are within a recursive rendering context.
   *
   * This is useful to determine if its safe to output a placeholder, so that
   * #post_render_cache will work.
   *
   * @return bool
   *   TRUE if we are within a recursive context, FALSE otherwise.
   */
  public static function isRecursive() {
    return BaseController::isRecursive();
  }
}
