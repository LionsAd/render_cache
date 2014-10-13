- Enable the module
- Ensure to add the following to your e.g. hook_render_cache_block_cache_info_alter():

/**
 * Implements hook_render_cache_block_cache_info_alter().
 */
function rc_site_render_cache_block_cache_info_alter(&$cache_info, $object, $context) {
  if ($context['module'] == 'rc_site') {
    // Need at least custom granularity for now.
    $cache_info['granularity'] = DRUPAL_CACHE_CUSTOM; 
    $cache_info['render_strategy'][] = 'big_pipe';
  }
}

- If you want to split the html.tpl.php at a different point add a comment to show where to SPLIT:

<!-- X-RENDER-CACHE-BIG-PIPE-SPLIT -->
