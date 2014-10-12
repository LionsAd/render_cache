<?php

/**
 * Implements hook_views_plugins_alter().
 *
 * @param array[] $plugins
 */
function render_cache_views_views_plugins_alter(&$plugins) {
  $plugins['row']['node']['handler'] = 'render_cache_hijack_views_plugin_row_node_view';
}

/**
 * Implements hook_field_views_data_alter().
 */
function render_cache_field_views_data_alter(&$result, $field, $module) {
  foreach ($result as $table => $columns) {
    foreach ($columns as $key => $column) {
      if (!empty($column['field']['handler']) && $column['field']['handler'] == 'views_handler_field_field') {
        $result[$table][$key]['field']['handler'] = 'render_cache_hijack_views_handler_field_field';
      }
    }
  }
}
