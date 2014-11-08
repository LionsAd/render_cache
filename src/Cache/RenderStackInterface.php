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
}
