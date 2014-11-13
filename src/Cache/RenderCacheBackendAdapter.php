<?php
/**
 * @file
 * Contains \Drupal\render_cache\Cache\RenderCacheBackendAdapter
 */

namespace Drupal\render_cache\Cache;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Render\Element;

use Drupal\render_cache\Cache\RenderStackInterface;
use DrupalCacheInterface;
use RenderCache;

/**
 * Defines the render_cache.cache service.
 *
 * This class is used as an adapter from D8 style render
 * arrays to the Drupal 7 cache API.
 *
 * @ingroup cache
 */
class RenderCacheBackendAdapter implements RenderCacheBackendAdapterInterface {

  /**
   * The injected render stack.
   *
   * @var \Drupal\render_cache\Cache\RenderStackInterface
   */
  protected $renderStack;

  /**
   * Constructs a render cache backend adapter object.
   */
  public function __construct(RenderStackInterface $render_stack) {
    $this->renderStack = $render_stack;
  }

  // @codeCoverageIgnoreStart
  /**
   * {@inheritdoc}
   */
  public function cache($bin = 'cache') {
    // This is an internal API, but we need the cache object.
    return _cache_get_object($bin);
  }
  // @codeCoverageIgnoreEnd

  /**
   * {@inheritdoc}
   */
  public function get(array $cache_info) {
    $id = 42;
    $cache_info_map = array( $id => $cache_info);
    $build = $this->getMultiple($cache_info_map);
    if (!empty($build[$id])) {
      return $build[$id];
    }

    return array();
  }

  /**
   * {@inheritdoc}
   */
  public function getMultiple(array $cache_info_map) {
    $build = array();

    $cid_map_per_bin = array();
    foreach ($cache_info_map as $id => $cache_info) {
      $bin = isset($cache_info['bin']) ? $cache_info['bin'] : 'cache';
      $cid = $this->getCacheId($cache_info);
      $cid_map_per_bin[$bin][$cid] = $id;
    }

    foreach ($cid_map_per_bin as $bin => $cid_map) {
      // Retrieve data from the cache.
      $cids = array_filter(array_keys($cid_map));

      if (!empty($cids)) {
        $cached = $this->cache($bin)->getMultiple($cids);
        foreach ($cached as $cid => $cache) {
          if (!$cache) {
            continue;
          }
          $id = $cid_map[$cid];
          $render = $this->renderStack->convertRenderArrayFromD7($cache->data);
          // @codeCoverageIgnoreStart
          if (!$this->validate($render)) {
            $cache_strategy = $cache_info_map[$id]['render_cache_cache_strategy'];

            // We need to clear the cache for the late rendering strategy, else
            // drupal_render_cache_get() will retrieve the item again from the
            // cache.
            if ($cache_strategy == RenderCache::RENDER_CACHE_STRATEGY_LATE_RENDER) {
              $this->cache($bin)->clear($cid);
            }
            continue;
          }
          // @codeCoverageIgnoreEnd
          $build[$id] = $render;
        }
      }
    }
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function set(array &$render, array $cache_info) {
    $cid = $this->getCacheId($cache_info);

    $bin = isset($elements['#cache']['bin']) ? $elements['#cache']['bin'] : 'cache';
    $expire = isset($elements['#cache']['expire']) ? $elements['#cache']['expire'] : CacheBackendInterface::CACHE_PERMANENT;

    $cache_strategy = $cache_info['render_cache_cache_strategy'];

    // Preserve some properties.
    $properties = $this->preserveProperties($render, $cache_info);

    // Need to first render to markup, else we would need to collect and remove
    // assets twice. This saves a lot performance.
    if ($cache_strategy == RenderCache::RENDER_CACHE_STRATEGY_DIRECT_RENDER) {
      // This internally inc / dec recursion and attaches new out-of-bound assets.
      $markup = $this->renderStack->render($render);
    }

    // This normalizes that all #cache, etc. properties are in the top
    // render element.
    $full_render = array();
    // Ensure that cache_info is processed first.
    $full_render['#cache'] = $cache_info;
    $full_render['render'] = &$render;
    $assets = $this->renderStack->collectAndRemoveAssets($full_render);
    $render += $assets;

    $data = $this->renderStack->convertRenderArrayToD7($render);
    $data['#attached']['render_cache'] += $properties;

    if ($cache_strategy == RenderCache::RENDER_CACHE_STRATEGY_NO_RENDER) {
      $this->cache($bin)->set($cid, $data, $expire);
    }
    elseif ($cache_strategy == RenderCache::RENDER_CACHE_STRATEGY_DIRECT_RENDER) {
      $attached = $this->renderStack->collectAttached($data);

      $data = array();
      $data['#markup'] = &$markup;
      $data['#attached'] = $attached;
      $this->cache($bin)->set($cid, $data, $expire);

      $render = $this->renderStack->convertRenderArrayFromD7($data);
    }
    elseif ($cache_strategy == RenderCache::RENDER_CACHE_STRATEGY_LATE_RENDER) {
      // This cache id was invalidated via cache clear if it was not valid
      // before. This prevents drupal_render_cache_get() from getting an item
      // from the cache.
      $render['#cache']['cid'] = $cid;
      $render['#attached']['render_cache'] = $data['#attached']['render_cache'];
    }
    else {
      // This is actually covered, but not seen by xdebug.
      // @codeCoverageIgnoreStart
      throw new \RunTimeException('Unknown caching strategy passed.');
      // @codeCoverageIgnoreEnd
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setMultiple(array &$build, array $cache_info_map) {
    foreach (Element::children($build) as $id) {
      $this->set($build[$id], $cache_info_map[$id]);
    }
  }

  /**
   * Calculates the Cache ID from the cache keys.
   *
   * @param array $cache_info
   *   The cache info structure.
   *
   * @return string|NULL
   *   The calculated cache id.
   */
  protected function getCacheId(array $cache_info) {
    return $cache_info['cid'];
  }

  // @codeCoverageIgnoreStart
  /**
   * Checks that a cache entry is still valid.
   *
   * @param array $render
   *   The render array to check.
   *
   * @return bool
   *   TRUE when its valid, FALSE otherwise.
   */
  protected function validate(array $render) {
    // @todo implement.
    return TRUE;
  }
  // @codeCoverageIgnoreEnd

  /**
   * Preserves some properties based on the cache info struct.
   *
   * @param array $render
   *   The render array to preserve properties for.
   * @param array $cache_info
   *   The cache information structure.
   *
   * @return array
   *   The preserved properties.
   */
  protected function preserveProperties(array $render, array $cache_info) {
    $properties = array();

    if (empty($cache_info['render_cache_preserve_properties']) || !is_array($cache_info['render_cache_preserve_properties'])) {
      return $properties;
    }

    foreach ($cache_info['render_cache_preserve_properties'] as $key) {
      if (isset($render[$key])) {
        $properties[$key] = $render[$key];
      }
    }

    return $properties;
  }
}
