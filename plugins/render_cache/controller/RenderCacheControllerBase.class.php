<?php

define('RENDER_CACHE_STRATEGY_NO_RENDER', 0);
define('RENDER_CACHE_STRATEGY_DIRECT_RENDER', 1);
define('RENDER_CACHE_STRATEGY_LATE_RENDER', 2);

/**
 * Interface to describe how RenderCache controller plugin objects are implemented.
 */
interface RenderCacheControllerInterface {
  public function getContext();
  public function setContext(array $context);

  public function view(array $objects);

  public static function drupalRender(array &$render);

  public static function isRecursive();
  public static function getRecursionLevel();
  public static function getRecursionStorage();
  public static function setRecursionStorage(array $storage);
  public static function addRecursionStorage(array $render);
}

/**
 * RenderCacheController abstract base class.
 */
abstract class RenderCacheControllerAbstractBase extends RenderCachePluginBase implements RenderCacheControllerInterface {
  // -----------------------------------------------------------------------
  // Suggested implementation functions.

  abstract protected function isCacheable(array $default_cache_info, array $context);

  /**
   * Provides the cache info for all objects based on the context.
   */
  abstract protected function getDefaultCacheInfo($context);

  abstract protected function getCacheContext($object, array $context);

  /**
   * Specific cache info overrides based on the $object.
   */
  abstract protected function getCacheInfo($object, array $context);
  abstract protected function getCacheKeys($object, array $context);
  abstract protected function getCacheHash($object, array $context);
  abstract protected function getCacheTags($object, array $context);
  abstract protected function getCacheValidate($object, array $context);

   /**
   * Render uncached objects.
   *
   * This function needs to be implemented by every child class.
   *
   * @param $objects
   *   Array of $objects to be rendered keyed by id.
   *
   * @return array
   *   Render array keyed by id.
   */
  abstract protected function render(array $objects);

  /**
   * Render uncached objects in a recursion compatible way.
   *
   * The default implementation is dumb and expensive performance wise, as
   * it calls the render() method for each object seperately.
   *
   * Controllers that support recursions should implement the
   * RenderCacheControllerRecursionInterface and subclass from
   * RenderCacheControllerRecursionBase.
   *
   * @see RenderCacheControllerRecursionInterface
   * @see RenderCacheControllerRecursionBase
   *
   * @param $objects
   *   Array of $objects to be rendered keyed by id.
   *
   * @return array
   *   Render array keyed by id.
   */
  abstract protected function renderRecursive(array $objects);

  // -----------------------------------------------------------------------
  // Helper functions.

  /**
   * Provides the fully pouplated cache information for a specific object.
   */
  abstract protected function getCacheIdInfo($object, array $cache_info = array(), array $context = array());

  abstract protected function increaseRecursion();
  abstract protected function decreaseRecursion();

  abstract protected function alter($type, &$data, &$context1 = NULL, &$context2 = NULL, &$context3 = NULL);
}

/**
 * Base class for RenderCacheController plugin objects.
 */
abstract class RenderCacheControllerBase extends RenderCacheControllerAbstractBase {
  /**
   * An optional context provided by this controller.
   */
  protected $context = array();

  /**
   * Recursion level of current call stack.
   */
  protected static $recursionLevel = 0;

  /**
   * Recursion storage of current call stack.
   */
  protected static $recursionStorage = array();

  /**
   * {@inheritdoc}
   */
  public function getContext() {
    return $this->context;
  }

  /**
   * {@inheritdoc}
   */
  public function setContext(array $context) {
    $this->context = $context;
  }

  /**
   * {@inheritdoc}
   */
  public function view(array $objects) {
    // Retrieve controller context.
    $context = $this->getContext();

    // Get default cache info and allow modules to alter it.
    $default_cache_info = $this->getDefaultCacheInfo($context);
    $this->alter('default_cache_info', $default_cache_info, $context);

    $cid_map = array();
    $cache_info_map = array();
    $object_order = array_keys($objects);

    // Determine if this is cacheable.
    $is_cacheable = $this->isCacheable($default_cache_info, $context);

    // Retrieve a list of cache_info structures.
    foreach ($objects as $id => $object) {
      $context['id'] = $id;
      $cache_info_map[$id] = $this->getCacheIdInfo($object, $default_cache_info, $context);

      // If it is not cacheable, set the 'cid' to NULL.
      if (!$is_cacheable) {
        $cache_info_map[$id]['cid'] = NULL;
      }
      $cid_map[$id] = $cache_info_map[$id]['cid'];
    }

    // Retrieve data from the cache.
    $cids = array_filter(array_values($cid_map));

    if (!empty($cids)) {
      $cached_build = $this->getCache($cids, $default_cache_info);

      // Calculate remaining entities
      foreach ($object_order as $id) {
        $cid = $cid_map[$id];
        if ($cid && isset($cached_build[$cid])) {
          unset($objects[$id]);
        }
      }
   }

    // Render non-cached entities.
    if (!empty($objects)) {
      $object_build = $this->renderRecursive($objects);
    }

    $build = array();
    foreach ($object_order as $id) {
      $cid = $cid_map[$id];
      $cache_info = $cache_info_map[$id];
      $strategy = $this->determineCachingStrategy($cache_info, $id);

      if (isset($cached_build[$cid])) {
        // This has been already processed by the processCacheEntry() function.
        $render = $cached_build[$cid];
      } else {
        // If the object is not set, there is nothing we can do here.
        if (!isset($object_build[$id])) {
          continue;
        }
        $render = $object_build[$id];
        $render = static::cacheRenderArray($render, $cache_info);

        // If this should not be cached, unset the cache info properties.
        if (!$cid) {
          unset($render['#cache']['cid']);
          unset($render['#cache']['keys']);
        }

        $this->setCache($render, $cache_info, $strategy);
      }

      // Only when we have #markup we can post process.
      if ($strategy == RENDER_CACHE_STRATEGY_DIRECT_RENDER && !static::isRecursive()) {
        $storage = $render;
        while (!empty($storage['#post_render_cache'])) {
          $this->increaseRecursion();
          _drupal_render_process_post_render_cache($render);
          $storage = $this->decreaseRecursion();
          static::addRecursionStorage($storage);
        }
      }

      // Store recursive storage.
      static::addRecursionStorage($render);

      // Unset any remaining weight properties.
      unset($render['#weight']);

      if ($strategy == RENDER_CACHE_STRATEGY_DIRECT_RENDER) {
        unset($render['#attached']);
        unset($render['#cache']);
        unset($render['#post_render_cache']);
      }

      $build[$id] = $render;
    }

    // If this is the main entry point.
    if (!static::isRecursive() && variable_get('render_cache_send_drupal_cache_tags', TRUE)) {
      $storage = static::getRecursionStorage();
      $header = static::convertCacheTagsToHeader($storage['#cache']['tags']);
      // @todo ensure render_cache is the top module.
      // Currently this header can be send multiple times.
      drupal_add_http_header('X-Drupal-Cache-Tags', $header, TRUE);
    }

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public static function isRecursive() {
    return static::$recursionLevel > 0;
  }

  /**
   * {@inheritdoc}
   */
  public static function getRecursionLevel() {
    return static::$recursionLevel;
  }

  /**
   * {@inheritdoc}
   */
  public static function getRecursionStorage() {
    $storage = static::$recursionStorage[static::$recursionLevel];
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

  /**
   * {@inheritdoc}
   */
  public static function setRecursionStorage(array $storage) {
    static::$recursionStorage[static::$recursionLevel] = $storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function addRecursionStorage(array $render) {
    $storage = static::getCleanStorage($render);
    static::$recursionStorage[static::$recursionLevel][] = $storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function drupalRender(array &$render) {
    $cache_info = array();
    static::cacheRenderArray($render, $cache_info);

    // Merge back previously saved properties.
    if (!empty($render['#attached']['render_cache'])) {
      $render += $render['#attached']['render_cache'];
      unset($render['#attached']['render_cache']);
    }

    // Store recursive storage.
    static::addRecursionStorage($render);

    return drupal_render($render);
  }

  /**
   * {@inheritdoc}
   */
  protected function isCacheable(array $default_cache_info, array $context) {
    $ignore_request_method_check = $default_cache_info['render_cache_ignore_request_method_check'];
    return isset($default_cache_info['granularity'])
        && variable_get('render_cache_enabled', TRUE)
        && variable_get('render_cache_' . $this->getType() . '_enabled', TRUE)
        && render_cache_call_is_cacheable(NULL, $ignore_request_method_check);
  }

  /**
   * {@inheritdoc}
   */
  protected function getCacheContext($object, array $context) {
    return $context;
  }

  /**
   * {@inheritdoc}
   */
  protected function getCacheInfo($object, array $context) {
    return array();
  }

  /**
   * {@inheritdoc}
   */
  protected function getCacheKeys($object, array $context) {
    return array(
      'render_cache',
      $this->getType(),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getCacheHash($object, array $context) {
    return array(
      'id' => $context['id'],
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getCacheTags($object, array $context) {
    return array(
      'rendered' => TRUE,
      $this->getType() . '_view' => TRUE,
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getCacheValidate($object, array $context) {
    return array();
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultCacheInfo($context) {
    return array(
       // Drupal 7 properties.
       'bin' => 'cache_render',
       'expire' => CACHE_PERMANENT,
       // Use per role to support contextual and its safer anyway.
       'granularity' => DRUPAL_CACHE_PER_ROLE,
       'keys' => array(),

       // Drupal 8 properties.
       'tags' => array(),

       // Render Cache specific properties.
       // @todo Port to Drupal 8.
       'hash' => array(),
       'validate' => array(),

       // Special keys that are only related to our implementation.
       // @todo Remove and replace with something else.
       'render_cache_render_to_markup' => FALSE,
       'render_cache_ignore_request_method_check' => FALSE,
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getCacheIdInfo($object, array $cache_info = array(), array $context = array()) {
    $context = $this->getCacheContext($object, $context);

    $cache_info = drupal_array_merge_deep(
      $cache_info,
      $this->getCacheInfo($object, $context)
    );

    // Ensure these properties are always set.
    $cache_info += array(
      'keys' => array(),
      'hash' => array(),
      'tags' => array(),
      'validate' => array(),
    );

    // Set cache information properties.
    $cache_info['keys'] = array_merge(
      $cache_info['keys'],
      $this->getCacheKeys($object, $context)
    );
    $cache_info['hash'] = array_merge(
      $cache_info['hash'],
      $this->getCacheHash($object, $context)
    );
    $cache_info['tags'] = drupal_array_merge_deep(
      $cache_info['tags'],
      $this->getCacheTags($object, $context)
    );
    $cache_info['validate'] = drupal_array_merge_deep(
      $cache_info['validate'],
      $this->getCacheValidate($object, $context)
    );

    // @todo Remove this later.
    $cache_info['hash']['render_method'] = !empty($cache_info['render_cache_render_to_markup']);
    if ($cache_info['hash']['render_method']) {
      $cache_info['hash']['render_options'] = serialize($cache_info['render_cache_render_to_markup']);
    }

    $this->alter('cache_info', $cache_info, $object, $context);

    // If we can't cache this, return with cid set to NULL.
    if ($cache_info['granularity'] == DRUPAL_NO_CACHE) {
      $cache_info['cid'] = NULL;
      return $cache_info;
    }

    // If a Cache ID isset, we need to skip the rest.
    if (isset($cache_info['cid'])) {
      return $cache_info;
    }

    $keys = &$cache_info['keys'];
    $hash = &$cache_info['hash'];

    $tags = &$cache_info['tags'];
    $validate = &$cache_info['validate'];

    // Allow modules to alter the keys, hash, tags and validate.
    $this->alter('keys', $keys, $object, $cache_info, $context);
    $this->alter('hash', $hash, $object, $cache_info, $context);

    $this->alter('tags', $tags, $object, $cache_info, $context);
    $this->alter('validate', $validate, $object, $cache_info, $context);

    // Add drupal_render cid_parts based on granularity.
    $granularity = isset($cache_info['granularity']) ? $cache_info['granularity'] : NULL;
    $cid_parts = array_merge(
      $cache_info['keys'],
      drupal_render_cid_parts($granularity)
    );

    // Calculate the hash.
    $algorithm = variable_get('render_cache_hash_algorithm', 'md5');
    $cid_parts[] = hash($algorithm, implode('-', $cache_info['hash']));

    // Allow modules to alter the final cid_parts array.
    $this->alter('cid', $cid_parts, $cache_info, $object, $context);

    $cache_info['cid'] = implode(':', $cid_parts);

    return $cache_info;
  }

  /**
   * {@inheritdoc}
   */
  protected static function cacheRenderArray($render, $cache_info) {
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
   * Retrieves results from the cache.
   */
  protected function getCache(&$cids, $default_cache_info) {
    $objects = cache_get_multiple($cids, $default_cache_info['bin']);
    foreach ($objects as $cid => $cache) {
      $build = $this->processCacheEntry($cache->data, $default_cache_info);
      if (!$this->validateCacheEntry($build, $default_cache_info)) {
        unset($objects[$cid]);
        $cids[] = $cid;
        continue;
      }
      $objects[$cid] = $build;
    }
    return $objects;
  }

  protected function processCacheEntry(array $build, array $default_cache_info) {
    // Merge back previously saved properties.
    if (!empty($build['#attached']['render_cache'])) {
      $build += $build['#attached']['render_cache'];
      unset($build['#attached']['render_cache']);
    }

    return $build;
  }

  protected function validateCacheEntry(array $build, array $default_cache_info) {
    // @ todo revalidate objects here.
    return TRUE;
  }

  protected function determineCachingStrategy($cache_info, $id) {
    if (empty($cache_info['render_cache_render_to_markup'])) {
      return RENDER_CACHE_STRATEGY_NO_RENDER;
    }

    if (!empty($cache_info['render_cache_render_to_markup']['cache late'])) {
      return RENDER_CACHE_STRATEGY_LATE_RENDER;
    }

    return RENDER_CACHE_STRATEGY_DIRECT_RENDER;
  }

  protected static function getCleanStorage($render, $remove_render_cache = TRUE) {
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

  protected function setCache(&$render, $cache_info, $strategy) {
    switch ($strategy) {
      case RENDER_CACHE_STRATEGY_NO_RENDER:
        if (!empty($render['#cache']['cid'])) {
          // Prevent further caching.
          unset($render['#cache']['cid']);
          unset($render['#cache']['keys']);

          cache_set($cache_info['cid'], $render, $cache_info['bin'], $cache_info['expire']);
        }
      break;
      case RENDER_CACHE_STRATEGY_DIRECT_RENDER:
        $storage = static::getCleanStorage($render, FALSE);
        $render = array(
          '#markup' => drupal_render($render),
        );
        $render += $storage;
        $render = $this->processCacheEntry($render, $cache_info);
      break;
      case RENDER_CACHE_STRATEGY_LATE_RENDER:
        // Nothing to be done here.
      break;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function renderRecursive(array $objects) {
    $build = array();
    foreach ($objects as $id => $object) {

      $single_objects = array(
        $id => $object,
      );

      $this->increaseRecursion();
      $render = $this->render($single_objects);
      $storage = $this->decreaseRecursion();
      if (!empty($render[$id])) {
        $build[$id] = $render[$id];
        $build[$id]['x_render_cache_recursion_storage'] = $storage;
      }
    }
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  protected function increaseRecursion() {
    static::$recursionLevel += 1;
    static::$recursionStorage[static::$recursionLevel] = array();
  }

  /**
   * {@inheritdoc}
   */
  protected function decreaseRecursion() {
    $storage = static::getRecursionStorage();
    static::$recursionLevel -= 1;
    return $storage;
  }

  /**
   * {@inheritdoc}
   */
  protected function alter($type, &$data, &$context1 = NULL, &$context2 = NULL, &$context3 = NULL) {
    drupal_alter('render_cache_' . $this->getType() . '_' . $type, $data, $context1, $context2, $context3);
  }

  /**
   * Converts a cache tags array into a X-Drupal-Cache-Tags header value.
   *
   * @param array $tags
   *   Associative array of cache tags to flatten.
   *
   * @return string
   *   A space-separated list of flattened cache tag identifiers.
   */
  public static function convertCacheTagsToHeader(array $tags) {
    $flat_tags = array();
    foreach ($tags as $namespace => $values) {
      if (is_array($values)) {
        foreach ($values as $value) {
          $flat_tags[] = "$namespace:$value";
        }
      }
      else {
        $flat_tags[] = "$namespace:$values";
      }
    }
    return implode(' ', $flat_tags);
  }
}
