<?php

namespace Drupal\webform_content_creator;

use Drupal\node\Entity\NodeType;
use Drupal\webform\WebformSubmissionInterface;

/**
 * Provides useful functions required in Webform content creator module.
 */
class WebformContentCreatorUtilities {

  const WEBFORM = 'webform';

  const WEBFORM_SUBMISSION = 'webform_submission';

  const ENTITY_TYPE_MANAGER = 'entity_type.manager';

  const ENTITY_MANAGER = 'entity_field.manager';

  const CONTENT_BASIC_FIELDS = ['body', 'status', 'uid'];

  /**
   * Function to check whether an Webform content creator entity exists.
   *
   * @param string $id
   *   Webform Content Creator id.
   *
   * @return bool
   *   True, if the entity already exists.
   */
  public static function existsWebformContentCreatorEntity($id) {
    $entity = \Drupal::entityQuery('webform_content_creator')
      ->condition('id', $id)
      ->execute();
    return (bool) $entity;
  }

  /**
   * Contructs a tree with webform elements which can be used in Selects.
   *
   * @param array $elements
   *   Webform elements.
   *
   * @return array
   *   Tree with webform elements
   */
  private static function buildTree(array $elements) {
    $definitions = \Drupal::service('plugin.manager.webform.element')->getDefinitions();
    $layout_elements = [
      'webform_wizard_page',
      'container',
      'details',
      'fieldset',
      'webform_flexbox',
    ];

    $result = [];
    $webform_field_ids = array_keys($elements);
    // Default value, only used if there are no wizard pages in webform.
    $wizardPage = t('Webform elements');
    // Check which element is the first wizard page (in case it exists)
    $flag = 0;
    $aux = [];
    foreach ($webform_field_ids as $v) {
      if ($v === 'actions') {
        continue;
      }
      $title = 'Section';
      if (isset($elements[$v]['#title'])) {
        $title = $elements[$v]['#title'];
      }
      else {
        if (isset($elements[$v]['#markup'])) {
          $title = $elements[$v]['#markup'];
        }
      }

      if (in_array($elements[$v]["#type"], $layout_elements, TRUE)) {
        if ($elements[$v]["#webform_parent_key"] !== '') {
          continue;
        }
        // Executes only for the first wizard page (first optgroup in select)
        if ($flag === 0) {
          $wizard_page = html_entity_decode($title);
          unset($aux);
          $flag++;
          continue;
        }
        if (!empty($aux)) {
          foreach ($aux as $k2 => $v2) {
            $result[$wizard_page][$k2] = $v2;
          }
        }
        $wizard_page = html_entity_decode($title);
        unset($aux);
      }
      // Check if element has not parents.
      elseif ($elements[$v]["#webform_parent_key"] === '') {
        $result['0,' . $v] = html_entity_decode($title) . ' (' . $v . ') - ' . $definitions[$elements[$v]["#type"]]['label'];
      }
      // Skip webform sections (not shown in selects)
      elseif ($elements[$v]["#type"] !== "webform_section") {
        $aux['0,' . $v] = html_entity_decode($title) . ' (' . $v . ') - ' . $definitions[$elements[$v]["#type"]]['label'];
      }
    }
    // Organize webform elements as a tree (wizard pages as optgroups)
    foreach ($aux as $k2 => $v2) {
      $result[$wizard_page][$k2] = $v2;
    }
    return $result;
  }

  /**
   * Get webform elements and properties structured as a tree.
   *
   * @param string $webform_id
   *   Webform id.
   *
   * @return array
   *   Tree with webform elements and basic attributes.
   */
  public static function getWebformElements($webform_id) {
    $webform = \Drupal::entityTypeManager()->getStorage(self::WEBFORM)->load($webform_id);
    $options = [];
    $submission_storage = \Drupal::entityTypeManager()->getStorage(self::WEBFORM_SUBMISSION);
    $field_definitions = $submission_storage->checkFieldDefinitionAccess($webform, $submission_storage->getFieldDefinitions());
    // Basic webform properties (sid, token, serial number ..)
    foreach ($field_definitions as $key => $field_definition) {
      if (isset($field_definition['type']) && !empty($field_definition['type'])) {
        $options['1,' . $key] = $field_definition['title'] . ' (' . $key . ') - ' . $field_definition['type'];
      }
    }
    // Webform elements.
    $elements = $webform->getElementsInitializedAndFlattened();
    // Webform elements organized in a structured tree.
    $result = self::buildTree($elements);
    // Join with basic webform properties.
    $result[t('Webform properties')->render()] = $options;
    return $result;
  }

  /**
   * Return array with all webform elements types.
   *
   * @param mixed $webform_id
   *   Webform id.
   *
   * @return array
   *   Webform basic attributes and element types
   */
  public static function getWebformElementsTypes($webform_id) {
    if (!isset($webform_id) || empty($webform_id)) {
      return NULL;
    }

    // Get webform entity.
    $webform = \Drupal::entityTypeManager()->getStorage(self::WEBFORM)->load($webform_id);
    if (empty($webform)) {
      return NULL;
    }

    // Get webform submission storage.
    $submission_storage = \Drupal::entityTypeManager()->getStorage(self::WEBFORM_SUBMISSION);
    $submission_storage_definitions = $submission_storage->getFieldDefinitions();
    if (empty($submission_storage_definitions)) {
      return NULL;
    }

    // Get webform basic attributes definitions.
    $result = $submission_storage->checkFieldDefinitionAccess($webform, $submission_storage_definitions);
    if (empty($result)) {
      return NULL;
    }

    // Get webform elements and merge with the webform basic attributes.
    $elements = $webform->getElementsInitializedAndFlattened();
    if (is_array($elements)) {
      $webform_field_ids = array_keys($elements);
      foreach ($webform_field_ids as $v) {
        if (!isset($elements[$v]) || empty($elements[$v])) {
          continue;
        }
        $result[$v] = $elements[$v]['#type'];
      }
    }
    return $result;
  }

  /**
   * Return the content type fields.
   *
   * @param Drupal\node\Entity\NodeType $content_type
   *   Content type entity.
   *
   * @return array
   *   Content type fields
   */
  public static function contentTypeFields(NodeType $content_type) {
    $entity_manager = \Drupal::service(self::ENTITY_MANAGER);
    $fields = [];

    if (!empty($content_type)) {
      $fields = $entity_manager->getFieldDefinitions('node', $content_type->getOriginalId());
    }
    return $fields;
  }

  /**
   * Get content type fields, except the basic fields from node type entity.
   *
   * @param Drupal\node\Entity\NodeType $content_type
   *   Content type entity.
   *
   * @return array
   *   Associative array Content type fields
   */
  public static function getContentFieldsIds(NodeType $content_type) {
    $node_fields = self::contentTypeFields($content_type);
    $ids = array_keys($node_fields);
    return array_filter($ids, function ($fid) {
      return strpos($fid, 'field_') === 0 || in_array($fid, self::CONTENT_BASIC_FIELDS);
    });
  }

  /**
   * Get all content type ids.
   *
   * @return array
   *   Array with all content type ids.
   */
  public static function getAllContentTypeIds() {
    return \Drupal::service(self::ENTITY_TYPE_MANAGER)->getStorage('node_type')->getQuery()->execute();
  }

  /**
   * Get all content type entities.
   *
   * @return array
   *   All content type entities.
   */
  public static function getAllContentTypes() {
    $ids = self::getAllContentTypeIds();
    return \Drupal::entityTypeManager()->getStorage('node_type')->loadMultiple(array_keys($ids));
  }

  /**
   * Get an associative array with content type ids and respective labels.
   *
   * @return array
   *   Associative array with content type ids and labels.
   */
  public static function getFormattedContentTypes() {
    $content_types = self::getAllContentTypes();
    $result = [];
    foreach ($content_types as $k => $v) {
      $result[$k] = $v->label();
    }
    return $result;
  }

  /**
   * Get all webform ids.
   *
   * @return array
   *   Array with all webform ids.
   */
  public static function getAllWebformIds() {
    $ids = \Drupal::entityTypeManager()->getStorage(self::WEBFORM)->getQuery()->condition('template', FALSE)->execute();
    return $ids;
  }

  /**
   * Get all webform entities.
   *
   * @return array
   *   All webform entities.
   */
  public static function getAllWebforms() {
    $ids = self::getAllWebformIds();
    $webforms = \Drupal::entityTypeManager()->getStorage(self::WEBFORM)->loadMultiple(array_keys($ids));
    return $webforms;
  }

  /**
   * Get an associative array with webform ids and respective labels.
   *
   * @return array
   *   Associative array with webform ids and labels.
   */
  public static function getFormattedWebforms() {
    $webforms = self::getAllWebforms();
    $result = [];
    foreach ($webforms as $k => $v) {
      $category = $v->get('category');
      if (empty($category)) {
        $result[$k] = $v->label();
      }
      else {
        $result[$category][$k] = $v->label();
      }
    }

    return $result;
  }

  /**
   * Get an associative array with encryption profiles and respective labels.
   *
   * @return array
   *   Associative array with encryption profiles ids and labels.
   */
  public static function getFormattedEncryptionProfiles() {
    $profiles = [];
    $module_handler = \Drupal::service('module_handler');
    if ($module_handler->moduleExists('encrypt')) {
      $profiles = \Drupal::service(self::ENTITY_TYPE_MANAGER)->getStorage('encryption_profile')->loadMultiple();
    }
    $result = [];
    foreach ($profiles as $k => $v) {
      $result[$k] = $v->label();
    }
    return $result;
  }

  /**
   * Get decrypted value.
   *
   * @param string $value
   *   Encrypted value.
   * @param string $encryption_profile
   *   Encryption profile.
   *
   * @return string
   *   Decrypted value
   */
  public static function getDecryptedValue($value, $encryption_profile) {
    $result = FALSE;
    if (empty($value) || empty($encryption_profile)) {
      return '';
    }
    if (\Drupal::service('module_handler')->moduleExists('encrypt')) {
      $result = \Drupal::service('encryption')->decrypt($value, $encryption_profile);
    }
    if ($result === FALSE) {
      $result = $value;
    }
    return $result;
  }

  /**
   * Get values inside text with tokens.
   *
   * @param string $value
   *   String with tokens.
   * @param string $encryption_profile
   *   Encryption profile.
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *   Webform submission.
   * @param string $type
   *   Token type.
   *
   * @return string
   *   Token value.
   */
  public static function getTokenValue($value, $encryption_profile, WebformSubmissionInterface $webform_submission, $type = self::WEBFORM_SUBMISSION) {
    if (empty($value) || empty($webform_submission)) {
      return '';
    }
    // Get tokens in string.
    $tokens = \Drupal::token()->scan($value);
    $token_keys = [];
    $token_values = [];
    if (empty($tokens)) {
      return $value;
    }
    foreach ($tokens[$type] as $val) {
      $token_value = \Drupal::token()->replace($val, [self::WEBFORM_SUBMISSION => $webform_submission]);
      if (!empty($encryption_profile)) {
        // Decrypt single token value.
        $token_value = self::getDecryptedValue($token_value, $encryption_profile);
      }
      $token_keys[] = $val;
      $token_values[] = $token_value;
    }
    if (empty($token_values)) {
      return $value;
    }
    // Replace all token values in string.
    return str_replace($token_keys, $token_values, $value);
  }

}
