<?php

class render_cache_hijack_views_handler_field_field extends views_handler_field_field {

  /**
   * Override views_handler_field_field::render() to add caching.
   */
  function set_items($values, $row_id) {
    // In some cases the instance on the entity might be easy, see
    // https://drupal.org/node/1161708 and https://drupal.org/node/1461536 for
    // more information.
    if (empty($values->_field_data[$this->field_alias]) || empty($values->_field_data[$this->field_alias]['entity']) || !isset($values->_field_data[$this->field_alias]['entity']->{$this->definition['field_name']})) {
      return array();
    }

    $display = array(
      'type' => $this->options['type'],
      'settings' => $this->options['settings'],
      'label' => 'hidden',
      // Pass the View object in the display so that fields can act on it.
      'views_view' => $this->view,
      'views_field' => $this,
      'views_row_id' => $row_id,
    );


    $entity_type = $values->_field_data[$this->field_alias]['entity_type'];
    $entity = $this->get_value($values, 'entity');
    if (!$entity) {
      return array();
    }

    $langcode = $this->field_language($entity_type, $entity);
    $render_array = render_cache_view_field($entity_type, $entity, $this->definition['field_name'], $display, $langcode);

    $items = array();
    if ($this->options['field_api_classes']) {
      // Make a copy.
      $array = $render_array;
      return array(array('rendered' => drupal_render($render_array)));
    }

    foreach (element_children($render_array) as $count) {
      $items[$count]['rendered'] = $render_array[$count];
      // field_view_field() adds an #access property to the render array that
      // determines whether or not the current user is allowed to view the
      // field in the context of the current entity. We need to respect this
      // parameter when we pull out the children of the field array for
      // rendering.
      if (isset($render_array['#access'])) {
        $items[$count]['rendered']['#access'] = $render_array['#access'];
      }
      // Only add the raw field items (for use in tokens) if the current user
      // has access to view the field content.
      if ((!isset($items[$count]['rendered']['#access']) || $items[$count]['rendered']['#access']) && !empty($render_array['#items'][$count])) {
        $items[$count]['raw'] = $render_array['#items'][$count];
      }
    }
    return $items;
  }
}
