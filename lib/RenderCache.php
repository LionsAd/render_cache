<?php
/**
 * @file
 * Contains RenderCache
 */

/**
 * Static Service Container wrapper wrapping Drupal class.
 */
class RenderCache {

  /**
   * Indicates that the item should not be rendered before it is cached.
   *
   * This is useful if the unrendered output is changed dynamically, before
   * it is cached.
   *
   * This is also the slowest option and should be regarded as a 'last-resort'
   * option.
   */
  const RENDER_CACHE_STRATEGY_NO_RENDER = 0;

  /**
   * Indicates that the item should be rendered before it is cached.
   *
   * If there is code that needs to change some properties,
   * the 'render_cache_preserve_properties' can be used.
   *
   * This is used for example to preserve page_footer / page_top properties
   * for #theme => html.
   */
  const RENDER_CACHE_STRATEGY_DIRECT_RENDER = 1;

  /**
   * Indicates that the item should not be rendered and it should be cached via
   * drupal_render() and drupal_render_cache_set().
   *
   * This is useful for e.g display suite when you want to change the markup
   * after entity_view(), but before calling drupal_render().
   *
   * This is most useful in combination with 'render_cache_preserve_properties',
   * because the entries retrieved from cache will only have #markup
   * and #attached keys by default.
   */
  const RENDER_CACHE_STRATEGY_LATE_RENDER = 2;

  // Drupal 8's constants differ from those in Drupal 7.
  // So we explicitly define them again as class constants.
  // Note: Drupal 8 has CACHE_PERMANENT == -1.

  /**
   * Indicates that the item should never be removed unless explicitly selected.
   *
   * The item may be removed using cache_clear_all() with a cache ID.
   */
  const CACHE_PERMANENT = 0;

  /**
   * Indicates that the item should be removed at the next general cache wipe.
   */
  const CACHE_TEMPORARY = -1;

  /**
   * The currently active container object.
   *
   * @var \Drupal\render_cache\DependencyInjection\ContainerInterface
   */
  protected static $container;

  /**
   * The currently active render stack - for improved performance.
   *
   * @var \Drupal\render_cache\Cache\RenderStack
   */
  protected static $renderStack;

  /**
   * Post initialization function.
   *
   * Will be removed once the base class is moved to service_container.
   *
   * @todo This is currently not called.
   */
  protected static function postInit() {
    // Last get the render stack for improved performance.
    if (static::$container) {
      static::$renderStack = static::$container->get('render_stack');

      // Check if we support dynamic asset loading via drupal_add_js/css.
      if (variable_get('render_cache_supports_dynamic_assets', FALSE)) {
        static::$renderStack->supportsDynamicAssets(TRUE);
      }
    }
  }

  /**
   * Returns a render cache controller plugin.
   *
   * @param string $type
   *   The type of the controller plugin, e.g. block, entity, ...
   *
   * @return \Drupal\render_cache\RenderCache\Controller\ControllerInterface|NULL
   *   The instantiated controller with the given type or NULL.
   */
  public static function getController($type) {
    return static::$container->get('render_cache.controller')->createInstance($type);
  }

  /**
   * Returns a render cache render strategy plugin.
   *
   * @param string $type
   *   The type of the render strategy plugin, e.g. big_pipe, esi_validate, ...
   *
   * @return \Drupal\render_cache\RenderCache\RenderStrategy\RenderStrategyInterface|NULL
   *   The instantiated render strategy with the given type or NULL.
   */
  public static function getRenderStrategy($type) {
    return static::$container->get('render_cache.render_strategy')->createInstance($type);
  }

  /**
   * Returns a render cache validation strategy plugin.
   *
   * @param string $type
   *   The type of the validation strategy plugin, e.g. cache tags, ttl, ...
   *
   * @return \Drupal\render_cache\RenderCache\ValidationStrategy\ValidationStrategyInterface|NULL
   *   The instantiated validation strategy with the given type or NULL.
   */
  public static function getValidationStrategy($type) {
    return static::$container->get('render_cache.validation_strategy')->createInstance($type);
  }

  /**
   * Overrides drupal_render().
   *
   * If we really need to render early, at least collect the cache tags, etc.
   *
   * @param array $render
   *
   * @return string
   */
  public static function drupalRender(&$render) {
    return static::$renderStack->drupalRender($render);
  }

  /**
   * Returns if we are within a recursive rendering context.
   *
   * This is useful to determine if its safe to output a placeholder, so that
   * #post_render_cache will work.
   *
   * @return bool
   *   TRUE if we are within a recursive context, FALSE otherwise.
   */
  public static function isRecursive() {
    return static::$renderStack->isRecursive();
  }

  /**
   * Overrides drupal_add_js().
   *
   * @see drupal_add_js()
   */
  public static function drupal_add_js($data = NULL, $options = NULL) {
    if (!static::$renderStack) {
      return static::callOriginalFunction('drupal_add_js', func_get_args());
    }
    return static::$renderStack->drupal_add_assets('js', $data, $options);
  }

  /**
   * Overrides drupal_add_css().
   *
   * @see drupal_add_css()
   */
  public static function drupal_add_css($data = NULL, $options = NULL) {
    if (!static::$renderStack) {
      return static::callOriginalFunction('drupal_add_css', func_get_args());
    }
    return static::$renderStack->drupal_add_assets('css', $data, $options);
  }

  /**
   * Overrides drupal_add_library().
   *
   * @see drupal_add_library()
   */
  public static function drupal_add_library($module, $name, $every_page = NULL) {
    if (!static::$renderStack) {
      return static::callOriginalFunction('drupal_add_library', func_get_args());
    }
    return static::$renderStack->drupal_add_library($module, $name, $every_page);
  }

  /**
   * Overrides drupal_process_attached().
   *
   * @see drupal_process_attached()
   */
  public static function drupal_process_attached($elements, $group = JS_DEFAULT, $dependency_check = FALSE, $every_page = NULL) {
    if (!static::$renderStack) {
      return static::callOriginalFunction('drupal_process_attached', func_get_args());
    }
    return static::$renderStack->drupal_process_attached($elements, $group, $dependency_check, $every_page);
  }

  /**
   * Calls the original function in case the container is not yet booted.
   *
   * @param string $function
   *   The function name.
   * @param array $args
   *   The function args.
   * @return NULL|mixed
   *   Returns what the original function returns.
   */
  public static function callOriginalFunction($function, $args) {
    global $conf;

    $name = $function . "_function";

    $old = $conf[$name];
    unset($conf[$name]);
    $return = call_user_func_array($function, $args);
    $conf[$name] = $old;

    return $return;
  }
}
