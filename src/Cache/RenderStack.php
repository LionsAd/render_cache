<?php
/**
 * @file
 * Contains \Drupal\render_cache\Cache\RenderStack
 */

namespace Drupal\render_cache\Cache;

use Drupal\Core\Cache\CacheableInterface;
use SplStack;

/**
 * Defines the RenderStack service.
 *
 * @ingroup cache
 */
class RenderStack extends SplStack implements CacheableInterface {
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
    $this->recursionLevel -= 1;
    return $storage;
  }

  /**
   * {@inheritdoc}
   */
  public function isRecursive() {
    return $this->count() > 0;
  }

  /**
   * {@inheritdoc}
   */
  public function getRecursionLevel() {
    return $this->count();
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

    // pseudo-collect the new storage.
    if (!empty($storage)) {
      $render['#cache']['tags'] = drupal_render_collect_cache_tags($storage);
      ksort($render['#cache']['tags']);

      $post_render_cache = drupal_render_collect_post_render_cache($storage);
      $render['#post_render_cache'] = $post_render_cache;
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
  public function addRecursionStorage(array $render) {
    $storage = $this->getCleanStorage($render);
    $this->recursionStorage[$this->recursionLevel][] = $storage;
  }

  /**
   * {@inheritdoc}
   */
  public function drupalRender(array &$render) {
    $this->cacheRenderArray($render);

    // Merge back previously saved properties.
    if (!empty($render['#attached']['render_cache'])) {
      $render += $render['#attached']['render_cache'];
      unset($render['#attached']['render_cache']);
    }

    // Store recursive storage.
    $this->addRecursionStorage($render);

    return drupal_render($render);
  }

  /**
   * @param array $render
   * @param bool $remove_render_cache
   *
   * @return array
   */
  public function getCleanStorage($render, $remove_render_cache = TRUE) {
    // Ensure all properties are set.
    $render += array(
      '#cache' => array(),
      '#attached' => array(),
      '#post_render_cache' => array(),
    );
    $render['#cache'] += array(
      'tags' => array()
    );

    // Store only relevant parts.
    $storage = array(
      '#attached' => $render['#attached'],
      '#cache' => array(
        'tags' => $render['#cache']['tags']
      ),
      '#post_render_cache' => $render['#post_render_cache'],
    );
    if ($remove_render_cache) {
      // Remove render cache properties.
      unset($storage['#attached']['render_cache']);
    }

    return $storage;
  }

  /**
   * {@inheritdoc}
   */
  public function cacheRenderArray($render, $cache_info = array()) {
    // Process markup with drupal_render() caching.

    // Tags are special so collect them first to add them in again.
    if (isset($render['#cache']['tags'])) {
      $render['x_render_cache_collected_tags']['#cache']['tags'] = $render['#cache']['tags'];
      unset($render['#cache']['tags']);
    }
    if (!isset($render['#cache'])) {
      $render['#cache'] = array();
    }

    $render['#cache'] = drupal_array_merge_deep($render['#cache'], $cache_info);
    $render['#cache']['tags'] = drupal_render_collect_cache_tags($render);
    ksort($render['#cache']['tags']);

    $post_render_cache = drupal_render_collect_post_render_cache($render);
    $render['#post_render_cache'] = $post_render_cache;

    $render_cache_attached = array();
    // Preserve some properties in #attached?
    if (!empty($cache_info['render_cache_render_to_markup']['preserve properties']) &&
        is_array($cache_info['render_cache_render_to_markup']['preserve properties'])) {
      foreach ($cache_info['render_cache_render_to_markup']['preserve properties'] as $key) {
        if (isset($render[$key])) {
          $render_cache_attached[$key] = $render[$key];
        }
      }
    }

    // Store data in #attached for Drupal 7.
    $render_cache_attached['#cache']['tags'] = $render['#cache']['tags'];
    $render_cache_attached['#post_render_cache'] = $render['#post_render_cache'];

    $render['#attached'] = drupal_render_collect_attached($render, TRUE);
    $render['#attached']['render_cache'] = $render_cache_attached;

    return $render;
  }

  /**
   * {@inheritdoc}
   */
  public function convertRenderArrayToD7($render) {
    if (!empty($render['#cache']['tags'])) {
      $render['#attached']['render_cache']['#cache']['tags'] = $render['#cache']['tags'];
      unset($render['#cache']['tags']);
    }
    if (!empty($render['#cache']['max-age'])) {
      $render['#attached']['render_cache']['#cache']['max-age'] = $render['#cache']['max-age'];
      unset($render['#cache']['max-age']);
    }
    // Ensure the cache property is empty.
    if (empty($render['#cache'])) {
      unset($render['#cache']);
    }

    if (!empty($render['#post_render_cache'])) {
      $render['#attached']['render_cache']['#post_render_cache'] = $render['#post_render_cache'];
      unset($render['#post_render_cache']);
    }

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
}
