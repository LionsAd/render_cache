<?php

/**
 * @param array $build
 *
 * @see render_cache_page_drupal_render_page()
 */
function hook_render_cache_page_pre_render_alter(&$build) {

  // @todo Add example implementation.
}

/**
 * @param string $output
 * @param array $build
 *
 * @see render_cache_page_drupal_render_page()
 */
function hook_render_cache_page_post_render_alter(&$output, &$build) {
  // @todo Add example implementation.
}

/**
 * Allows changing the markup before it is send to the browser.
 *
 * @param string $output
 * @param array $build
 *
 * @see render_cache_page_drupal_render_page()
 */
function hook_render_cache_page_pre_send_alter(&$output, &$build) {
  // @todo Add example implementation.
}

/**
 * Allows sending more data after the main page content has been send to the browser.
 *
 * @param string $output
 * @param array $build
 *
 * @see render_cache_page_drupal_render_page()
 */
function hook_render_cache_page_post_send_alter(&$output, &$build) {
  // @todo Add example implementation.
}
