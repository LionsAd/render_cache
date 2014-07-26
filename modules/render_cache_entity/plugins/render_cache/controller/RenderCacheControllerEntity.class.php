<?php

/**
 * RenderCacheController Entity - Provides render caching for entity objects.
 */
class RenderCacheControllerEntity extends RenderCacheControllerBase {
  /**
   * {@inheritdoc}
   */
  protected function isCacheable(array $default_cache_info, array $context) {
    return variable_get('render_cache_' . $this->getType() . '_' . $context['entity_type'] . '_enabled', TRUE)
        && parent::isCacheable($default_cache_info, $context);
  }

  /**
   * {@inheritdoc}
   */
  protected function getCacheContext($object, array $context) {
    // Helper variables.
    $entity = $object;
    $entity_type = $context['entity_type'];

    $context = parent::getCacheContext($object, $context);

    // Setup entity context.
    list($entity_id, $entity_revision_id, $bundle) = entity_extract_ids($entity_type, $entity);
    $context = $context + array(
      'entity_id' => $entity_id,
      'entity_revision_id' => $entity_revision_id,
      'bundle' => !empty($bundle) ? $bundle : $entity_type,
    );

    return $context;
  }

  /**
   * {@inheritdoc}
   */
  protected function getCacheKeys($object, array $context) {
    return array_merge(parent::getCacheKeys($object, $context), array(
      $context['entity_type'],
      $context['view_mode'],
    ));
  }

  /**
   * {@inheritdoc}
   */
  protected function getCacheHash($object, array $context) {
    // Helper variables.
    $entity = $object;

    // Calculate hash to expire cached items automatically.
    $hash = parent::getCacheHash($object, $context);

    $hash['entity_id'] = $context['entity_id'];
    $hash['entity_revision_id'] = $context['entity_revision_id'];
    $hash['bundle'] = $context['bundle'];
    $hash['langcode'] = $context['langcode'];

    // @todo Move to cache tags.
    $hash['modified'] = entity_modified_last($context['entity_type'], $entity);

    return $hash;
  }

  /**
   * {@inheritdoc}
   */
  protected function getCacheTags($object, array $context) {
    // Helper variables.
    $entity_type = $context['entity_type'];
    $entity_id = $context['entity_id'];

    $tags = parent::getCacheTags($object, $context);
    $tags[$entity_type][] = $entity_id;
    $tags[$entity_type . '_view'] = TRUE;

    return $tags;
  }

  /**
   * {@inheritdoc}
   */
  protected function render(array $objects) {
    // Helper variables.
    $entities = $objects;
    $view_mode = $this->context['view_mode'];
    $langcode = $this->context['langcode'];
    $entity_type = $this->context['entity_type'];
    $entity_info = entity_get_info($entity_type);

    // If this is a entity view callback.
    if (isset($entity_info['render cache storage']['callback'])) {
      $build = $entity_info['render cache storage']['callback']($entities, $view_mode, $langcode, $entity_type);
    }
    // Otherwise this is a controller class callback.
    else {
      $page = $this->getPageArgument();
      $build = entity_get_controller($entity_type)->view($entities, $view_mode, $langcode, $page);
    }
    $build = reset($build);

    return $build;
  }

  /**
   * Helper function to retrieve missing $page argument from backtrace.
   *
   * @return bool $page
   *   The page argument from entity_view() if found - NULL otherwise.
   */
  protected function getPageArgument() {
    $page = NULL;

    // We need the $page variable from entity_view() that it does not pass us.
    if (version_compare(PHP_VERSION, '5.4.0') < 0) {
      $backtrace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT);
    }
    else {
      // Get only the stack frames we need (PHP 5.4 only).
      $backtrace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 2);
    }

    // As a safety, do not grab an unexpected arg for $page, check that this
    // was called from entity_view().
    if ($backtrace[2]['function'] === 'entity_view' && isset($backtrace[2]['args'][4])) {
      $page = $backtrace[2]['args'][4];
    }

    return $page;
  }
}
