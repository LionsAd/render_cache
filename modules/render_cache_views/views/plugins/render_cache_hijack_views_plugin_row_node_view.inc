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

      // Use entity_view to display this node potentially cached.
      $nodes = entity_view('node', array($node->nid => $node), $this->options['view_mode']);

      return drupal_render($nodes);
    }
  }
}
