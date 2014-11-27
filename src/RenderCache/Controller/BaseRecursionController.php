<?php
/**
 * @file
 * Contains \Drupal\render_cache\RenderCache\Controller\BaseRecursionController
 */

namespace Drupal\render_cache\RenderCache\Controller;

/**
 * Base class for RecursionController plugin objects.
 *
 * Used for render cache controllers that support recursion.
 *
 * @ingroup rendercache
 */
abstract class BaseRecursionController extends BaseController implements RecursionControllerInterface {

  /**
   * {@inheritdoc}
   */
  public function recursionStep(array &$build) {
    $storage = $this->renderStack->decreaseRecursion();
    if (!empty($build)) {
      $build['x_render_cache_recursion_storage'] = $storage;
    }
    $this->renderStack->increaseRecursion();
  }

  /**
   * {@inheritdoc}
   */
  protected function renderRecursive(array $objects) {
    // This provides an optimized version for rendering in a
    // recursive way.
    //
    // @see BaseController::renderRecursive()

    // Store the render cache controller within the objects.
    foreach ($objects as $object) {
      if (is_object($object)) {
        $object->render_cache_controller = $this;
      }
    }

    // Increase recursion for the first step.
    $this->renderStack->increaseRecursion();

    // Now build the objects, the implementing class
    // is responsible to call recursionStep()
    // after each object has been individually built.
    $build = $this->render($objects);

    // Decrease recursion as the last step.
    $this->renderStack->decreaseRecursion();

    // Remove the render cache controller within the objects again.
    foreach ($objects as $object) {
      if (is_object($object)) {
        unset($object->render_cache_controller);
      }
    }

    return $build;
  }
}
