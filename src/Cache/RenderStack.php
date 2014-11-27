<?php
/**
 * @file
 * Contains \Drupal\render_cache\Cache\RenderStack
 */

namespace Drupal\render_cache\Cache;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Cache\CacheableInterface;
use Drupal\Core\Render\Element;

use Drupal\render_cache\Cache\Cache;
use RenderCache;

/**
 * Defines the RenderStack service.
 *
 * @ingroup cache
 */
class RenderStack implements RenderStackInterface, CacheableInterface {
  /**
   * {@inheritdoc}
   */
  public function getCacheKeys() {
    return array('render_cache', 'foo');
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return array(array('node' => 1));
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return 600;
  }

  /**
   * {@inheritdoc}
   */
  public function isCacheable() {
    return TRUE;
  }

  // ----------------------------

  /**
   * Recursion level of current call stack.
   *
   * @var int
   */
  protected $recursionLevel = 0;

  /**
   * Recursion storage of current call stack.
   *
   * @var array
   */
  protected $recursionStorage = array();

  /**
   * Whether this stack supports dynamic asset adding by overriding
   * drupal_add_* functions via $conf.
   *
   * Default: FALSE
   *
   * @var bool
   */
  protected $supportsDynamicAssets = FALSE;

  /**
   * {@inheritdoc}
   */
  public function increaseRecursion() {
    $this->recursionLevel += 1;
    $this->recursionStorage[$this->recursionLevel] = array();
  }

  /**
   * {@inheritdoc}
   */
  public function decreaseRecursion() {
    $storage = $this->getRecursionStorage();
    unset($this->recursionStorage[$this->recursionLevel]);
    $this->recursionLevel -= 1;
    return $storage;
  }

  /**
   * {@inheritdoc}
   */
  public function isRecursive() {
    return $this->recursionLevel > 0;
  }

  /**
   * {@inheritdoc}
   */
  public function getRecursionLevel() {
    return $this->recursionLevel;
  }

  /**
   * {@inheritdoc}
   */
  public function getRecursionStorage() {
    if (!isset($this->recursionStorage[$this->recursionLevel])) {
      $this->recursionStorage[$this->recursionLevel] = array();
    }
    $storage = $this->recursionStorage[$this->recursionLevel];
    $render = array();

    // Collect the new storage.
    if (!empty($storage)) {
      $render = $this->collectAndRemoveAssets($storage);
      $attached = $this->collectAttached($storage);
      if ($attached) {
        $render['#attached'] = $attached;
      }
      // Cache the work, no need to do it twice.
      $this->recursionStorage[$this->recursionLevel] = $render;
    }

    return $render;
  }

  // ----------------------------

  /**
   * {@inheritdoc}
   */
  public function setRecursionStorage(array $storage) {
    $this->recursionStorage[$this->recursionLevel] = $storage;
  }

  /**
   * {@inheritdoc}
   */
  public function addRecursionStorage(array &$render, $collect_attached = FALSE) {
    $storage = $this->collectAndRemoveAssets($render);
    if ($collect_attached) {
      $storage['#attached'] = $this->collectAttached($render);
    }
    $this->recursionStorage[$this->recursionLevel][] = $storage;

    return $storage;
  }

  /**
   * {@inheritdoc}
   *
   * @codeCoverageIgnore
   */
  public function drupalRender(array &$render) {
    $markup = drupal_render($render);

    // Store and remove recursive storage.
    // for our properties.
    $this->addRecursionStorage($render);

    return $markup;
  }

  /**
   * {@inheritdoc}
   *
   * @codeCoverageIgnore
   */
  public function collectAttached(array $render) {
    return drupal_render_collect_attached($render, TRUE);
  }

  /**
   * Getter/Setter for dynamic asset support.
   *
   * This should only be set to TRUE, when render_cache.settings.inc is
   * included in settings.php, which will set
   * $conf['render_cache_supports_dynamic_assets'] = TRUE.
   *
   * This is needed to determine if its necessary to collect assets before
   * setting the cache ourselves or if they have been added to the stacks
   * recursive storage.
   *
   * Note: This settings include will only work with a core patch for now.
   *
   * @param bool $supportsDynamicAssets
   *   If this isset the stack state will be changed to this.
   *
   * @return bool
   *   Whether or not dynamic assets are supported.
   */
  public function supportsDynamicAssets($supportsDynamicAssets = NULL) {
    if (isset($supportsDynamicAssets)) {
      $this->supportsDynamicAssets = $supportsDynamicAssets;
    }

    return $this->supportsDynamicAssets;
  }

  /**
   * {@inheritdoc}
   */
  public function render(array &$render) {
    $this->increaseRecursion();

    // Push our recursive stored storage on the stack first.
    if (!empty($render['x_render_cache_recursion_storage'])) {
      $storage = $render['x_render_cache_recursion_storage'];
      unset($render['x_render_cache_recursion_storage']);
      $this->addRecursionStorage($storage, TRUE);
    }

    $markup = $this->drupalRender($render);

    // In case the dynamic assets have not been processed via our
    // drupal_process_attached, we need to collect them ourselves.
    if (!$this->supportsDynamicAssets()) {
      $storage = array();
      $storage['#attached'] = $this->collectAttached($render);
      $this->addRecursionStorage($storage, TRUE);
    }

    $storage = $this->decreaseRecursion();

    $original_render = $render;

    $render = $storage;
    $render['#markup'] = &$markup;

    return array($markup, $original_render);
  }

  public function collectAndRemoveAssets(&$element, $recursive = FALSE) {
    $assets = $this->collectAndRemoveD8Properties($element);

    $assets['#cache']['tags'] = isset($assets['#cache']['tags']) ? $assets['#cache']['tags'] : array();
    $assets['#cache']['max-age'] = isset($assets['#cache']['max-age']) ? $assets['#cache']['max-age'] : array();
    $assets['#cache']['downstream-ttl'] = isset($assets['#cache']['downstream-ttl']) ? $assets['#cache']['downstream-ttl'] : array();
    $assets['#post_render_cache'] = isset($assets['#post_render_cache']) ? $assets['#post_render_cache'] : array();

    if (!is_array($assets['#cache']['max-age'])) {
      $assets['#cache']['max-age'] = array($assets['#cache']['max-age']);
    }
    if (!is_array($assets['#cache']['downstream-ttl'])) {
      $assets['#cache']['downstream-ttl'] = array($assets['#cache']['downstream-ttl']);
    }

    // Get the children of the element, sorted by weight.
    $children = Element::children($element, TRUE);

    foreach ($children as $key) {
      $new_assets = $this->collectAndRemoveAssets($element[$key], TRUE);
      $assets['#cache']['tags'] = Cache::mergeTags($assets['#cache']['tags'], $new_assets['#cache']['tags']);
      $assets['#cache']['max-age'] = NestedArray::mergeDeep($assets['#cache']['max-age'], $new_assets['#cache']['max-age']);
      $assets['#cache']['downstream-ttl'] = NestedArray::mergeDeep($assets['#cache']['downstream-ttl'], $new_assets['#cache']['downstream-ttl']);
      $assets['#post_render_cache'] = NestedArray::mergeDeep($assets['#post_render_cache'], $new_assets['#post_render_cache']);
    }

    if (!$recursive) {
      // Ensure that there are no empty properties.
      if (empty($assets['#cache']['tags'])) {
        unset($assets['#cache']['tags']);
      }
      if (empty($assets['#cache']['max-age'])) {
        unset($assets['#cache']['max-age']);
      }
      if (empty($assets['#cache']['downstream-ttl'])) {
        unset($assets['#cache']['downstream-ttl']);
      }
      // Ensure the cache property is empty.
      if (empty($assets['#cache'])) {
        unset($assets['#cache']);
      }
      if (empty($assets['#post_render_cache'])) {
        unset($assets['#post_render_cache']);
      }
    }

    return $assets;
  }

  public function collectAndRemoveD8Properties(&$element) {
    $render = array();

    if (!empty($element['#cache']['tags'])) {
      $render['#cache']['tags'] = $element['#cache']['tags'];
      unset($element['#cache']['tags']);
    }
    if (!empty($element['#cache']['max-age'])) {
      $render['#cache']['max-age'] = $element['#cache']['max-age'];
      unset($element['#cache']['max-age']);
    }
    if (!empty($element['#cache']['downstream-ttl'])) {
      $render['#cache']['downstream-ttl'] = $element['#cache']['downstream-ttl'];
      unset($element['#cache']['downstream-ttl']);
    }

    // Ensure the cache property is empty.
    if (empty($element['#cache'])) {
      unset($element['#cache']);
    }

    if (!empty($element['#post_render_cache'])) {
      $render['#post_render_cache'] = $element['#post_render_cache'];
      unset($element['#post_render_cache']);
    }

    return $render;
  }


  /**
   * {@inheritdoc}
   */
  public function convertRenderArrayToD7($render) {
    $render['#attached']['render_cache'] = $this->collectAndRemoveD8Properties($render);

    return $render;
  }

  /**
   * {@inheritdoc}
   */
  public function convertRenderArrayFromD7($render) {
    if (!empty($render['#attached']['render_cache'])) {
      $render += $render['#attached']['render_cache'];
      unset($render['#attached']['render_cache']);
    }

    return $render;
  }

  public function processPostRenderCache(&$render, $cache_info) {
    $strategy = $cache_info['render_cache_cache_strategy'];

    // Only when we have rendered to #markup we can post process.
    // @todo Use a #post_render function with a closure instead.
    if ($strategy != RenderCache::RENDER_CACHE_STRATEGY_DIRECT_RENDER) {
      // @todo Log an error.
      return;
    }

    $storage = $this->collectAndRemoveAssets($render);

    while (!empty($storage['#post_render_cache'])) {
      // Save the value and unset from the storage.
      $post_render_cache = $storage['#post_render_cache'];
      unset($storage['#post_render_cache']);

      $this->increaseRecursion();
      // Add the storage back first, so order is preserved.
      $this->addRecursionStorage($storage);

      // Add todo use a helper function.
      foreach (array_keys($post_render_cache) as $callback) {
        foreach ($post_render_cache[$callback] as $context) {
          $render = call_user_func_array($callback, array($render, $context));
        }
      }
      // Get and remove any new storage from the render array.
      $storage = $this->collectAndRemoveAssets($render);
      // ... and push to the stack.
      $this->addRecursionStorage($storage);

      // Now everything is in here.
      $storage = $this->decreaseRecursion();

      // If there is attached on the stack, then merge it to the #attached data we already have.
      if (!empty($storage['#attached'])) {
        $render += array(
          '#attached' => array(),
        ); // @codeCoverageIgnore
        $render['#attached'] = NestedArray::mergeDeep($render['#attached'], $storage['#attached']);
        unset($storage['#attached']);
      }
    }

    // Put the storage back, so it can be pushed to the stack.
    $render = NestedArray::mergeDeep($render, $storage);
  }

  // JS / CSS asset helper functions.
  // -------------------------------------------------------------------

  /**
   * Helper function to add JS/CSS assets to the recursion storage.
   *
   * @param string $type
   *   The type of asset, can be 'js' or 'css'.
   * @param mixed $data
   *   The data to add.
   * @param array|string|NULL $options
   *   The options to add.
   *
   * @return mixed|NULL
   *   What drupal_add_js/drupal_add_css return or NULL when adding something.
   *
   * @see drupal_add_css()
   * @see drupal_add_js()
   */
  public function drupal_add_assets($type, $data = NULL, $options = NULL) {

    // Construct the options when its not an array.
    if (isset($options)) {
      if (!is_array($options)) {
        $options = array('type' => $options);
      }
    }
    else {
      $options = array();
    }

    if (isset($data) && $this->isRecursive()) {
      $new_options = $options;
      $new_options['data'] = $data;
      $storage = array();
      $storage['#attached'][$type][] = $new_options;
      $this->addRecursionStorage($storage, TRUE);
      return;
    }

    return $this->callOriginalFunction("drupal_add_{$type}", $data, $options);
  }

  /**
   * Helper function to add library assets to the recursion storage.
   *
   * @param $module
   *   The name of the module that registered the library.
   * @param $name
   *   The name of the library to add.
   * @param $every_page
   *   Set to TRUE if this library is added to every page on the site. Only items
   *   with the every_page flag set to TRUE can participate in aggregation.
   *
   * @return
   *   TRUE if the library was successfully added; FALSE if the library or one of
   *   its dependencies could not be added.
   *
   * @see drupal_add_library()
   */
  public function drupal_add_library($module, $name, $every_page = NULL) {
    if ($this->isRecursive()) {
      $storage = array();
      $storage['#attached']['library'][] = array($module, $name);
      $this->addRecursionStorage($storage, TRUE);
      // @todo Figure out at runtime if library dependencies are met.
      return TRUE;
    }
    return $this->callOriginalFunction("drupal_add_library", $module, $name, $every_page);
  }

  /**
   * Helper function to add any #attached assets to the recursion storage.
   *
   * @param $elements
   *   The structured array describing the data being rendered.
   * @param $group
   *   The default group of JavaScript and CSS being added. This is only applied
   *   to the stylesheets and JavaScript items that don't have an explicit group
   *   assigned to them.
   * @param $dependency_check
   *   When TRUE, will exit if a given library's dependencies are missing. When
   *   set to FALSE, will continue to add the libraries, even though one or more
   *   dependencies are missing. Defaults to FALSE.
   * @param $every_page
   *   Set to TRUE to indicate that the attachments are added to every page on the
   *   site. Only attachments with the every_page flag set to TRUE can participate
   *   in JavaScript/CSS aggregation.
   *
   * @return
   *   FALSE if there were any missing library dependencies; TRUE if all library
   *   dependencies were met.
   *
   * @see drupal_process_attached()
   */
  public function drupal_process_attached($elements, $group = 0, $dependency_check = FALSE, $every_page = NULL) {
    if ($this->isRecursive()) {
      $storage = array();
      $storage['#attached'] = $elements['#attached'];
      $this->addRecursionStorage($storage, TRUE);
      // @todo Figure out at runtime if library dependencies are met.
      return TRUE;
    }

    return $this->callOriginalFunction("drupal_process_attached", $elements, $group, $dependency_check, $every_page);
  }

  /**
   * This calls the given original function by replacing the global $conf
   * variable, calling the function and putting it back.
   *
   * @param string $function
   *   The function to call, the arguments are gotton via func_get_args().
   * @return NULL|mixed
   *   Returns what the original function returns.
   */
  public function callOriginalFunction($function) {
    global $conf;

    $args = func_get_args();
    array_shift($args);

    $name = $function . "_function";

    $old = $conf[$name];
    unset($conf[$name]);
    $return = call_user_func_array($function, $args);
    $conf[$name] = $old;

    return $return;
  }

}
