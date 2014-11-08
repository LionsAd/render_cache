<?php
/**
 * @file
 * Contains RenderCache
 */

use Drupal\render_cache\Controller\BaseController;
use Drupal\render_cache\DependencyInjection\CachedContainerBuilder;
use Drupal\render_cache\DependencyInjection\ServiceProviderPluginManager;

/**
 * Static Service Container wrapper.
 *
 * Generally, code in Drupal should accept its dependencies via either
 * constructor injection or setter method injection. However, there are cases,
 * particularly in legacy procedural code, where that is infeasible. This
 * class acts as a unified global accessor to arbitrary services within the
 * system in order to ease the transition from procedural code to injected OO
 * code.
 *
 */
class RenderCache {

  /**
   * The currently active container object.
   *
   * @var \Drupal\RenderCache\DependencyInjection\ContainerInterface
   */
  protected static $container;

  /**
   * Initializes the container.
   *
   * This can be safely called from hook_boot() because the container will
   * only be build if we have reached the DRUPAL_BOOTSTRAP_FULL phase.
   *
   * @return bool
   *   TRUE when the container was initialized, FALSE otherwise.
   */
  public static function init() {
    // If this is set already, just return.
    if (isset(static::$container)) {
      return TRUE;
    }

    $service_provider_manager = new ServiceProviderPluginManager();
    // This is an internal API, but we need the cache object.
    $cache = _cache_get_object('cache');

    $container_builder = new CachedContainerBuilder($service_provider_manager, $cache);

    if ($container_builder->isCached()) {
      static::$container = $container_builder->compile();
      return TRUE;
    }

    // If we have not yet fully bootstrapped, we can't build the container.
    if (drupal_bootstrap(NULL, FALSE) != DRUPAL_BOOTSTRAP_FULL) {
      return FALSE;
    }

    // Rebuild the container.
    static::$container = $container_builder->compile();

    return (bool) static::$container;
  }

  /**
   * Returns the currently active global container.
   *
   * @deprecated This method is only useful for the testing environment. It
   * should not be used otherwise.
   *
   * @return \Drupal\render_cache\DependencyInjection\ContainerInterface
   */
  public static function getContainer() {
    return static::$container;
  }

  /**
   * Retrieves a service from the container.
   *
   * Use this method if the desired service is not one of those with a dedicated
   * accessor method below. If it is listed below, those methods are preferred
   * as they can return useful type hints.
   *
   * @param string $id
   *   The ID of the service to retrieve.
   * @return mixed
   *   The specified service.
   */
  public static function service($id) {
    return static::$container->get($id);
  }

  /**
   * Indicates if a service is defined in the container.
   *
   * @param string $id
   *   The ID of the service to check.
   *
   * @return bool
   *   TRUE if the specified service exists, FALSE otherwise.
   */
  public static function hasService($id) {
    return static::$container && static::$container->has($id);
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
    return static::$container->get('render_stack')->drupalRender($render);
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
    return static::$container->get('render_stack')->isRecursive();
  }
}
