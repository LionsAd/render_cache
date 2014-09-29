<?php
/**
 * @file
 * Contains implementation of Render Cache Render Strategy direct class.
 */

/**
 * Direct fallback to render placeholders.
 */
class RenderCacheRenderStrategyDirect extends RenderCacheRenderStrategyBase {
  /**
   * {@inheritdoc}
   */
  public function render(array $args) {
    $placeholders = array();
    foreach ($args as $placeholder => $ph_object) {
      $placeholders[$placeholder] = array(
        '#markup' => 'Placeholder',
      );
    }

    return $placeholders;
  }
}
