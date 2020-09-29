<?php

namespace Drupal\webform_content_creator\Plugin\WebformContentCreator\FieldMapping;

use Drupal\webform_content_creator\Plugin\FieldMappingBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Provides a default field mapping.
 *
 * @WebformContentCreatorFieldMapping(
 *   id = "default_mapping",
 *   label = @Translation("Default"),
 *   weight = 99,
 *   field_types = {},
 * )
 */
class DefaultFieldMapping extends FieldMappingBase {

  /**
   * {@inheritdoc}
   */
  public function mapEntityField(ContentEntityInterface &$content, array $webform_element, array $data = [], FieldDefinitionInterface $field_definition) {
    return parent::mapEntityField($content, $webform_element, $data, $field_definition);
  }

}
