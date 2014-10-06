<?php
/**
 * @file
 * Contains implementation of RenderCache placeholder functionality.
 */

interface RenderCachePlaceholderInterface {
  /**
   * Gets a #post_render_cache placeholder for a given function.
   *
   * @param $function
   *   The function to call when the #post_render_cache callback is invoked.
   * @param array $args
   *   The arguments to pass to the function. This can contain %load arguments,
   *   similar to how menu paths are working.
   *   @code
   *     $args = array(
   *       '%node' => $node->nid,
   *       'full',
   *     );
   *   @endcode
   *   Given %node the function node_load() will be called with the argument, the resulting
   *   function will just be given the $node as argument.
   * @param bool $multi
   *   Whether the function accepts multiple contexts. This is useful to group similar objects
   *   together.
   *
   * @return array
   *   A render array with #markup set to the placeholder and
   *   #post_render_cache callback set to callback postRenderCacheCallback()
   *   with the given arguments and function encoded in the context.
   */
  public static function getPlaceholder($function, array $args = array(), $multiple = FALSE);

  /**
   * Generic #post_render_cache callback for getPlaceholder().
   *
   * @param array $element
   *   The renderable array that contains the to be replaced placeholder.
   * @param array $context
   *   An array with the following keys:
   *   - function: The function to call.
   *   - args: The arguments to pass to the function.
   *
   * @return array
   *   A renderable array with the placeholder replaced.
   */
  public static function postRenderCacheCallback(array $element, array $context);

  /**
   * Generic #post_render_cache callback for getPlaceholder() with multi=TRUE.
   *
   * This is useful to group several related elements together.
   *
   * @param array $element
   *   The renderable array that contains the to be replaced placeholders.
   * @param array $contexts
   *   An array keyed by function with the contexts as values.
   *
   * @return array
   *   A renderable array with the placeholders replaced.
   */
  public static function postRenderCacheMultiCallback(array $element, array $contexts);


  /**
   * Loads the %load arguments within $context['args'].
   *
   * @see RenderCachePlaceholderInterface::getPlaceholder()
   *
   * @param array $context
   *   An array with the following keys:
   *   - function: The function to call.
   *   - args: The arguments to process.
   *
   * @return array
   *   The function arguments suitable for call_user_func_array() with
   *   an argument keyed %node with a value of nid replaced with the loaded
   *   $node.
   */
  public static function loadPlaceholderFunctionArgs(array $context);
}

/**
 * Provides placeholder utility functions.
 */
class RenderCachePlaceholder implements RenderCachePlaceholderInterface {

  /**
   * {@inheritdoc}
   */
  public static function getPlaceholder($function, array $args = array(), $multiple = FALSE) {
    $callback = $multiple ? 'RenderCachePlaceholder::postRenderCacheMultiCallback' : 'RenderCachePlaceholder::postRenderCacheCallback';

    $context = array(
      'function' => $function,
      'args' => $args,
    );

    $placeholder = drupal_render_cache_generate_placeholder($context['function'], $context);

    $context = array($context);
    if ($multiple) {
      $context = array(
        $function => $context,
      );
    }

    return array(
      '#post_render_cache' => array(
        $callback => $context,
      ),
      '#markup' => $placeholder,
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function postRenderCacheMultiCallback(array $element, array $contexts) {
    // Check this is really a multi placeholder.
    if (isset($contexts['function']) || !isset($contexts[0]['function'])) {
      return $element;
    }

    $function = $contexts[0]['function'];
    $args = array();
    foreach ($contexts as $context) {
      $placeholder = drupal_render_cache_generate_placeholder($context['function'], $context);

      // Check if the placeholder is present at all.
      if (strpos($element['#markup'], $placeholder) === FALSE) {
        continue;
      }

      $args[$placeholder] = $context['args'];
    }

    // This expects an array keyed by placeholder with the build as value.
    $placeholders = call_user_func($function, $args);

    foreach ($placeholders as $placeholder => $new_element) {
      $markup = drupal_render($new_element);
      $element['#markup'] = str_replace($placeholder, $markup, $element['#markup']);
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function postRenderCacheCallback(array $element, array $context) {

    $placeholder = drupal_render_cache_generate_placeholder($context['function'], $context);

    // Check if the placeholder is present at all.
    if (strpos($element['#markup'], $placeholder) === FALSE) {
      return $element;
    }

    $function = $context['function'];
    $args = static::loadPlaceholderFunctionArgs($context);
    $new_element = call_user_func_array($function, $args);

    $markup = RenderCacheControllerBase::drupalRender($new_element);
    $element['#markup'] = str_replace($placeholder, $markup, $element['#markup']);

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function loadPlaceholderFunctionArgs(array $context) {
    $args = array();

    foreach ($context['args'] as $key => $arg) {
      // In case a dynamic argument has been passed, load it with the loader.
      if (preg_match('/^%(|' . DRUPAL_PHP_FUNCTION_PATTERN . ')$/', $key, $matches)) {
        if (!empty($matches[1]) && function_exists($matches[1] . '_load')) {
          $loader_function = $matches[1] . '_load';
          $arg = $loader_function($arg);
        }
      }
      $args[] = $arg;
    }

    return $args;
  }
}
