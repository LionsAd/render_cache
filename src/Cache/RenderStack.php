<?php
/**
 * @file
 * Contains \Drupal\render_cache\Cache\RenderStack
 */

namespace Drupal\render_cache\Cache;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Cache\CacheableInterface;
use Drupal\Core\Render\Element;

use Drupal\render_cache\Cache\Cache;
use RenderCache;

/**
 * Defines the RenderStack service.
 *
 * @ingroup cache
 */
class RenderStack implements RenderStackInterface, CacheableInterface {
  /**
   * {@inheritdoc}
   */
  public function getCacheKeys() {
    return array('render_cache', 'foo');
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return array(array('node' => 1));
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return 600;
  }

  /**
   * {@inheritdoc}
   */
  public function isCacheable() {
    return TRUE;
  }

  // ----------------------------

  /**
   * Recursion level of current call stack.
   *
   * @var int
   */
  protected $recursionLevel = 0;

  /**
   * Recursion storage of current call stack.
   *
   * @var array
   */
  protected $recursionStorage = array();

  /**
   * Increments the recursion level by 1.
   */
  public function increaseRecursion() {
    $this->recursionLevel += 1;
    $this->recursionStorage[$this->recursionLevel] = array();
  }

  /**
   * Decrements the recursion level by 1.
   */
  public function decreaseRecursion() {
    $storage = $this->getRecursionStorage();
    unset($this->recursionStorage[$this->recursionLevel]);
    $this->recursionLevel -= 1;
    return $storage;
  }

  /**
   * {@inheritdoc}
   */
  public function isRecursive() {
    return $this->recursionLevel > 0;
  }

  /**
   * {@inheritdoc}
   */
  public function getRecursionLevel() {
    return $this->recursionLevel;
  }

  /**
   * {@inheritdoc}
   */
  public function getRecursionStorage() {
    if (!isset($this->recursionStorage[$this->recursionLevel])) {
      $this->recursionStorage[$this->recursionLevel] = array();
    }
    $storage = $this->recursionStorage[$this->recursionLevel];
    $render = array();

    // Collect the new storage.
    if (!empty($storage)) {
      $render = $this->collectAndRemoveAssets($storage);
      $render['#attached'] = drupal_render_collect_attached($storage, TRUE);
    }

    return $render;
  }

  // ----------------------------

  /**
   * {@inheritdoc}
   */
  public function setRecursionStorage(array $storage) {
    $this->recursionStorage[$this->recursionLevel] = $storage;
  }

  /**
   * {@inheritdoc}
   */
  public function addRecursionStorage(array &$render) {
    $storage = $this->collectAndRemoveAssets($render);
    $this->recursionStorage[$this->recursionLevel][] = $storage;

    return $storage;
  }

  /**
   * {@inheritdoc}
   */
  public function drupalRender(array &$render) {
    // Store and remove recursive storage.
    $this->addRecursionStorage($render);

    return drupal_render($render);
  }

  /**
   * {@inheritdoc}
   */
  public function collectAttached(array $render) {
    return drupal_render_collect_attached($render, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function render(array &$render) {
    $this->increaseRecursion();
    $markup = drupal_render($render);
    $storage = $this->decreaseRecursion();
    $render['x_render_cache_render_storage'][] = $storage;

    return $markup;
  }

  public function collectAndRemoveAssets(&$element, $recursive = FALSE) {
    $assets = $this->collectAndRemoveD8Properties($element);

    $assets['#cache']['tags'] = isset($assets['#cache']['tags']) ? $assets['#cache']['tags'] : array();
    $assets['#cache']['max-age'] = isset($assets['#cache']['max-age']) ? $assets['#cache']['max-age'] : array();
    $assets['#cache']['downstream-ttl'] = isset($assets['#cache']['downstream-ttl']) ? $assets['#cache']['downstream-ttl'] : array();
    $assets['#post_render_cache'] = isset($assets['#post_render_cache']) ? $assets['#post_render_cache'] : array();

    // Get the children of the element, sorted by weight.
    $children = Element::children($element, TRUE);

    foreach ($children as $key) {
      $new_assets = $this->collectAndRemoveAssets($element[$key], TRUE);
      $assets['#cache']['tags'] = Cache::mergeTags($assets['#cache']['tags'], $new_assets['#cache']['tags']);
      $assets['#cache']['max-age'] = NestedArray::mergeDeep($assets['#cache']['max-age'], $new_assets['#cache']['max-age']);
      $assets['#cache']['downstream-ttl'] = NestedArray::mergeDeep($assets['#cache']['downstream-ttl'], $new_assets['#cache']['downstream-ttl']);
      $assets['#post_render_cache'] = NestedArray::mergeDeep($assets['#post_render_cache'], $new_assets['#post_render_cache']);
    }

    if (!$recursive) {
      // Ensure that there are no empty properties.
      if (empty($assets['#cache']['tags'])) {
        unset($assets['#cache']['tags']);
      }
      if (empty($assets['#cache']['max-age'])) {
        unset($assets['#cache']['max-age']);
      }
      if (empty($assets['#cache']['downstream-ttl'])) {
        unset($assets['#cache']['downstream-ttl']);
      }
      // Ensure the cache property is empty.
      if (empty($assets['#cache'])) {
        unset($assets['#cache']);
      }
      if (empty($assets['#post_render_cache'])) {
        unset($assets['#post_render_cache']);
      }
    }

    return $assets;
  }

  public function collectAndRemoveD8Properties(&$element) {
    $render = array();

    if (!empty($element['#cache']['tags'])) {
      $render['#cache']['tags'] = $element['#cache']['tags'];
      unset($element['#cache']['tags']);
    }
    if (!empty($element['#cache']['max-age'])) {
      $render['#cache']['max-age'] = $element['#cache']['max-age'];
      unset($element['#cache']['max-age']);
    }
    if (!empty($element['#cache']['downstream-ttl'])) {
      $render['#cache']['downstream-ttl'] = $element['#cache']['downstream-ttl'];
      unset($element['#cache']['downstream-ttl']);
    }

    // Ensure the cache property is empty.
    if (empty($element['#cache'])) {
      unset($element['#cache']);
    }

    if (!empty($element['#post_render_cache'])) {
      $render['#post_render_cache'] = $element['#post_render_cache'];
      unset($element['#post_render_cache']);
    }

    return $render;
  }


  /**
   * {@inheritdoc}
   */
  public function convertRenderArrayToD7($render) {
    $render['#attached']['render_cache'] = $this->collectAndRemoveD8Properties($render);

    return $render;
  }

  /**
   * {@inheritdoc}
   */
  public function convertRenderArrayFromD7($render) {
    if (!empty($render['#attached']['render_cache'])) {
      $render += $render['#attached']['render_cache'];
      unset($render['#attached']['render_cache']);
    }

    return $render;
  }

  public function processPostRenderCache(&$render, $cache_info) {
    $strategy = $cache_info['render_cache_cache_strategy'];

    // Only when we have #markup we can post process.
    // @todo Use a #post_render function with a closure instead.
    if ($strategy == RenderCache::RENDER_CACHE_STRATEGY_DIRECT_RENDER
      && !empty($render['#post_render_cache'])) {
      // @todo add back recursive post render cache.
      $this->increaseRecursion();
      // @todo Fix this!
      _drupal_render_process_post_render_cache($render);
      $storage = $this->decreaseRecursion();
      $this->addRecursionStorage($storage);

      unset($render['#attached']);
      unset($render['#cache']);
      unset($render['#post_render_cache']);
    }
  }
}
