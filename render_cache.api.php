<?php

/**
 * Hook to alter the entity hash.
 *
 * @param array $hash
 * @param object $entity
 * @param array $cache_info
 * @param array $context
 */
function hook_render_cache_entity_hash_alter(&$hash, $entity, $cache_info, $context) {
  // @todo Add example implementation.
}

/**
 * Hook to alter the cid.
 *
 * @param array $cid_parts
 * @param array $block
 * @param array $cache_info
 * @param array $context
 */
function hook_render_cache_block_cid_alter(&$cid_parts, $block, $cache_info, $context) {
  // @todo Add example implementation.
}

/**
 * @param array $cache_info
 * @param array $block
 * @param array $context
 */
function hook_render_cache_block_cache_info_alter(&$cache_info, $block, $context) {
  // @todo Add example implementation.
}

/**
 * @param array $cache_info_default
 * @param array $default_alter_context
 */
function hook_render_cache_block_default_cache_info_alter(&$cache_info_default, $default_alter_context) {
  // @todo Add example implementation.
}
