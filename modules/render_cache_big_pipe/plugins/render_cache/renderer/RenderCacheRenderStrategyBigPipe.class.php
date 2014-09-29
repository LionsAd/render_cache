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
      // @todo Replace with a nice loading theme.
      $placeholders[$placeholder] = array();
      $id = drupal_html_id('render-cache-big-pipe-' . $ph_object['type'] . '-' . $ph_object['id']);
      $placeholders[$placeholder]['#markup'] = '<div id="' . $id . '"></div>';

      // Store the data for later usage.
      static::$placeholders[$id] = $ph_object;
    }

    return $placeholders;
  }

  public function renderPlaceholder($placeholder, $ph_object) {
    $rcc = render_cache_get_controller($ph_object['type']);
    $rcc->setContext($ph_object['context']);
    $objects = array(
      $ph_object['id'] => $ph_object['object'],
    );
    $build = $rcc->viewPlaceholders($objects);
    $output = drupal_render($build);

    $html = drupal_json_encode($output);

    // @todo Add helper function.
    $markup = <<<EOF
<script type="text/javascript">
var element = document.getElementById("$placeholder");
var newElement = document.createElement("span");
newElement.innerHTML = $html;
var newChild = newElement.firstChild;
element.parentNode.replaceChild(newChild, element);
</script>;
EOF;

    return $markup;
  }
}
