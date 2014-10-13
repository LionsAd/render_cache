<?php

/**
 * Interface to describe how RenderCache validator plugin objects are implemented.
 */
interface RenderCacheValidationStrategyInterface {
  public function validate(array $objects);
  public function generate(array $objects);
}

/**
 * Base class for RenderCacheRenderCacheValidationStrategy plugin objects.
 */
abstract class RenderCacheValidationStrategyBase extends RenderCachePluginBase implements RenderCacheValidationStrategyInterface {
}
