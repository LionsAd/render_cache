<?php
/**
 * @file
 * Contains \Drupal\render_cache\RenderCache\Controller\BaseController
 */

namespace Drupal\render_cache\RenderCache\Controller;

use Drupal\render_cache\Cache\RenderCachePlaceholder;

define('RENDER_CACHE_STRATEGY_NO_RENDER', 0);
define('RENDER_CACHE_STRATEGY_DIRECT_RENDER', 1);
define('RENDER_CACHE_STRATEGY_LATE_RENDER', 2);

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
   * Constructs a controller plugin object.
   *
   * @param array $plugin
   *   The plugin definition.
   * @param \Drupal\render_cache\Cache\RenderStackInterface $render_stack
   *   The render stack.
   */
  public function __construct($plugin, $render_stack) {
    parent::__construct($plugin);
    $this->renderStack = $render_stack;
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
    // Retrieve controller context.
    $context = $this->getContext();

    // Get default cache info and allow modules to alter it.
    $default_cache_info = $this->getDefaultCacheInfo($context);
    $this->alter('default_cache_info', $default_cache_info, $context);

    $cid_map = array();
    $cache_info_map = array();
    $placeholders = array();
    $object_order = array_keys($objects);

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
      // If this should be rendered as a placeholder,
      // remove the CID as well.
      if (!empty($cache_info_map[$id]['placeholder_id'])) {
        $placeholders[$id] = $id;
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

    // Render placeholders.
    // @todo Helper function.
    $placeholder_build = array();
    foreach ($placeholders as $id) {
      // @todo Serialize the object.
      $ph_object = array(
        'id' => $id,
        'type' => $this->getType(),
        'context' => $context,
        'object' => $objects[$id],
        'cache_info' => $cache_info_map[$id],
        // Put this for easy access here.
        'render_strategy' => $cache_info_map[$id]['render_strategy'],
      );
      unset($objects[$id]);
      $placeholder_build[$id] = RenderCachePlaceholder::getPlaceholder(get_class($this) . '::renderPlaceholders', $ph_object, TRUE);
    }

    // Render non-cached entities.
    if (!empty($objects)) {
      $object_build = $this->renderRecursive($objects);
    }

    $build = array();
    foreach ($object_order as $id) {
      $cid = $cid_map[$id];
      $cache_info = $cache_info_map[$id];
      $strategy = $this->determineCachingStrategy($cache_info);

      if (isset($cached_build[$cid])) {
        // This has been already processed by the processCacheEntry() function.
        $render = $cached_build[$cid];
      } elseif (isset($placeholder_build[$id])) {
        $render = $placeholder_build[$id];
      } else {
        // If the object is not set, there is nothing we can do here.
        if (!isset($object_build[$id])) {
          continue;
        }
        $render = $object_build[$id];
        $render = $this->renderStack->cacheRenderArray($render, $cache_info);

        // If this should not be cached, unset the cache info properties.
        if (!$cid) {
          unset($render['#cache']['cid']);
          unset($render['#cache']['keys']);
        }

        $this->setCache($render, $cache_info, $strategy);
      }

      // Store recursive storage.
      $this->renderStack->addRecursionStorage($render);

      // Unset any remaining weight properties.
      unset($render['#weight']);

      $post_render_cache = array();

      if ($strategy == RENDER_CACHE_STRATEGY_DIRECT_RENDER) {
        unset($render['#attached']);
        unset($render['#cache']);
        if (!empty($render['#post_render_cache'])) {
          $post_render_cache = $render['#post_render_cache'];
        }
        unset($render['#post_render_cache']);
      }

      // Only when we have #markup we can post process.
      if ($strategy == RENDER_CACHE_STRATEGY_DIRECT_RENDER
         && !empty($post_render_cache)
         && !$this->renderStack->isRecursive()) {

        $render['#post_render_cache'] = $post_render_cache;

        // @todo add back recursive post render cache.
        $this->renderStack->increaseRecursion();
        _drupal_render_process_post_render_cache($render);
        $storage = $this->renderStack->decreaseRecursion();
        $this->renderStack->addRecursionStorage($storage);

        unset($render['#attached']);
        unset($render['#cache']);
        unset($render['#post_render_cache']);
      }

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

      $build[$id] = $render;
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

    return $build;
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
       'render_cache_ignore_request_method_check' => FALSE,
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

    // Save the placeholder ID.
    if (!empty($cache_info['render_strategy'])) {
      $cache_info['placeholder_id'] = $cache_info['cid'];
    }

    // If the caller caches this customly still, unset cid.
    if ($cache_info['granularity'] == DRUPAL_CACHE_CUSTOM) {
      $cache_info['cid'] = NULL;
    }

    return $cache_info;
  }

  /**
   * Retrieves results from the cache.
   *
   * @param string[] $cids
   * @param array $default_cache_info
   *
   * @return
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

  /**
   * @param array $build
   * @param array $default_cache_info
   *
   * @return array
   */
  protected function processCacheEntry(array $build, array $default_cache_info) {
    // Merge back previously saved properties.
    if (!empty($build['#attached']['render_cache'])) {
      $build += $build['#attached']['render_cache'];
      unset($build['#attached']['render_cache']);
    }

    return $build;
  }

  /**
   * @param array $build
   * @param array $default_cache_info
   *
   * @return bool
   */
  protected function validateCacheEntry(array $build, array $default_cache_info) {
    // @ todo revalidate objects here.
    return TRUE;
  }

  /**
   * @param array $cache_info
   *
   * @return int
   *   One of the RENDER_CACHE_STRATEGY_* constants.
   */
  protected function determineCachingStrategy($cache_info) {
    if (empty($cache_info['render_cache_render_to_markup'])) {
      return RENDER_CACHE_STRATEGY_NO_RENDER;
    }

    if (!empty($cache_info['render_cache_render_to_markup']['cache late'])) {
      return RENDER_CACHE_STRATEGY_LATE_RENDER;
    }

    return RENDER_CACHE_STRATEGY_DIRECT_RENDER;
  }

  /**
   * @param array $render
   * @param array $cache_info
   * @param int $strategy
   *   One of the RENDER_CACHE_STRATEGY_* constants.
   */
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
        $storage = $this->renderStack->getCleanStorage($render, FALSE);

        // Do not have drupal_render() render this, as we need recursion
        // support.
        unset($render['#cache']['cid']);
        unset($render['#cache']['keys']);

        // This will catch resources added during preprocess phases.
        $this->renderStack->increaseRecursion();
        $render = array(
          '#markup' => drupal_render($render),
        );
        $render_storage = $this->renderStack->decreaseRecursion();

        if (!empty($render_storage)) {
          // Add to storage.
          $storage['x_render_cache_drupal_render_recursion_storage'] = $render_storage;
          $storage = $this->renderStack->cacheRenderArray($storage);
          $storage = $this->renderStack->getCleanStorage($storage, FALSE);
        }

        $render['#attached'] = $storage['#attached'];

        cache_set($cache_info['cid'], $render, $cache_info['bin'], $cache_info['expire']);

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
