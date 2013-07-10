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
  function render($row) {
    if (isset($this->nodes[$row->{$this->field_alias}])) {
      $node = $this->nodes[$row->{$this->field_alias}];
      $cid = render_cache_get_cid('node', $node, $this->options['view_mode']);
      if ($cache = cache_get($cid, 'cache_render')) {
        return $cache->data;
      }

      $render = parent::render($row);
      cache_set($cid, $render, 'cache_render');
      return $render;
    }
  }
}
