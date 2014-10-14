<?php
/**
 * @file
 * Contains \Drupal\render_cache\RenderCache\Controller\ControllerInterface
 */

namespace Drupal\render_cache\RenderCache\Controller;

/**
 * Interface to describe how RenderCache controller plugin objects are implemented.
 *
 * @ingroup rendercache
 */
interface ControllerInterface {

  /**
   * @return array
   */
  public function getContext();

  /**
   * @param array $context
   */
  public function setContext(array $context);

  /**
   * @param array $objects
   *
   * @return array
   */
  public function view(array $objects);

  /**
   * @param object[] $objects
   *
   * @return array
   */
  public function viewPlaceholders(array $objects);

  /**
   * @param array $render
   *
   * @return mixed
   */
  public static function drupalRender(array &$render);

  /**
   * @param array $args
   *
   * @return string
   */
  public static function renderPlaceholders(array $args);

  /**
   * @return bool
   */
  public static function isRecursive();

  /**
   * @return int
   */
  public static function getRecursionLevel();

  /**
   * @return array
   *   A Drupal render array.
   */
  public static function getRecursionStorage();

  /**
   * @param array $storage
   */
  public static function setRecursionStorage(array $storage);

  /**
   * @param array $render
   */
  public static function addRecursionStorage(array $render);
}
