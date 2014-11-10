<?php
/**
 * @file
 * Contains \Drupal\render_cache\Cache\RenderCacheBackendAdapterInterface
 */

namespace Drupal\render_cache\Cache;

/**
 * Defines an interface for the render cache backend adapter.
 *
 * This is an adapter from the render cache, caching implementation
 * to the Drupal 7 cache interface.
 *
 * This implements advanced Drupal 8 features like cache tags using
 * a cache re-validation strategy.
 *
 * An implementation supporting cache tags natively can switch out
 * the service.
 *
 * @ingroup cache
 */
interface RenderCacheBackendAdapterInterface {

  /**
   * Retrieves the Drupal 7 cache object for the given bin.
   *
   * @param string $bin
   *   The cache bin to retrieve a cache object for.
   *
   * @return \DrupalCacheInterface
   *   The Drupal 7 cache object.
   */
  public function cache($bin = 'cache');

  /**
   * Gets a cache entry based on the given cache info.
   *
   * @param array $cache_info
   *   The cache info structure.
   *
   * @return array
   *   The cached render array, which can be empty.
   */
  public function get(array $cache_info);

  /**
   * Gets multiple cache entries based on the cache info map.
   *
   * @param array $cache_info_map
   *   The cache information map, keyed by ID, consisting of cache info structs.
   *
   * @return array
   *   The builded render array, keyed by ID for each cache entry found.
   */
  public function getMultiple(array $cache_info_map);

  /**
   * Sets one cache entry based on the given $cache_info structure.
   *
   * Because cache_info supports different caching strategies, this
   * function needs to be able to change the given render array.
   *
   * @param array &$render
   *   The render array to set the cache for.
   * @param array $cache_info
   *   The cache info structure.
   */
  public function set(array &$render, array $cache_info);

  /**
   * This sets multiple cache entries based on the cache info map.
   *
   * It is expected that $build and $cache_info_map are keyed by the same
   * IDs.
   *
   * @param array &$build
   *   The build of render arrays, keyed by ID.
   * @param array $cache_info_map
   *   The cache information map, keyed by ID, consisting of cache info structs.
   */
  public function setMultiple(array &$build, array $cache_info_map);
}
