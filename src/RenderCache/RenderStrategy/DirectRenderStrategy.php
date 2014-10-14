<?php
/**
 * @file
 * Contains \Drupal\render_cache\RenderCache\RenderStrategy\DirectRenderStrategy
 */

namespace Drupal\render_cache\RenderCache\RenderStrategy;

/**
 * Direct fallback to render placeholders.
 *
 * @ingroup rendercache
 */
class DirectRenderStrategy extends BaseRenderStrategy {
  /**
   * {@inheritdoc}
   */
  public function render(array $args) {
    $placeholders = array();
    foreach ($args as $placeholder => $ph_object) {
      $rcc = render_cache_get_controller($ph_object['type']);
      $rcc->setContext($ph_object['context']);
      $objects = array(
        $ph_object['id'] => $ph_object['object'],
      );
      $build = $rcc->viewPlaceholders($objects);

      $placeholders[$placeholder] = $build;
    }

    return $placeholders;
  }
}
