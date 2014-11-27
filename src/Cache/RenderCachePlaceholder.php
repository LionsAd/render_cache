<?php
/**
 * @file
 * Contains \Drupal\render_cache\Cache\RenderCachePlaceholder
 */

namespace Drupal\render_cache\Cache;

use RenderCache;

/**
 * Provides placeholder utility functions.
 *
 * @ingroup cache
 */
class RenderCachePlaceholder implements RenderCachePlaceholderInterface {

  /**
   * {@inheritdoc}
   */
  public static function getPlaceholder($function, array $args = array(), $multiple = FALSE) {
    // Add the classname to the front.
    $callback = get_called_class();
    $callback .= $multiple ? '::postRenderCacheMultiCallback' : '::postRenderCacheCallback';

    $context = array(
      'function' => $function,
      'args' => $args,
    );

    $placeholder = static::generatePlaceholder($context['function'], $context);

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
      $placeholder = static::generatePlaceholder($context['function'], $context);

      // Check if the placeholder is present at all.
      if (strpos($element['#markup'], $placeholder) === FALSE) {
        continue;
      }

      $args[$placeholder] = static::loadPlaceholderFunctionArgs($context);
    }

    // This expects an array keyed by placeholder with the build as value.
    $placeholders = call_user_func($function, $args);

    foreach ($placeholders as $placeholder => $new_element) {
      $markup = static::drupalRender($new_element);
      $element['#markup'] = str_replace($placeholder, $markup, $element['#markup']);
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function postRenderCacheCallback(array $element, array $context) {

    $placeholder = static::generatePlaceholder($context['function'], $context);

    // Check if the placeholder is present at all.
    if (strpos($element['#markup'], $placeholder) === FALSE) {
      return $element;
    }

    $function = $context['function'];
    $args = static::loadPlaceholderFunctionArgs($context);
    $new_element = call_user_func_array($function, $args);

    $markup = static::drupalRender($new_element);
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
      if (strpos($key, '%') !== 0) {
        $args[$key] = $arg;
        continue;
      }

      $prefix = substr($key, 1);
      $loader_functions = array(
        $prefix . '_load',
        $prefix . 'Load',
      );
      foreach ($loader_functions as $loader_function) {
        if (is_callable($loader_function)) {
          $arg = call_user_func($loader_function, $arg);
        }
      }
      $args[$key] = $arg;
    }

    return $args;
  }

  /**
   * Generates a render cache placeholder.
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
   * @codeCoverageIgnore
   */
  protected static function generatePlaceholder($callback, array &$context) {
    return drupal_render_cache_generate_placeholder($callback, $context);
  }

  /**
   * Overrides drupal_render().
   *
   * @param array $elements
   *   The elements to render.
   *
   * @return string
   *   The rendered HTML string.
   *
   * @codeCoverageIgnore
   */
  protected static function drupalRender(array &$elements) {
    return RenderCache::drupalRender($elements);
  }
}
