<?php
/**
 * @file 
 * A straight port of Drupal 8 render functions for advanced render caching.
 */

if (!variable_get('render_cache_drupal_8_render_defined', FALSE)) {

/**
 * Generates a render cache placeholder.
 *
 * This is used by drupal_pre_render_render_cache_placeholder() to generate
 * placeholders, but should also be called by #post_render_cache callbacks that
 * want to replace the placeholder with the final markup.
 *
 * @param string $callback
 *   The #post_render_cache callback that will replace the placeholder with its
 *   eventual markup.
 * @param array $context
 *   An array providing context for the #post_render_cache callback. This array
 *   will be altered to provide a 'token' key/value pair, if not already
 *   provided, to uniquely identify the generated placeholder.
 *
 * @return string
 *   The generated placeholder HTML.
 *
 * @throws \Exception
 *
 * @see drupal_render_cache_get()
 */
function drupal_render_cache_generate_placeholder($callback, array &$context) {
  if (!is_callable($callback)) {
    throw new Exception(t('$callable must be a callable function or of the form service_id:method.'));
  }

  // Generate a unique token if one is not already provided.
  $context += array(
    'token' => base64_encode(drupal_random_bytes(55)),
  );

  return '<drupal-render-cache-placeholder callback="' . $callback . '" token="' . $context['token'] . '"></drupal-render-cache-placeholder>';
}

/**
 * Processes #post_render_cache callbacks.
 *
 * #post_render_cache callbacks may modify:
 * - #markup: to replace placeholders
 * - #attached: to add libraries or JavaScript settings
 *
 * Note that in either of these cases, #post_render_cache callbacks are
 * implicitly idempotent: a placeholder that has been replaced can't be replaced
 * again, and duplicate attachments are ignored.
 *
 * @param array &$elements
 *   The structured array describing the data being rendered.
 *
 * @see drupal_render()
 * @see drupal_render_collect_post_render_cache
 */
function _drupal_render_process_post_render_cache(array &$elements) {
  if (isset($elements['#post_render_cache'])) {
    // Call all #post_render_cache callbacks, passing the provided context.
    foreach (array_keys($elements['#post_render_cache']) as $callback) {
      foreach ($elements['#post_render_cache'][$callback] as $context) {
        $elements = call_user_func_array($callback, array($elements, $context));
      }
    }
    // Make sure that any attachments added in #post_render_cache callbacks are
    // also executed.
    if (isset($elements['#attached'])) {
      drupal_process_attached($elements);
    }
  }
}

}
