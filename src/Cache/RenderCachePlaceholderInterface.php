<?php
/**
 * @file
 * Contains \Drupal\render_cache\Cache\RenderCachePlaceholderInterface
 */

namespace Drupal\render_cache\Cache;

/**
 * Defines an interface for #post_render_cache placeholders.
 *
 * @ingroup cache
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
   * @param bool $multiple
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

