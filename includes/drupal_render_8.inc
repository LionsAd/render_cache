<?php
/**
 * @file 
 * A straight port of Drupal 8 render functions for advanced render caching.
 */

/**
 * Generates a render cache placeholder.
 *
 * This is used by drupal_pre_render_render_cache_placeholder() to generate
 * placeholders, but should also be called by #post_render_cache callbacks that
 * want to replace the placeholder with the final markup.
 *
 * @param string $callback
 *   The #post_render_cache callback that will replace the placeholder with its
 *   eventual markup.
 * @param array $context
 *   An array providing context for the #post_render_cache callback. This array
 *   will be altered to provide a 'token' key/value pair, if not already
 *   provided, to uniquely identify the generated placeholder.
 *
 * @return string
 *   The generated placeholder HTML.
 *
 * @throws \Exception
 *
 * @see drupal_render_cache_get()
 */
function drupal_render_cache_generate_placeholder($callback, array &$context) {
  if (!is_callable($callback)) {
    throw new Exception(t('$callable must be a callable function or of the form service_id:method.'));
  }

  // Generate a unique token if one is not already provided.
  $context += array(
    'token' => drupal_random_bytes(55),
  );

  return '<drupal-render-cache-placeholder callback="' . $callback . '" token="' . $context['token'] . '"></drupal-render-cache-placeholder>';
}

/**
 * Processes #post_render_cache callbacks.
 *
 * #post_render_cache callbacks may modify:
 * - #markup: to replace placeholders
 * - #attached: to add libraries or JavaScript settings
 *
 * Note that in either of these cases, #post_render_cache callbacks are
 * implicitly idempotent: a placeholder that has been replaced can't be replaced
 * again, and duplicate attachments are ignored.
 *
 * @param array &$elements
 *   The structured array describing the data being rendered.
 *
 * @see drupal_render()
 * @see drupal_render_collect_post_render_cache
 */
function _drupal_render_process_post_render_cache(array &$elements) {
  if (isset($elements['#post_render_cache'])) {
    // Call all #post_render_cache callbacks, passing the provided context.
    foreach (array_keys($elements['#post_render_cache']) as $callback) {
      foreach ($elements['#post_render_cache'][$callback] as $context) {
        $elements = call_user_func_array($callback, array($elements, $context));
      }
    }
    // Make sure that any attachments added in #post_render_cache callbacks are
    // also executed.
    if (isset($elements['#attached'])) {
      drupal_process_attached($elements);
    }
  }
}

/**
 * Collects #post_render_cache for an element and its children into a single
 * array.
 *
 * When caching elements, it is necessary to collect all #post_render_cache
 * callbacks into a single array, from both the element itself and all child
 * elements. This allows drupal_render() to execute all of them when the element
 * is retrieved from the render cache.
 *
 * Note: the theme system may render child elements directly (e.g. rendering a
 * node causes its template to be rendered, which causes the node links to be
 * drupal_render()ed). On top of that, the theme system transforms render arrays
 * into HTML strings. These two facts combined imply that it is impossible for
 * #post_render_cache callbacks to bubble up to the root of the render array.
 * Therefore, drupal_render_collect_post_render_cache() must be called *before*
 * #theme callbacks, so that it has a chance to examine the full render array.
 * In short: in order to examine the full render array for #post_render_cache
 * callbacks, it must use post-order tree traversal, whereas drupal_render()
 * itself uses pre-order tree traversal.
 *
 * @param array &$elements
 *   The element to collect #post_render_cache callbacks for.
 * @param array $callbacks
 *   Internal use only. The #post_render_callbacks array so far.
 * @param bool $is_root_element
 *   Internal use only. Whether the element being processed is the root or not.
 *
 * @return
 *   The #post_render_cache array for this element and its descendants.
 *
 * @see drupal_render()
 * @see _drupal_render_process_post_render_cache()
 */
function drupal_render_collect_post_render_cache(array &$elements, array $callbacks = array(), $is_root_element = TRUE) {
  // Try to fetch the prerendered element from cache, to determine
  // #post_render_cache callbacks for this element and all its children. If we
  // don't do this, then the #post_render_cache tokens will be re-generated, but
  // they would no longer match the tokens in the render cached markup, causing
  // the render cache placeholder markup to be sent to the end user!
  $retrieved_from_cache = FALSE;
  if (!$is_root_element && isset($elements['#cache'])) {
    $cached_element = drupal_render_cache_get($elements);
    if ($cached_element !== FALSE && isset($cached_element['#post_render_cache'])) {
      $elements['#post_render_cache'] = $cached_element['#post_render_cache'];
      $retrieved_from_cache = TRUE;
    }
  }

  // Collect all #post_render_cache callbacks for this element.
  if (isset($elements['#post_render_cache'])) {
    $callbacks = drupal_array_merge_deep($callbacks, $elements['#post_render_cache']);
  }

  // Collect the #post_render_cache callbacks for all child elements, unless
  // we've already collected them above by retrieving this element (and its
  // children) from the render cache.
  if (!$retrieved_from_cache && $children = element_children($elements)) {
    foreach ($children as $child) {
      $callbacks = drupal_render_collect_post_render_cache($elements[$child], $callbacks, FALSE);
    }
  }

  return $callbacks;
}

/**
 * Collects cache tags for an element and its children into a single array.
 *
 * The cache tags array is returned in a format that is valid for
 * \Drupal\Core\Cache\CacheBackendInterface::set().
 *
 * When caching elements, it is necessary to collect all cache tags into a
 * single array, from both the element itself and all child elements. This
 * allows items to be invalidated based on all tags attached to the content
 * they're constituted from.
 *
 * @param array $element
 *   The element to collect cache tags from.
 * @param array $tags
 *   (optional) An array of already collected cache tags (i.e. from a parent
 *   element). Defaults to an empty array.
 *
 * @return array
 *   The cache tags array for this element and its descendants.
 */
function drupal_render_collect_cache_tags($element, $tags = array()) {
  if (isset($element['#cache']['tags'])) {
    foreach ($element['#cache']['tags'] as $namespace => $values) {
      if (is_array($values)) {
        foreach ($values as $value) {
          $tags[$namespace][$value] = $value;
        }
      }
      else {
        if (!isset($tags[$namespace])) {
          $tags[$namespace] = $values;
        }
      }
    }
  }
  if ($children = element_children($element)) {
    foreach ($children as $child) {
      $tags = drupal_render_collect_cache_tags($element[$child], $tags);
    }
  }

  return $tags;
}
