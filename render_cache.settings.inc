<?php

/**
 * @file
 * Settings file for render_cache with core patch.
 * Using this allows using dynamic assets.
 */

$conf['render_cache_supports_dynamic_assets'] = TRUE;
$conf['drupal_add_js_function'] = 'RenderCache::drupal_add_js';
$conf['drupal_add_css_function'] = 'RenderCache::drupal_add_css';
$conf['drupal_add_library_function'] = 'RenderCache::drupal_add_library';
$conf['drupal_process_attached_function'] = 'RenderCache::drupal_process_attached';
