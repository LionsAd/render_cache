<?php
/**
 * @file
 * Contains \Drupal\render_cache\RenderCache\Controller\BaseController
 */

namespace Drupal\render_cache\RenderCache\Controller;

use Drupal\render_cache\Cache\RenderCacheBackendAdapterInterface;
use Drupal\render_cache\Cache\RenderCachePlaceholder;
use Drupal\render_cache\Cache\RenderStack;
use Drupal\render_cache\Cache\RenderStackInterface;

use RenderCache;

/**
 * Base class for Controller plugin objects.
 *
 * @ingroup rendercache
 */
abstract class BaseController extends AbstractBaseController {

  /**
   * An optional context provided by this controller.
   *
   * @var array
   */
  protected $context = array();

  /**
   * The injected render stack.
   *
   * @var \Drupal\render_cache\Cache\RenderStackInterface
   */
  protected $renderStack;

  /**
   * The injected cache backend adapter.
   *
   * @var \Drupal\render_cache\Cache\RenderCacheBackendAdapter
   */
  protected $cache;

  /**
   * Constructs a controller plugin object.
   *
   * @param array $plugin
   *   The plugin definition.
   * @param \Drupal\render_cache\Cache\RenderStack $render_stack
   *   The render stack.
   * @param \Drupal\render_cache\Cache\RenderCacheBackendAdapter $cache
   *   The cache backend adapter.
   */
  public function __construct(array $plugin, RenderStackInterface $render_stack, RenderCacheBackendAdapterInterface $cache) {
    parent::__construct($plugin);
    $this->renderStack = $render_stack;
    $this->cache = $cache;
  }

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
  public function viewPlaceholders(array $objects) {
    $object_build = array();

    if (!empty($objects)) {
      $object_build = $this->render($objects);
    }

    return $object_build;
  }
  /**
   * {@inheritdoc}
   */
  public function view(array $objects) {
    $object_order = array_keys($objects);

    // Retrieve controller context.
    $context = $this->getContext();

    // Get default cache info and allow modules to alter it.
    $default_cache_info = $this->getDefaultCacheInfo($context);
    $this->alter('default_cache_info', $default_cache_info, $context);

    // Calculate cache info map.
    $cache_info_map = $this->getCacheInfoMap($objects, $context, $default_cache_info);
    $build = $this->cache->getMultiple($cache_info_map);
    $build += $this->getPlaceholders($objects, $cache_info_map, $context);

    $remaining = array_diff_key($objects, $build);

    // Render non-cached entities.
    if (!empty($remaining)) {
      $object_build = $this->renderRecursive($remaining);

      // @todo It is possible for modules to set the request to not cacheable, so
      // check this again.
      // @todo This conflicts with memcache stampede protection, will need to
      //       set empty cache entries instead.
      //if ($this->isCacheable($default_cache_info, $context)) {
      $this->cache->setMultiple($object_build, $cache_info_map);
      //}
      $build += $object_build;
    }

    $return = array();
    foreach ($object_order as $id) {
      // This can happen when a block, e.g. is empty.
      if (!isset($build[$id])) {
        continue;
      }
      $render = $build[$id];

      // Unset any remaining weight properties.
      unset($render['#weight']);

      // Store recursive storage and remove from render array.
      $storage = $this->renderStack->addRecursionStorage($render);

      $cache_info = $cache_info_map[$id];
      if (!$this->renderStack->isRecursive()) {
        $render += $storage;
        $this->renderStack->processPostRenderCache($render, $cache_info);
      }

      // @todo Use a #post_render function.
      if (isset($render['#markup'])
         && (variable_get('render_cache_debug_output', FALSE)
           || variable_get('render_cache_debug_output_' . $this->getType(), FALSE)
           || !empty($cache_info['render_cache_debug_output']))
         ) {
        // @todo Move to helper function.
        $prefix = '<!-- START RENDER ID: ' . $id . ' CACHE INFO: ' . "\n" . print_r($cache_info, TRUE);
        $prefix .= "\nHOOKS:\n";
        $hook_prefix = 'render_cache_' . $this->getType() . '_';
        foreach (array('default_cache_info', 'cache_info', 'keys', 'tags', 'hash', 'validate') as $hook) {
          $prefix .= '* hook_' . $hook_prefix . $hook . "_alter()\n";
        }
        $prefix .= '-->';
        $suffix = '<!-- END RENDER -->';
        $render['#markup'] = "\n$prefix\n" . $render['#markup'] . "\n$suffix\n";
      }

      $return[$id] = $render;
    }

    // If this is the main entry point.
    if (!$this->renderStack->isRecursive() && variable_get('render_cache_send_drupal_cache_tags', TRUE)) {
      $storage = $this->renderStack->getRecursionStorage();

      if (!empty($storage['#cache']['tags'])) {
        $header = static::convertCacheTagsToHeader($storage['#cache']['tags']);
        // @todo ensure render_cache is the top module.
        // Currently this header can be send multiple times.
        drupal_add_http_header('X-Drupal-Cache-Tags', $header, TRUE);
      }
    }

    return $return;
  }

  // -----------------------------------------------------------------------
  // Child implementable functions.

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
      // @todo indentation.
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
       // Allows special rendering via big pipe, esi, etc.
       'render_strategy' => array(),

       // Special keys that are only related to our implementation.
       // @todo Remove and replace with something else.
       'render_cache_render_to_markup' => FALSE,

       // New internal properties.
       'render_cache_ignore_request_method_check' => FALSE,
       'render_cache_cache_strategy' => NULL,
       'render_cache_preserve_properties' => array(),
    );
  }

  // -----------------------------------------------------------------------
  // Helper functions

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

    // Save the placeholder ID and remove cache id.
    if (!empty($cache_info['render_strategy'])) {
      $cache_info['placeholder_id'] = $cache_info['cid'];
      $cache_info['cid'] = NULL;
    }

    // If the caller caches this customly, unset cid.
    if ($cache_info['granularity'] == DRUPAL_CACHE_CUSTOM) {
      $cache_info['cid'] = NULL;
    }

    // Convert to the new format. (BC layer)
    if (empty($cache_info['render_cache_cache_strategy'])) {
      $strategy = $this->determineCachingStrategy($cache_info);
      $cache_info['render_cache_cache_strategy'] = $strategy;
    }
    if (!empty($cache_info['render_cache_render_to_markup']['preserve properties'])) {
      $cache_info['render_cache_preserve_properties'] = $cache_info['render_cache_render_to_markup']['preserve properties'];
    }
    unset($cache_info['render_cache_render_to_markup']);

    return $cache_info;
  }

  /**
   * Returns the cache information map for the given objects.
   *
   * @param array $objects
   *   The objects keyed by ID to get cache information for.
   * @param array $context
   *   The context given to the controller.
   * @param array $default_cache_info
   *   The default cache info structure.
   *
   * @return array
   *   Array keyed by ID with the cache info structures as values.
   */
  protected function getCacheInfoMap(array $objects, array $context, array $default_cache_info) {
    $cache_info_map = array();

    // Determine if this is cacheable.
    $is_cacheable = $this->isCacheable($default_cache_info, $context);

    // Retrieve a list of cache_info structures.
    foreach ($objects as $id => $object) {
      $object_context = $context;
      $object_context['id'] = $id;
      $cache_info_map[$id] = $this->getCacheIdInfo($object, $default_cache_info, $object_context);

      // If it is not cacheable, set the 'cid' to NULL.
      if (!$is_cacheable) {
        $cache_info_map[$id]['cid'] = NULL;
      }
    }

    return $cache_info_map;
  }

  /**
   * Determines the caching strategy for a given cache info structure.
   *
   * @param array $cache_info
   *   The cache information structure.
   *
   * @return int
   *   One of the RenderCache::RENDER_CACHE_STRATEGY_* constants.
   */
  protected function determineCachingStrategy($cache_info) {
    if (empty($cache_info['render_cache_render_to_markup'])) {
      return RenderCache::RENDER_CACHE_STRATEGY_NO_RENDER;
    }

    if (!empty($cache_info['render_cache_render_to_markup']['cache late'])) {
      return RenderCache::RENDER_CACHE_STRATEGY_LATE_RENDER;
    }

    return RenderCache::RENDER_CACHE_STRATEGY_DIRECT_RENDER;
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

      $this->renderStack->increaseRecursion();
      $render = $this->render($single_objects);
      $storage = $this->renderStack->decreaseRecursion();
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
  protected static function convertCacheTagsToHeader(array $tags) {
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

  /**
   * Get the placeholders from the cache information map.
   *
   * @param array $objects
   *   The objects keyed by ID to get cache information for.
   * @param array $cache_info_map
   *   The cache information map.
   * @param array $context
   *   The context given to the controller.
   *
   * @return array
   *   The render array keyed by id with placeholders as values.
   */
  protected function getPlaceholders(array $objects, array $cache_info_map, $context) {
    $build = array();
    foreach ($cache_info_map as $id => $cache_info) {
      if (empty($cache_info['placeholder_id'])) {
        continue;
      }

      // @todo Serialize the object.
      $ph_object = array(
        'id' => $id,
        'type' => $this->getType(),
        'context' => $context,
        'object' => $objects[$id],
        'cache_info' => $cache_info,
        // Put this for easy access here.
        'render_strategy' => $cache_info['render_strategy'],
      );
      $build[$id] = RenderCachePlaceholder::getPlaceholder(get_class($this) . '::renderPlaceholders', $ph_object, TRUE);
    }

    return $build;
  }

  /**
   * @param array $args
   *
   * @return array|string
   */
  public static function renderPlaceholders(array $args) {
    $all_placeholders = array();
    $strategies = array();

    foreach ($args as $placeholder => $ph_object) {
      foreach ($ph_object['render_strategy'] as $render_strategy) {
        $strategies[$render_strategy][$placeholder] = $placeholder;
      }

      // Fallback to direct rendering.
      $strategies['direct'][$placeholder] = $placeholder;
    }

    foreach ($strategies as $render_strategy => $placeholder_keys) {
      $rcs = render_cache_get_renderer($render_strategy);
      if (!$rcs) {
        continue;
      }

      $objects = array_intersect_key($args, $placeholder_keys);
      if (empty($objects)) {
        continue;
      }

      $placeholders = $rcs->render($objects);
      foreach ($placeholders as $placeholder => $render) {
        $all_placeholders[$placeholder] = $render;
        unset($args[$placeholder]);
      }
    }

    return $all_placeholders;
  }
}
