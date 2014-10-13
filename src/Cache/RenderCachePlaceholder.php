<?php
/**
 * @file
 * Contains \Drupal\render_cache\Cache\RenderCachePlaceholder
 */

namespace Drupal\render_cache\Cache;

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
    $callback = $multiple ? 'RenderCachePlaceholder::postRenderCacheMultiCallback' : 'RenderCachePlaceholder::postRenderCacheCallback';
    // Add namespace to the front.
    $callback = "\\Drupal\\render_cache\\Cache\\" . $callback;

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
