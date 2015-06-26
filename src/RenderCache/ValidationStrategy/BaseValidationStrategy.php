<?php
/**
 * @file
 * Contains \Drupal\render_cache\RenderCache\ValidationStrategy\BaseValidationStrategy
 */

namespace Drupal\render_cache\RenderCache\ValidationStrategy;

use Drupal\Component\Plugin\PluginBase;

/**
 * Base class for ValidationStrategy plugin objects.
 *
 * @ingroup rendercache
 */
abstract class BaseValidationStrategy extends PluginBase implements ValidationStrategyInterface {
}
