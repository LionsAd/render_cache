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
   * @param array $render
   *
   * @return mixed
   */
  public function drupalRender(array &$render);

  /**
   * @return bool
   */
  public function isRecursive();

  /**
   * @return int
   */
  public function getRecursionLevel();

  /**
   * @return array
   *   A Drupal render array.
   */
  public function getRecursionStorage();

  /**
   * @param array $storage
   */
  public function setRecursionStorage(array $storage);

  /**
   * @param array $render
   */
  public function addRecursionStorage(array $render);

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
