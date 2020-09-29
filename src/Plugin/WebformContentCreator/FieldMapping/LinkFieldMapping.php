<?php

namespace Drupal\webform_content_creator\Plugin\WebformContentCreator\FieldMapping;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\webform_content_creator\Plugin\FieldMappingBase;

/**
 * Provides link field mapping.
 *
 * @WebformContentCreatorFieldMapping(
 *   id = "link_mapping",
 *   label = @Translation("Link"),
 *   weight = 0,
 *   field_types = {
 *     "link"
 *   },
 * )
 */
class LinkFieldMapping extends FieldMappingBase {

  public function getSupportedWebformFields($webform_id) {
    $supported_types = ["url"];

    return $this->filterWebformFields($webform_id, $supported_types);
  }

  public function mapEntityField(ContentEntityInterface &$content, array $webform_element, array $data = [], FieldDefinitionInterface $field_definition) {
    $field_id = $field_definition->getName();
    $field_value = $data[$field_id];

    $content->set($field_id, $field_value);
  }

}
