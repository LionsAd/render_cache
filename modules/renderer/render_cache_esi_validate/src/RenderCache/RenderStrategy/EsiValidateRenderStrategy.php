<?php
/**
 * @file
 * Contains \Drupal\render_cache_esi_validate\RenderCache\RenderStrategy\EsiValidateRenderStrategy
 */

namespace Drupal\render_cache_esi_validate\RenderCache\RenderStrategy;

use Drupal\render_cache\RenderCache\RenderStrategy\BaseRenderStrategy;

/**
 * ESI Validate RenderStrategy - Provides esi processing for placeholders.
 *
 * @ingroup rendercache
 */
class EsiValidateRenderStrategy extends BaseRenderStrategy {

  /**
   * {@inheritdoc}
   */
  public function render(array $args) {
    $placeholders = array();

    // This will only work if the caller allows ESI.
    if (empty($_SERVER['HTTP_X_DRUPAL_ESI_VALIDATE'])) {
      return array();
    }

    foreach ($args as $placeholder => $ph_object) {
      // If there is no cache ID, we can't ESI validate this.
      if (empty($ph_object['cache_info']['cid'])) {
	      continue;
      }

      $base_esi = 'render-cache/esi-validate-render';
      if (variable_get('render_cache_esi_use_php_script', FALSE)) {
        $base_esi = drupal_get_path('module', 'esi_render_cache') . '/esi_validate.php';
      }

      // The markup is already cached, so just provide a Cache ID.
      $url = url($base_esi, array(
        'query' => array(
          'cid' => $ph_object['cache_info']['cid'],
          'bin' => $ph_object['cache_info']['bin'],
        ),
      ));

      $placeholders[$placeholder] = array(
        '#markup' => '<esi:include src="' . $url . '" />',
      );
    }

    return $placeholders;
  }
}
