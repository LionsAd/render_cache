<?php
/**
 * @file
 * Contains \Drupal\render_cache\RenderCache\Controller\RecursionControllerInterface
 */

namespace Drupal\render_cache\RenderCache\Controller;

/**
 * Interface to describe how RenderCache controller plugin objects supporting
 * recursion are implemented.
 *
 * @ingroup rendercache
 */
interface RecursionControllerInterface {
  /**
   * Ensures that recursion storage is added to the right cached object.
   *
   * Example: A module can implement hook_block_view_alter() and pass
   *          $block->content to ensure that recursion storage created
   *          during the building of the block is properly added to the block
   *          itself:
   * {@code}
   * function render_cache_block_block_view_alter(&$data, $block) {
   *   if (!empty($block->render_cache_controller) && !empty($data['content'])) {
   *     // Normalize to the drupal_render() structure so we can add something.
   *     if (is_string($data['content'])) {
   *       $data['content'] = array(
   *         '#markup' => $data['#content'],
   *       );
   *     }
   *     $block->render_cache_controller->recursionStep($data['content']);
   *   }
   * }
   * {@endcode}
   *
   * @param $build
   *   The render array to add the recursion storage to when the $build is not
   *   empty.
   */
  public function recursionStep(array &$build);
}


