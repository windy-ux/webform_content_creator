<?php

namespace Drupal\webform_content_creator;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface defining an Webform content creator entity.
 */
interface WebformContentCreatorInterface extends ConfigEntityInterface {
  const WEBFORM = 'webform';

  const WEBFORM_CONTENT_CREATOR = 'webform_content_creator';
  
  const FIELD_TITLE = 'field_title';

  const WEBFORM_FIELD = 'webform_field';

  const CUSTOM_CHECK = 'custom_check';

  const CUSTOM_VALUE = 'custom_value';

  const ELEMENTS = 'elements';

  const TYPE = 'type';

  const USE_ENCRYPT = 'use_encrypt';

  const ENCRYPTION_PROFILE = 'encryption_profile';

  /**
   * Returns the entity title.
   *
   * @return string
   *   The entity title.
   */
  public function getTitle();

  /**
   * Returns the entity content type id.
   *
   * @return string
   *   The entity content type.
   */
  public function getContentType();

  /**
   * Returns the entity webform id.
   *
   * @return string
   *   The webform.
   */
  public function getWebform();

  /**
   * Returns the entity attributes as an associative array.
   *
   * @return array
   *   The entity attributes mapping.
   */
  public function getAttributes();

  /**
   * Returns the encryption method.
   *
   * @return boolean
   *   true, when an encryption profile is used. Otherwise, returns false.
   */
  public function getEncryptionCheck();

  /**
   * Returns the encryption profile.
   *
   * @return string
   *   The encryption profile name.
   */
  public function getEncryptionProfile();

  /**
   * Check if a content type entity associated with the Webform content creator entity exists.
   *
   * @return boolean true, if content type entity exists. Otherwise, returns false.
   */
  public function existsContentType();

  /**
   * Check if the content type id (parameter) is equal to the content type id of Webform content creator entity
   *
   * @param string $ct Content type id
   * @return boolean true, if the parameter is equal to the content type id of Webform content creator entity. Otherwise, returns false.
   */
  public function equalsContentType($ct);

  /**
   * Check if the webform id (parameter) is equal to the webform id of Webform content creator entity
   *
   * @param string $webform Webform id
   * @return boolean true, if the parameter is equal to the webform id of Webform content creator entity. Otherwise, returns false.
   */
  public function equalsWebform($webform);

  /**
   * Show a message accordingly to status value, after creating/updating an entity.
   *
   * @param int $status Status int, returned after creating/updating an entity.
   */
  public function statusMessage($status);

  /**
   * Create node from webform submission.
   *
   * @param type $webform_submission Webform submission
   */
  public function createNode($webform_submission);
  
  /**
   * Check if field maximum size is exceeded. 
   * 
   * @param array $fields Content type fields
   * @param string $k Field machine name
   * @param string $decValue Decrypted value
   * @return int 1 if maximum size is exceeded, otherwise return 0.
   */
  public function checkMaxFieldSizeExceeded($fields,$k,$decValue);
}
