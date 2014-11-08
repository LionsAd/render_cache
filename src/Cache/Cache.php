<?php

/**
 * @file
 * Contains \Drupal\render_cache\Cache\Cache.
 */

namespace Drupal\render_cache\Cache;

use Drupal\Core\Cache\Cache as D8Cache;
use SelectQueryInterface;

/**
 * Helper methods for cache.
 *
 * @ingroup cache
 */
class Cache extends D8Cache {
  /**
   * Generates a hash from a query object, to be used as part of the cache key.
   *
   * This smart caching strategy saves Drupal from querying and rendering to
   * HTML when the underlying query is unchanged.
   *
   * Expensive queries should use the query builder to create the query and then
   * call this function. Executing the query and formatting results should
   * happen in a #pre_render callback.
   *
   * Note: This function was overridden to provide the D7 version of
   *       SelectQueryInterface.
   *
   * @param \SelectQueryInterface
   *   A select query object.
   *
   * @return string
   *   A hash of the query arguments.
   */
  public static function keyFromQuery(SelectQueryInterface $query) {
    $query->preExecute();
    $keys = array((string) $query, $query->getArguments());
    return hash('sha256', serialize($keys));
  }
}
