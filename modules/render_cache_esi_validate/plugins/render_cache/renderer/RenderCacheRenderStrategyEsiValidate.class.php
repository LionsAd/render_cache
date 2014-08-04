<?php

/**
 * RenderCacheController ESI Validate - Provides esi processing for placeholders.
 */
class RenderCacheRenderStrategyEsiValidate extends RenderCacheRenderStrategyBase {

  /**
   * {@inheritdoc}
   */
  public function render(array $placeholders) {
    $remaining_placeholders = array();

    // This will only work if the caller allows ESI.
    if (empty($_SERVER['HTTP_X_DRUPAL_ESI_VALIDATE'])) {
      return $placeholders;
    }

    foreach ($placeholders as $key => $placeholder) {
      // If there is no cache ID, we can't ESI validate this.
      if (empty($placeholder['cache_info']['cid']) {
        $remaining_placeholders[$key] = $placeholder;
	continue;
      }

      $base_esi = 'render-cache/esi-validate-render';
      if (variable_get('render_cache_esi_use_php_script', FALSE)) {
        $base_esi = drupal_get_path('module', 'esi_render_cache') . '/esi_validate.php';
      }

      // The markup is already cached, so just provide a Cache ID.
      $url = url($base_esi, array(
        'query' => array(
          'cid' => $placeholder['cache_info']['cid'],
          'bin' => $placeholder['cache_info']['bin'],
        ),
      ));

      $placeholders[$key] = '<esi:include src="' . $url . '" />';
    }

    return $remaining_placeholders;
  }
}
