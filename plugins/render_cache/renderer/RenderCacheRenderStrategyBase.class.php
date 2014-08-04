<?php
/**
 * @file
 * Contains implementation of Render Cache Render Strategy base class.
 */

/**
 * Interface to describe how RenderCache renderer plugin objects are implemented.
 */
interface RenderCacheRenderStrategyInterface {
  public function render(array $placeholders);
}

/**
 * Base class for RenderCacheRenderCacheValidationStrategy plugin objects.
 */
abstract class RenderCacheRenderStrategyBase extends RenderCachePluginInterface implements RenderCacheRenderStrategyInterface {

}
