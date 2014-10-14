<?php
/**
 * @file
 * Contains \Drupal\render_cache\RenderCache\Controller\AbstractBaseController
 */

namespace Drupal\render_cache\RenderCache\Controller;

use Drupal\render_cache\Plugin\BasePlugin;

/**
 * Controller abstract base class.
 *
 * @ingroup rendercache
 */
abstract class AbstractBaseController extends BasePlugin implements ControllerInterface {
  // -----------------------------------------------------------------------
  // Suggested implementation functions.

  /**
   * @param array $default_cache_info
   * @param array $context
   *
   * @return bool
   */
  abstract protected function isCacheable(array $default_cache_info, array $context);

  /**
   * Provides the cache info for all objects based on the context.
   *
   * @param array $context
   *
   * @return array
   */
  abstract protected function getDefaultCacheInfo($context);

  /**
   * @param object $object
   * @param array $context
   *
   * @return array
   */
  abstract protected function getCacheContext($object, array $context);

  /**
   * Specific cache info overrides based on the $object.
   *
   * @param object $object
   * @param array $context
   *
   * @return array
   */
  abstract protected function getCacheInfo($object, array $context);

  /**
   * @param object $object
   * @param array $context
   *
   * @return array
   */
  abstract protected function getCacheKeys($object, array $context);

  /**
   * @param object $object
   * @param array $context
   *
   * @return array
   */
  abstract protected function getCacheHash($object, array $context);

  /**
   * @param object $object
   * @param array $context
   *
   * @return array
   */
  abstract protected function getCacheTags($object, array $context);

  /**
   * @param object $object
   * @param array $context
   *
   * @return array
   */
  abstract protected function getCacheValidate($object, array $context);

   /**
   * Render uncached objects.
   *
   * This function needs to be implemented by every child class.
   *
   * @param array $objects
   *   Array of $objects to be rendered keyed by id.
   *
   * @return array
   *   Render array keyed by id.
   */
  abstract protected function render(array $objects);

  /**
   * Renders uncached objects in a recursion compatible way.
   *
   * The default implementation is dumb and expensive performance wise, as
   * it calls the render() method for each object seperately.
   *
   * Controllers that support recursions should implement the
   * RecursionControllerInterface and subclass from
   * BaseRecursionController.
   *
   * @see \Drupal\render_cache\RenderCache\Controller\RecursionControllerInterface
   * @see \Drupal\render_cache\RenderCache\Controller\BaseRecursionController
   *
   * @param object[] $objects
   *   Array of $objects to be rendered keyed by id.
   *
   * @return array[]
   *   Render array keyed by id.
   */
  abstract protected function renderRecursive(array $objects);

  // -----------------------------------------------------------------------
  // Helper functions.

  /**
   * Provides the fully pouplated cache information for a specific object.
   *
   * @param object $object
   * @param array $cache_info
   * @param array $context
   *
   * @return array
   */
  abstract protected function getCacheIdInfo($object, array $cache_info = array(), array $context = array());

  /**
   * Increments the recursion level by 1.
   */
  abstract protected function increaseRecursion();

  /**
   * Decrements the recursion level by 1.
   */
  abstract protected function decreaseRecursion();

  /**
   * @param string $type
   * @param array $data
   * @param mixed|null $context1
   * @param mixed|null $context2
   * @param mixed|null $context3
   */
  abstract protected function alter($type, &$data, &$context1 = NULL, &$context2 = NULL, &$context3 = NULL);
}


