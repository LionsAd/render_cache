<?php
/**
 * @file
 * Contains interface and implementation of a render cache controller that supports recursion.
 */

/**
 * Interface to describe how RenderCache controller plugin objects supporting
 * recursion are implemented.
 */
interface RenderCacheControllerRecursionInterface {
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

/**
 * RenderCacheController recursion base class.
 */
abstract class RenderCacheControllerRecursionBase extends RenderCacheControllerBase implements RenderCacheControllerRecursionInterface {

  /**
   * {@inheritdoc}
   */
  public function recursionStep(array &$build) {
    $storage = $this->decreaseRecursion();
    if (!empty($build)) {
      $build['x_render_cache_recursion_storage'] = $storage;
    }
    $this->increaseRecursion();
  }

  /**
   * {@inheritdoc}
   */
  protected function renderRecursive(array $objects) {
    // This provides an optimized version for rendering in a
    // recursive way.
    //
    // @see RenderCacheControllerBase::renderRecursive()

    // Store the render cache controller within the objects.
    foreach ($objects as $object) {
      if (is_object($object)) {
        $object->render_cache_controller = $this;
      }
    }

    // Increase recursion for the first step.
    $this->increaseRecursion();

    // Now build the objects, the implementing class
    // is responsible to call recursionStep()
    // after each object has been individually built.
    $build = $this->render($objects);

    // Decrease recursion as the last step.
    $this->decreaseRecursion();

    return $build;
  }
}
