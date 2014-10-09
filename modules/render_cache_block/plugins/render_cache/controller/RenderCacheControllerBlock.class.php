<?php

/**
 * RenderCacheController Block - Provides render caching for block objects.
 */
class RenderCacheControllerBlock extends RenderCacheControllerRecursionBase {

  /**
   * {@inheritdoc}
   */
  protected function isCacheable(array $default_cache_info, array $context) {
    // Disabled caching for now.
    return variable_get('render_cache_' . $this->getType() . '_' . $context['region'] . '_enabled', TRUE)
        && parent::isCacheable($default_cache_info, $context);
  }

  /**
   * {@inheritdoc}
   */
  protected function getCacheContext($object, array $context) {
    // Helper variables.
    $block = $object;

    $context = parent::getCacheContext($object, $context);

    $context = $context + array(
      'bid' => $block->bid,
      'delta' => $block->delta,
      'module' => $block->module,
    );

    return $context;
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultCacheInfo($context) {
    $default_cache_info = parent::getDefaultCacheInfo($context);

    // The block cache renders to markup by default.
    $default_cache_info['render_cache_render_to_markup'] = TRUE;
    return $default_cache_info;
  }

  /**
   * {@inheritdoc}
   */
  protected function getCacheInfo($object, array $context) {
    // Helper variables.
    $block = $object;

    $cache_info = parent::getCacheInfo($object, $context);

    // Overwrite the default granularity, this is a pretty sane default.
    $cache_info['granularity'] = isset($block->cache) ? $block->cache : DRUPAL_NO_CACHE;

    return $cache_info;
  }


  /**
   * {@inheritdoc}
   */
  protected function getCacheKeys($object, array $context) {
    // Helper variables.
    // @todo Unused variable $block
    $block = $object;

    return array_merge(parent::getCacheKeys($object, $context), array(
      $context['region'],
      $context['module'],
      $context['delta'],
    ));
  }

  /**
   * {@inheritdoc}
   */
  protected function getCacheHash($object, array $context) {
    $hash['module'] = $context['module'];
    $hash['delta'] = $context['delta'];

    // Context module support.
    if (!empty($context['context']->name)) {
      $hash['context'] = $context['context']->name;
    }

    return $hash;
  }

  /**
   * {@inheritdoc}
   */
  protected function getCacheTags($object, array $context) {
    $tags = parent::getCacheTags($object, $context);
    $tags['block'][] = $context['id'];

    return $tags;
  }

  /**
   * {@inheritdoc}
   */
  protected function render(array $objects) {
    // Helper variables.
    $blocks = $objects;

    // Ensure block module is loaded.
    module_load_include('module', 'block', 'block');

    // Turn off core block caching.
    foreach ($blocks as $block) {
      $block->cache = DRUPAL_NO_CACHE;
    }

    // This works because _block_render_blocks uses the same format for the
    // $id of the block in the array.
    $list = _block_render_blocks($blocks);

    $build = array();
    if ($list) {
      $build = _block_get_renderable_array($list);
    }

    return $build;
  }
}
