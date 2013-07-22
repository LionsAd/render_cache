<?php

/**
 * @file
 * Contains the node view row style plugin hijack for caching.
 */

/**
 * Plugin which performs a node_view on the resulting object.
 *
 * Attempts to cache rendered nodes per view mode and language.
 *
 * @ingroup views_row_plugins
 */
class render_cache_hijack_views_plugin_row_node_view extends views_plugin_row_node_view {
  /**
   * Override parent::render() to add caching.
   */
  function render($row) {
    if (isset($this->nodes[$row->{$this->field_alias}])) {
      $node = $this->nodes[$row->{$this->field_alias}];
      $cid = render_cache_get_cid('node', $node, $this->options['view_mode']);
      if ($cache = cache_get($cid, 'cache_render')) {
        if (variable_get('render_cache__method', 0)) {
          // Cached as string.
          return $cache->data;
        }
        else {
          // Cached as render array, requires rendering.
          return drupal_render($cache->data);
        }
      }

      // Retrieve render array for node.
      $build = node_view($node, $this->options['view_mode']);
      if (variable_get('render_cache__method', 0)) {
        // Cache result as a string.
        $render = drupal_render($build);
        cache_set($cid, $render, 'cache_render');
      }
      else {
        // Cache result as a render array.
        cache_set($cid, $build, 'cache_render');
        $render = drupal_render($build);
      }

      return $render;
    }
  }
}
