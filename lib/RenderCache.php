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
}
