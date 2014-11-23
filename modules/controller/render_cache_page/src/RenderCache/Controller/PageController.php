<?php
/**
 * @file
 * Contains \Drupal\render_cache_page\RenderCache\Controller\PageController
 */

namespace Drupal\render_cache_page\RenderCache\Controller;

use Drupal\render_cache\RenderCache\Controller\BaseController;

/**
 * PageController - Provides render caching for page objects.
 *
 * @ingroup rendercache
 */
class PageController extends BaseController implements PageControllerInterface {

  /**
   * {@inheritdoc}
   */
  public function hook_init() {
    // We need to increase the recursion level before entering here to avoid
    // early rendering of #post_render_cache data.
    $this->renderStack->increaseRecursion();
  }

  /**
   * {@inheritdoc}
   */
  public function view(array $objects) {
    // We need to decrease recursion again.
    // Because this only adds to the recursion storage, it is safe to call.
    foreach ($objects as $id => $page) {
      // Transform into a render array.
      if (!is_array($page->content)) {
        $page->content = array(
          'main' => array(
            '#markup' => $page->content
          ),
        );
      }
      $storage = $this->renderStack->decreaseRecursion();
      $page->content['x_render_cache_page_recursion_storage'] = $storage;
    }

    return parent::view($objects);
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultCacheInfo($context) {
    $default_cache_info = parent::getDefaultCacheInfo($context);

    // The page cache is per page and per role by default.
    $default_cache_info['granularity'] = DRUPAL_CACHE_PER_ROLE | DRUPAL_CACHE_PER_PAGE;
    $default_cache_info['render_cache_cache_strategy'] = \RenderCache::RENDER_CACHE_STRATEGY_DIRECT_RENDER;
    $default_cache_info['render_cache_preserve_original'] = TRUE;
    return $default_cache_info;
  }

  /**
   * {@inheritdoc}
   */
  protected function isCacheable(array $default_cache_info, array $context) {
    // Disabled caching for now.
    return variable_get('render_cache_' . $this->getType() . '_' . $context['page_callback'] . '_enabled', FALSE)
        && parent::isCacheable($default_cache_info, $context);
  }

  /**
   * {@inheritdoc}
   */
  protected function getCacheKeys($object, array $context) {
    return array_merge(parent::getCacheKeys($object, $context), array(
      $context['page_callback'],
    ));
  }

  /**
   * {@inheritdoc}
   */
  protected function getCacheHash($object, array $context) {
    // Simple 1 hour cache to begin with.
    $hash['expiration'] = round(time() / 3600);

    return $hash;
  }

  /**
   * {@inheritdoc}
   */
  protected function getCacheTags($object, array $context) {
    $tags = parent::getCacheTags($object, $context);
    // @see drupal_pre_render_page() in Drupal 8.
    $tags[] = 'theme_global_settings';

    // Deliberately commented out as the theme might not be loaded, yet.
    // We do this in render() instead.
    //global $theme;
    //$tags[] = 'theme:' . $theme;

    return $tags;
  }

  /**
   * {@inheritdoc}
   */
  protected function render(array $objects) {
    foreach ($objects as $id => $page) {
      if ($this->renderStack->supportsDynamicAssets()) {
        $storage = $page->content['x_render_cache_page_recursion_storage'];
        unset($page->content['x_render_cache_page_recursion_storage']);
        $this->renderStack->addRecursionStorage($storage, TRUE);
      }
      $build[$id] = render_cache_page_drupal_render_page_helper($page->content);
    }
    // @see drupal_pre_render_page() in Drupal 8.
    global $theme;
    $page_id = current(array_keys($objects));
    $build[$page_id]['#cache']['tags'][] = 'theme:' . $theme;

    return $build;
  }
}
