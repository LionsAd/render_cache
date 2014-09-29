<?php

/**
 * RenderCacheController Big Pipe - Provides big pipe processing for placeholders.
 */
class RenderCacheRenderStrategyBigPipe extends RenderCacheRenderStrategyBase {

  protected static $placeholders = array();

  public static function getPlaceholders() {
    return static::$placeholders;
  }

  /**
   * {@inheritdoc}
   */
  public function render(array $args) {
    $placeholders = array();
    foreach ($args as $placeholder => $ph_object) {
      $placeholders[$placeholder] = array();

      // @todo Replace with a nice loading theme.
      $placeholders[$placeholder]['#markup'] = $placeholder;

      // Store the data for later usage.
      static::$placeholders[$placeholder] = $ph_object;
    }

    return $placeholders;
  }
}
