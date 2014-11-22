<?php
/**
 * @file
 * Contains \Drupal\render_cache\Cache\RenderStackInterface
 */

namespace Drupal\render_cache\Cache;

/**
 * Defines an interface for a render stack.
 *
 * @ingroup cache
 */
interface RenderStackInterface {
  /**
   * Renders the given render array, but preserves properties
   * in the stack.
   *
   * @param array $render
   *   The render array to render.
   *
   * @return string
   *   The rendered render array.
   */
  public function drupalRender(array &$render);

  /**
   * Whether we are in a recursive context.
   *
   * This is useful to determine e.g. if its safe to output a placeholder.
   *
   * @return bool
   *   TRUE if its recursive, FALSE otherwise.
   */
  public function isRecursive();

  /**
   * Returns the current recursion level.
   *
   * @return int
   *   The current recursion level.
   */
  public function getRecursionLevel();

  /**
   * Returns the current recursion storage.
   *
   * @return array
   *   The stored assets.
   */
  public function getRecursionStorage();

  /**
   * Sets the current recursion storage, overwriting everything that is
   * already stored in the current stack frame.
   *
   * @param array $storage
   *   The assets to store in the current stack frame.
   */
  public function setRecursionStorage(array $storage);

  /**
   * Adds assets to the current stack frame and removes them from the
   * render array.
   *
   * @param array $render
   *   The render array to retrieve and remove the assets from.
   * @param bool $collect_attached
   *   Whether or not #attached assets should be collected.
   */
  public function addRecursionStorage(array &$render, $collect_attached = FALSE);

  // Render Cache specific functions.
  // --------------------------------

  /**
   * Converts a render array to be compatible with Drupal 7.
   *
   * This moves Drupal 8 properties into ['#attached']['render_cache'].
   *
   * @param array $render
   *   The render array to convert.
   * @return array
   *   The converted render array.
   */
  public function convertRenderArrayToD7($render);

  /**
   * Converts a render array back to be compatible with Drupal 8.
   *
   * This moves properties from ['#attached']['render_cache'] back to the root.
   *
   * @param array $render
   *   The render array to convert.
   * @return array
   *   The converted render array.
   */
  public function convertRenderArrayFromD7($render);
}
