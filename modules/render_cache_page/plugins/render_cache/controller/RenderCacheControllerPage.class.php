<?php

/**
 * Special interface for the render cache page controller.
 */
interface RenderCacheControllerPageInterface {
  /**
   * Implements delegated hook_init().
   */
  public function hook_init();
}

/**
 * RenderCacheController Entity - Provides render caching for entity objects.
 */
class RenderCacheControllerPage extends RenderCacheControllerBase implements RenderCacheControllerPageInterface {
  /**
   * {@inheritdoc}
   */
  public function hook_init() {
    // We need to increase the recursion level before entering here to avoid
    // early rendering of #post_render_cache data.
    $this->increaseRecursion();
  }

  /**
   * {@inheritdoc}
   */
  public function view(array $objects) {
    // We need to decrease recursion again.
    // Because this only adds to the recursion storage, it is safe to call.
    $this->decreaseRecursion();

    return parent::view($objects);
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
    // Same as in D8, the page has a content tag.
    $tags['content'] = TRUE;

    return $tags;
  }

  /**
   * {@inheritdoc}
   */
  protected function render(array $objects) {
    foreach ($objects as $id => $page) {
      $build[$id] = render_cache_page_drupal_render_page_helper($page);
    }

    return $build;
  }
}
