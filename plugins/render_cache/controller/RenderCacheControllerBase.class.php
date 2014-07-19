<?php

/**
 * Interface to describe how RenderCache controller plugin objects are implemented.
 */
interface RenderCacheControllerInterface {
  public function getContext();
  public function setContext(array $context);

  protected function getDefaultCacheInfo();
  protected function getCacheInfo();

  protected function getCacheID();

  public function view(array $objects);

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
  protected function render(array $objects);
}

/**
 * Base class for RenderCacheController plugin objects.
 */
abstract class RenderCacheControllerBase extends RenderCachePluginBase implements RenderCacheControllerInterface {
  /**
   * An optional context provided by this controller.
   */
  protected $context = array();

  /**
   * {@inheritdoc}
   */
  public function setContext(array $context) {
    $this->context = $context;
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
  protected function alter($type, &$data, &$context1 = NULL, &$context2 = NULL, &$context3 = NULL) {
    drupal_alter('render_cache_' . $this->getType() . '_' . $type, $data, $context1, $context2, $context3);
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultCacheInfo() {
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
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getCacheContext($object, $context) {
    return $context;
  }

  /**
   * {@inheritdoc}
   */
  protected function getCacheKeys($object, $context) {
    return array(
      'render_cache',
      $this->getType(),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getCacheHash($object, $context) {
    return array(
      'id' => $context['id'],
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getCacheTags($object, $context) {
    return array(
      'content' => TRUE,
      $this->getType() => TRUE,
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getCacheValidate($object, $context) {
    return array();
  }

  /**
   * {@inheritdoc}
   */
  protected function getCacheInfo($object, $cache_info = array(), $context = array()) {
    $context = $this->getCacheContext($object, $context);

    // Set cache information properties.
    $cache_info['keys'] = array_merge(
      $cache_info['keys'],
      $this->getCacheKeys($object, $context),
    );
    $cache_info['hash'] = array_merge(
      $cache_info['hash'],
      $this->getCacheHash($object, $context),
    );
    $cache_info['tags'] = drupal_array_merge_deep(
      $cache_info['tags'],
      $this->getCacheTags($object, $context),
    );
    $cache_info['validate'] = drupal_array_merge_deep(
      $cache_info['validate'],
      $this->getCacheValidate($object, $context),
    );
 
    // @todo Remove this later.
    $cache_info['hash']['render_method'] = !empty($cache_info['render_cache_render_to_markup']);
    if ($cache_info['hash']['render_method']) {
      $cache_info['hash']['render_options'] = serialize($cache_info['render_cache_render_to_markup']);
    }
   
    $this->alter('cache_info', $cache_info, $object, $context);
  
    // If we can't cache this, return with cid set to NULL.
    if ($cache_info['granularity'] == DRUPAL_NO_CACHE)) {
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
    $this->alter('keys', $keys, $cache_info, $object, $context);
    $this->alter('hash', $hash, $cache_info, $object, $context);

    $this->alter('tags', $tags, $cache_info, $object, $context);
    $this->alter('validate', $validate, $cache_info, $object, $context);
    
    // Add drupal_render cid_parts based on granularity.
    $granularity = isset($cache_info['granularity']) ? $cache_info['granularity'] : NULL;
    $cid_parts = array_merge(
      $cache_info['keys'],
      drupal_render_cid_parts($granularity),
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
  public function view(array $objects) {
    // Ensure all properties are set.
    $cache_info = $this->getDefaultCacheInfo();
    $context = $this->getContext();

    $this->alter('default_cache_info', $cache_info, $context);

    // Retrieve a list of cache_ids
    $cid_map = array();
    $cache_info_map = array();
    foreach ($objects as $id => $object) {
      $context['id'] = $id;
      $cache_info_map[$id] = $this->getCacheInfo($object, $cache_info, $context);
      $cid_map[$id] = $cache_info_map[$id]['cid'];
    }
  
    $object_order = array_keys($objects);
  
    $cids = array_filter(array_values($cid_map));
    $cached_objects = cache_get_multiple($cids, $cache_info['bin']);
  
     // Calculate remaining entities
    $ids_remaining = array_intersect($cid_map, $cids);
    $objects = array_intersect_key($objects, $ids_remaining);
  
    // Render non-cached entities.
    if (!empty($objects)) {
      $object_build = $this->render($objects);
    }
  
    $build = array();
    foreach ($object_order as $id) {
      $cid = $cid_map[$id];
      $cache_info = $cache_info_map[$id];

      if (isset($cached_objects[$cid])) {
        $render = $cached_objects[$cid]->data;

        // Potentially merge back previously saved properties.
        // @todo Helper
        if (!empty($render['#attached']['render_cache'])) {
          $render += $render['#attached']['render_cache'];
          unset($render['#attached']['render_cache']);
        }
      } else {
        $render = $object_build[$id];

        // @todo Helper
        if (empty($cache_info['render_cache_render_to_markup'])) {
          cache_set($cid, $render, 'cache_render');
        }
        else {
          // Process markup with drupal_render() caching.
          $render['#cache'] = $cache_info;

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
          if (!empty($render_cache_attached)) {
            $render['#attached']['render_cache'] = $render_cache_attached;
          }

          // Do we want to render now?
          if (empty($cache_info['render_cache_render_to_markup']['cache late'])) {
            // And save things. Also add our preserved properties back.
            $render = array(
              '#markup' => drupal_render($render),
            ) + $render_cache_attached;
          }
        }
      }

      // Unset any weight properties.
      unset($render['#weight']);

      // Run any post-render callbacks.
      render_cache_process_attached_callbacks($render, $id);

      //$this->processRenderArray($render);
      
      $build[$id] = $render;
    }
  }
}
