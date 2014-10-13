<?php

class render_cache_hijack_context_reaction_block extends context_reaction_block {

  /**
   * {@inheritdoc}
   */
  function block_get_blocks_by_region($region) {
    module_load_include('module', 'block', 'block');

    // renderBlockList returns a render array() already,
    // so no need to call _block_get_renderable_array().
    $build = $this->renderBlockList($region);
    if ($this->is_editable_region($region)) {
      $build = $this->editable_region($region, $build);
    }
    return $build;
  }

  /**
   * Support the optional aggressive block list caching.
   *
   * Otherwise system_cron() clears context cache and parent::get_blocks()
   * runs _block_rehash(), which is slow, every 30 minutes.
   *
   * @param string|null $region
   * @param object|null $context
   * @param bool $reset
   *
   * @return array|bool
   */
  function get_blocks($region = NULL, $context = NULL, $reset = FALSE) {
    if (!variable_get('render_cache_cache_block_list', TRUE)) {
      return parent::get_blocks($region, $context, $reset);
    }

    $hash = md5(serialize(array($region, $context)));
    $cid = "render_cache:context_reaction_block:block_list:$hash";

    if (!$reset) {
      $cache = cache_get($cid);
      if (!empty($cache->data)) {
        return $cache->data;
      }
    }

    $blocks = parent::get_blocks($region, $context, $reset);
    cache_set($cid, $blocks);
    return $blocks;
  }

  /**
   * A caching version of block_list() plus _block_get_renderable_array().
   *
   * This function is identical to context_reaction_block::block_list()
   * except that $this->renderBlocks() is called instead of
   * _block_render_blocks() and that the sorting is done earlier.
   *
   * @see context_reaction_block::block_list().
   *
   * @param string $region
   *   The region to get blocks from.
   *
   * @return array
   *   A render array for this region. This render array will only contain
   *   #markup elements.
   */
  protected function renderBlockList($region) {
    module_load_include('module', 'block', 'block');

    $context_blocks = &drupal_static('context_reaction_block_list');;
    $contexts = context_active_contexts();
    if (!isset($context_blocks)) {
      $info = $this->get_blocks();
      $context_blocks = array();
      foreach ($contexts as $context) {
        $options = $this->fetch_from_context($context);
        if (!empty($options['blocks'])) {
          foreach ($options['blocks'] as $context_block) {
            $bid = "{$context_block['module']}-{$context_block['delta']}";
            if (isset($info[$bid])) {
              $block = (object) array_merge((array) $info[$bid], $context_block);
              $block->context = $context->name;
              $block->title = isset($info[$block->bid]->title) ? $info[$block->bid]->title : NULL;
              $block->cache = isset($info[$block->bid]->cache) ? $info[$block->bid]->cache : DRUPAL_NO_CACHE;
              $context_blocks[$block->region][$block->bid] = $block;
            }
          }
        }
      }

      $this->is_editable_check($context_blocks);
      global $theme;
      $active_regions = $this->system_region_list($theme);
      foreach ($context_blocks as $r => $blocks) {
        //only render blocks in an active region
        if (array_key_exists($r, $active_regions)) {
          // Sort blocks.
          uasort($blocks, array('context_reaction_block', 'block_sort'));
          $context_blocks[$r] = $this->renderBlocks($blocks, $r);

          // Make blocks editable if allowed.
          if ($this->is_editable_region($r)) {
            foreach ($context_blocks[$r] as $key => $block) {
              $context_blocks[$r][$key] = $this->editable_block($block);
            }
          }
        }
      }
    }
    return isset($context_blocks[$region]) ? $context_blocks[$region] : array();
  }

  protected function renderBlocks($context_blocks, $region) {
    // Save the region to the $context.
    $context = array(
      'region' => $region,
    );

    // Convert the blocks array into the right format.
    $blocks = array();
    foreach ($context_blocks as $key => $block) {
      $blocks["{$block->module}_{$block->delta}"] = $block;
    }

    // Delegate to render cache controller.
    $rcc = render_cache_get_controller('block');
    $rcc->setContext($context);
    $build = $rcc->view($blocks);

    return $build;
  }
}
