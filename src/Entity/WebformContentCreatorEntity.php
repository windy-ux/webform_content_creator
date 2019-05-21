<?php

namespace Drupal\webform_content_creator\Entity;

use Drupal\node\Entity\Node;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\webform_content_creator\WebformContentCreatorInterface;
use Drupal\Core\StringTranslation;
use Drupal\webform_content_creator\WebformContentCreatorUtilities;

/**
 * Defines the Webform Content creator entity.
 *
 * @ConfigEntityType(
 *   id = "webform_content_creator",
 *   label = @Translation("Webform Content creator"),
 *   handlers = {
 *     "list_builder" = "Drupal\webform_content_creator\Controller\WebformContentCreatorListBuilder",
 *     "form" = {
 *       "add" = "Drupal\webform_content_creator\Form\WebformContentCreatorForm",
 *       "edit" = "Drupal\webform_content_creator\Form\WebformContentCreatorForm",
 *       "delete" = "Drupal\webform_content_creator\Form\WebformContentCreatorDeleteForm",
 *       "manage_fields" = "Drupal\webform_content_creator\Form\WebformContentCreatorManageFieldsForm",
 *     }
 *   },
 *   config_prefix = "webform_content_creator",
 *   admin_permission = "administer site configuration",
 *   entity_keys = {
 *     "id" = "id",
 *     "title" = "title",
 *     "webform" = "webform",
 *     "content_type" = "content_type",
 *   },
 *   links = {
 *     "manage-fields-form" = "/admin/config/webform_content_creator/manage/{webform_content_creator}/fields",
 *     "edit-form" = "/admin/config/webform_content_creator/{webform_content_creator}",
 *     "delete-form" = "/admin/config/webform_content_creator/{webform_content_creator}/delete",
 *   }
 * )
 */
class WebformContentCreatorEntity extends ConfigEntityBase implements WebformContentCreatorInterface {

  use StringTranslation\StringTranslationTrait;

  protected $id;
  protected $title;
  protected $field_title;
  protected $webform;
  protected $content_type;
  protected $elements;
  protected $use_encrypt;
  protected $encryption_profile;

  /**
   * Returns the entity title.
   *
   * @return string
   *   The entity title.
   */
  public function getTitle() {
    return $this->get('title');
  }

  /**
   * Returns the entity content type id.
   *
   * @return string
   *   The entity content type.
   */
  public function getContentType() {
    return $this->get('content_type');
  }

  /**
   * Returns the entity webform id.
   *
   * @return string
   *   The entity webform.
   */
  public function getWebform() {
    return $this->get('webform');
  }

  /**
   * Returns the entity attributes as an associative array.
   *
   * @return array
   *   The entity attributes mapping.
   */
  public function getAttributes() {
    return $this->get('elements');
  }

  /**
   * Returns the encryption method.
   *
   * @return boolean
   *   true, when an encryption profile is used. Otherwise, returns false.
   */
  public function getEncryptionCheck() {
    return $this->get('use_encrypt');
  }

  /**
   * Returns the encryption profile.
   *
   * @return string
   *   The encryption profile name.
   */
  public function getEncryptionProfile() {
    return $this->get('encryption_profile');
  }

  /**
   * Create node from webform submission.
   *
   * @param WebformSubmission entity $webform_submission Webform submission
   */
  public function createNode($webform_submission) {
    // get title
    if ($this->get('field_title') !== null && $this->get('field_title') !== '') {
      $title = $this->get('field_title');
    } else {
      $title = \Drupal::entityTypeManager()->getStorage('webform')->load($this->get('webform'))->label();
    }

    // get webform submission data
    $data = $webform_submission->getData();
    if (empty($data)) {
      return 0;
    }
    // get encryption profile
    $use_encrypt = $this->get('use_encrypt');
    if ($use_encrypt) {
      $encryption_profile_name = $this->get('encryption_profile');
      $encryption_profile = \Drupal::service('entity.manager')->getStorage('encryption_profile')->load($encryption_profile_name);
    }

    // decrypt title
    $decrypted_title = WebformContentCreatorUtilities::getDecryptedTokenValue($title, $encryption_profile, $webform_submission);

    //create new node
    $content = Node::create([
          'type' => $this->getContentType(),
          'title' => $decrypted_title
    ]);

    // set node fields values
    $attributes = $this->get('elements');

    $contentType = \Drupal::entityTypeManager()->getStorage('node_type')->load($this->getContentType());
    if (empty($contentType)) {
      return false;
    }

    $fields = WebformContentCreatorUtilities::contentTypeFields($contentType);
    if (empty($fields)) {
      return false;
    }
    foreach ($attributes as $k2 => $v2) {
      if (!$content->hasField($k2)) { // check if node has the field
        continue;
      }
      if (!is_array($v2)) {
          continue;
      }
      if ($attributes[$k2]['custom_check']) { // custom text
        // use Drupal tokens to fill the field
        $decValue = WebformContentCreatorUtilities::getDecryptedTokenValue($v2['custom_value'], $encryption_profile, $webform_submission);
      } else {		
        if (!$attributes[$k2]['type']) { // webform element
          if (!array_key_exists('webform_field',$v2) || !array_key_exists($v2['webform_field'],$data)) {
            continue;
      	  }
          if ($use_encrypt) {
            $decValue = WebformContentCreatorUtilities::getDecryptedValue($data[$v2['webform_field']], $encryption_profile);
          } else {
            $decValue = $data[$v2['webform_field']];
          }
        } else { // webform basic property
          $decValue = $webform_submission->{$v2['webform_field']}->value;
        }
      }

      // check if field's max length is exceeded	  
      if ($this->checkMaxFieldSizeExceeded($fields,$k2,$decValue) !== 1) {
        $content->set($k2, $decValue);
      } else {
        $content->set($k2, substr($decValue, 0, $maxLength));
      }
    }

    // save node
    try {
      $result = $content->save();
    } catch (\Exception $e) {
      \Drupal::logger('webform_content_creator')->error(t('A problem occurred when creating a new node.'));
      \Drupal::logger('webform_content_creator')->error($e->getMessage());
    }
    return $result;
  }

  /**
   * Check if a content type entity associated with the Webform content creator entity exists.
   *
   * @return boolean true, if content type entity exists. Otherwise, returns false.
   */
  public function existsContentType() {
    $content_type = $this->getContentType(); // get content type id
    $content_type_entity = \Drupal::entityTypeManager()->getStorage('node_type')->load($content_type); // get content type entity
    if (!empty($content_type_entity)) {
      return true;
    }
    return false;
  }

  /**
   * Check if the content type id (parameter) is equal to the content type id of Webform content creator entity
   *
   * @param string $ct Content type id
   * @return boolean true, if the parameter is equal to the content type id of Webform content creator entity. Otherwise, returns false.
   */
  public function equalsContentType($ct) {
    if ($ct === $this->getContentType()) {
      return true;
    }
    return false;
  }

  /**
   * Check if the webform id (parameter) is equal to the webform id of Webform content creator entity
   *
   * @param string $webform Webform id
   * @return boolean true, if the parameter is equal to the webform id of Webform content creator entity. Otherwise, returns false.
   */
  public function equalsWebform($webform) {
    if ($webform === $this->getWebform()) {
      return true;
    }
    return false;
  }

  /**
   * Show a message accordingly to status value, after creating/updating an entity.
   *
   * @param int $status Status int, returned after creating/updating an entity.
   */
  public function statusMessage($status) {
    if ($status) {
      drupal_set_message($this->t('Saved the %label entity.', ['%label' => $this->getTitle(),]));
    } else {
      drupal_set_message($this->t('The %label entity was not saved.', ['%label' => $this->getTitle(),]));
    }
  }

  /**
   * Check if field maximum size is exceeded. 
   * 
   * @param array $fields Content type fields
   * @param string $k Field machine name
   * @param string $decValue Decrypted value
   * @return int 1 if maximum size is exceeded, otherwise return 0.
   */
  public function checkMaxFieldSizeExceeded($fields, $k, $decValue="") {
    if (!array_key_exists($k, $fields) || empty($fields[$k])) {
      return 0;
    }  
    $fieldSettings = $fields[$k]->getSettings();
    if (empty($fieldSettings)) {
      return 0;  
    }  
    if (!array_key_exists('max_length', $fieldSettings)) {
      return 0;	  
    }
    $maxLength = $fieldSettings['max_length'];
    if (empty($maxLength)) {
      return 0;
    }
    if ($maxLength < strlen($decValue)) {
      \Drupal::logger('webform_content_creator')->notice(t('Problem: Field\'s max length exceeded (truncated).'));
      return 1;
    }
  }
}
