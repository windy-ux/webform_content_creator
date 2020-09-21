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
    $elementsDefinitions = \Drupal::service('plugin.manager.webform.element')->getDefinitions();
    $layoutElements = [
      'webform_wizard_page',
      'container',
      'details',
      'fieldset',
      'webform_flexbox',
    ];

    $result = [];
    $webformFieldIds = array_keys($elements);
    // Default value, only used if there are no wizard pages in webform.
    $wizardPage = t('Webform elements');
    // Check which element is the first wizard page (in case it exists)
    $flag = 0;
    $aux = [];
    foreach ($webformFieldIds as $v) {
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

      if (in_array($elements[$v]["#type"], $layoutElements, TRUE)) {
        if ($elements[$v]["#webform_parent_key"] !== '') {
          continue;
        }
        // Executes only for the first wizard page (first optgroup in select)
        if ($flag === 0) {
          $wizardPage = html_entity_decode($title);
          unset($aux);
          $flag++;
          continue;
        }
        if (!empty($aux)) {
          foreach ($aux as $k2 => $v2) {
            $result[$wizardPage][$k2] = $v2;
          }
        }
        $wizardPage = html_entity_decode($title);
        unset($aux);
      }
      // Check if element has not parents.
      elseif ($elements[$v]["#webform_parent_key"] === '') {
        $result['0,' . $v] = html_entity_decode($title) . ' (' . $v . ') - ' . $elementsDefinitions[$elements[$v]["#type"]]['label'];
      }
      // Skip webform sections (not shown in selects)
      elseif ($elements[$v]["#type"] !== "webform_section") {
        $aux['0,' . $v] = html_entity_decode($title) . ' (' . $v . ') - ' . $elementsDefinitions[$elements[$v]["#type"]]['label'];
      }
    }
    // Organize webform elements as a tree (wizard pages as optgroups)
    foreach ($aux as $k2 => $v2) {
      $result[$wizardPage][$k2] = $v2;
    }
    return $result;
  }

  /**
   * Get webform elements and properties structured as a tree.
   *
   * @param string $webformId
   *   Webform id.
   *
   * @return array
   *   Tree with webform elements and basic attributes.
   */
  public static function getWebformElements($webformId) {
    $webform = \Drupal::entityTypeManager()->getStorage(self::WEBFORM)->load($webformId);
    $options = [];
    $submissionStorage = \Drupal::entityTypeManager()->getStorage(self::WEBFORM_SUBMISSION);
    $fieldDefinitions = $submissionStorage->checkFieldDefinitionAccess($webform, $submissionStorage->getFieldDefinitions());
    // Basic webform properties (sid, token, serial number ..)
    foreach ($fieldDefinitions as $key => $fieldDefinition) {
      if (isset($fieldDefinition['type']) && !empty($fieldDefinition['type'])) {
        $options['1,' . $key] = $fieldDefinition['title'] . ' (' . $key . ') - ' . $fieldDefinition['type'];
      }
    }
    // Webform elements.
    $elements = $webform->getElementsInitializedAndFlattened();
    // Webform elements organized in a structured tree.
    $webformOptions = self::buildTree($elements);
    // Join with basic webform properties.
    $webformOptions[t('Webform properties')->render()] = $options;
    return $webformOptions;
  }

  /**
   * Return array with all webform elements types.
   *
   * @param mixed $webformId
   *   Webform id.
   *
   * @return array
   *   Webform basic attributes and element types
   */
  public static function getWebformElementsTypes($webformId) {
    if (!isset($webformId) || empty($webformId)) {
      return NULL;
    }

    // Get webform entity.
    $webform = \Drupal::entityTypeManager()->getStorage(self::WEBFORM)->load($webformId);
    if (empty($webform)) {
      return NULL;
    }

    // Get webform submission storage.
    $submissionStorage = \Drupal::entityTypeManager()->getStorage(self::WEBFORM_SUBMISSION);
    $submissionStorageDefinitions = $submissionStorage->getFieldDefinitions();
    if (empty($submissionStorageDefinitions)) {
      return NULL;
    }

    // Get webform basic attributes definitions.
    $fieldDefinitions = $submissionStorage->checkFieldDefinitionAccess($webform, $submissionStorageDefinitions);
    if (empty($fieldDefinitions)) {
      return NULL;
    }

    // Get webform elements and merge with the webform basic attributes.
    $elements = $webform->getElementsInitializedAndFlattened();
    if (is_array($elements)) {
      $webformFieldIds = array_keys($elements);
      foreach ($webformFieldIds as $v) {
        if (!isset($elements[$v]) || empty($elements[$v])) {
          continue;
        }
        $fieldDefinitions[$v] = $elements[$v]['#type'];
      }
    }
    return $fieldDefinitions;
  }

  /**
   * Return the content type fields.
   *
   * @param Drupal\node\Entity\NodeType $contentType
   *   Content type entity.
   *
   * @return array
   *   Content type fields
   */
  public static function contentTypeFields(NodeType $contentType) {
    $entityManager = \Drupal::service(self::ENTITY_MANAGER);
    $fields = [];

    if (!empty($contentType)) {
      $fields = $entityManager->getFieldDefinitions('node', $contentType->getOriginalId());
    }
    return $fields;
  }

  /**
   * Get content type fields, except the basic fields from node type entity.
   *
   * @param Drupal\node\Entity\NodeType $contentType
   *   Content type entity.
   *
   * @return array
   *   Associative array Content type fields
   */
  public static function getContentFieldsIds(NodeType $contentType) {
    $nodeFields = self::contentTypeFields($contentType);
    $nodeFieldIds = array_keys($nodeFields);
    return array_filter($nodeFieldIds, function ($fid) {
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
    $contentTypes = self::getAllContentTypes();
    $contentTypesFormatted = [];
    foreach ($contentTypes as $k => $v) {
      $contentTypesFormatted[$k] = $v->label();
    }
    return $contentTypesFormatted;
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
    $webformsFormatted = [];
    foreach ($webforms as $k => $v) {
      $category = $v->get('category');
      if (empty($category)) {
        $webformsFormatted[$k] = $v->label();
      }
      else {
        $webformsFormatted[$category][$k] = $v->label();
      }
    }

    return $webformsFormatted;
  }

  /**
   * Get an associative array with encryption profiles and respective labels.
   *
   * @return array
   *   Associative array with encryption profiles ids and labels.
   */
  public static function getFormattedEncryptionProfiles() {
    $encryptionProfiles = [];
    $moduleHandler = \Drupal::service('module_handler');
    if ($moduleHandler->moduleExists('encrypt')) {
      $encryptionProfiles = \Drupal::service(self::ENTITY_TYPE_MANAGER)->getStorage('encryption_profile')->loadMultiple();
    }
    $encryptionProfilesFormatted = [];
    foreach ($encryptionProfiles as $k => $v) {
      $encryptionProfilesFormatted[$k] = $v->label();
    }
    return $encryptionProfilesFormatted;
  }

  /**
   * Get decrypted value.
   *
   * @param string $value
   *   Encrypted value.
   * @param string $encryptionProfile
   *   Encryption profile.
   *
   * @return string
   *   Decrypted value
   */
  public static function getDecryptedValue($value, $encryptionProfile) {
    $decValue = FALSE;
    if (empty($value) || empty($encryptionProfile)) {
      return '';
    }
    if (\Drupal::service('module_handler')->moduleExists('encrypt')) {
      $decValue = \Drupal::service('encryption')->decrypt($value, $encryptionProfile);
    }
    if ($decValue === FALSE) {
      $decValue = $value;
    }
    return $decValue;
  }

  /**
   * Get values inside text with tokens.
   *
   * @param string $value
   *   String with tokens.
   * @param string $encryptionProfile
   *   Encryption profile.
   * @param \Drupal\webform\WebformSubmissionInterface $webformSubmission
   *   Webform submission.
   * @param string $type
   *   Token type.
   *
   * @return string
   *   Token value.
   */
  public static function getTokenValue($value, $encryptionProfile, WebformSubmissionInterface $webformSubmission, $type = self::WEBFORM_SUBMISSION) {
    if (empty($value) || empty($webformSubmission)) {
      return '';
    }
    // Get tokens in string.
    $tokens = \Drupal::token()->scan($value);
    $tokenKeys = [];
    $tokenValues = [];
    if (empty($tokens)) {
      return $value;
    }
    foreach ($tokens[$type] as $val) {
      $tokenValue = \Drupal::token()->replace($val, [self::WEBFORM_SUBMISSION => $webformSubmission]);
      if (!empty($encryptionProfile)) {
        // Decrypt single token value.
        $tokenValue = self::getDecryptedValue($tokenValue, $encryptionProfile);
      }
      $tokenKeys[] = $val;
      $tokenValues[] = $tokenValue;
    }
    if (empty($tokenValues)) {
      return $value;
    }
    // Replace all token values in string.
    return str_replace($tokenKeys, $tokenValues, $value);
  }

}
