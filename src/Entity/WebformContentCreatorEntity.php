<?php

namespace Drupal\webform_content_creator\Entity;

use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\node\Entity\Node;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\webform_content_creator\WebformContentCreatorInterface;
use Drupal\webform_content_creator\WebformContentCreatorUtilities;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\Component\Utility\Html;

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
 *   },
 *   config_export = {
 *     "id",
 *     "title",
 *     "webform",
 *     "content_type",
 *     "field_title",
 *     "use_encrypt",
 *     "encryption_profile",
 *     "sync_content",
 *     "sync_content_delete",
 *     "sync_content_node_field",
 *     "elements",
 *   }
 * )
 */
class WebformContentCreatorEntity extends ConfigEntityBase implements WebformContentCreatorInterface {

  use StringTranslationTrait, MessengerTrait;

  /**
   * Webform content creator entity id.
   *
   * @var string
   */
  protected $id;

  /**
   * Webform content creator entity title.
   *
   * @var string
   */
  protected $title;

  /**
   * Node title.
   *
   * @var string
   */
  protected $field_title;

  /**
   * Webform machine name.
   *
   * @var string
   */
  protected $webform;

  /**
   * Content type machine name.
   *
   * @var string
   */
  protected $content_type;

  /**
   * Mapping between webform submission values and node field values.
   *
   * @var array
   */
  protected $elements;

  /**
   * Use encryption.
   *
   * @var bool
   */
  protected $use_encrypt;

  /**
   * Encryption profile.
   *
   * @var string
   */
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
   * Sets the entity title.
   *
   * @param string $title
   *   Node title.
   *
   * @return $this
   *   The Webform Content Creator entity.
   */
  public function setTitle($title) {
    $this->set('title', $title);
    return $this;
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
   * Sets the content type entity.
   *
   * @param string $content_type
   *   Content type entity.
   *
   * @return $this
   *   The Webform Content Creator entity.
   */
  public function setContentType($content_type) {
    $this->set('content_type', $content_type);
    return $this;
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
   * Sets the entity webform id.
   *
   * @param string $webform
   *   Webform id.
   *
   * @return $this
   *   The Webform Content Creator entity.
   */
  public function setWebform($webform) {
    $this->set('webform', $webform);
    return $this;
  }

  /**
   * Returns the entity attributes as an associative array.
   *
   * @return array
   *   The entity attributes mapping.
   */
  public function getAttributes() {
    return $this->get(WebformContentCreatorInterface::ELEMENTS);
  }

  /**
   * Check if synchronization between nodes and webform submissions is used.
   *
   * @return bool
   *   true, when the synchronization is used. Otherwise, returns false.
   */
  public function getSyncEditContentCheck() {
    return $this->get(WebformContentCreatorInterface::SYNC_CONTENT);
  }

  /**
   * Check if synchronization is used in deletion.
   *
   * @return bool
   *   true, when the synchronization is used. Otherwise, returns false.
   */
  public function getSyncDeleteContentCheck() {
    return $this->get(WebformContentCreatorInterface::SYNC_CONTENT_DELETE);
  }

  /**
   * Get node field in which the webform submission id will be stored.
   *
   * @return string
   *   Field machine name.
   */
  public function getSyncContentField() {
    return $this->get(WebformContentCreatorInterface::SYNC_CONTENT_FIELD);
  }

  /**
   * Returns the encryption method.
   *
   * @return bool
   *   true, when an encryption profile is used. Otherwise, returns false.
   */
  public function getEncryptionCheck() {
    return $this->get(WebformContentCreatorInterface::USE_ENCRYPT);
  }

  /**
   * Returns the encryption profile.
   *
   * @return string
   *   The encryption profile name.
   */
  public function getEncryptionProfile() {
    return $this->get(WebformContentCreatorInterface::ENCRYPTION_PROFILE);
  }

  /**
   * Get node title.
   *
   * @return string
   *   Node title.
   */
  private function getNodeTitle() {
    // Get title.
    if ($this->get(WebformContentCreatorInterface::FIELD_TITLE) !== NULL && $this->get(WebformContentCreatorInterface::FIELD_TITLE) !== '') {
      $title = $this->get(WebformContentCreatorInterface::FIELD_TITLE);
    }
    else {
      $title = \Drupal::entityTypeManager()->getStorage(WebformContentCreatorInterface::WEBFORM)->load($this->get(WebformContentCreatorInterface::WEBFORM))->label();
    }

    return $title;
  }

  /**
   * Get encryption profile name.
   *
   * @return string
   *   Encryption profile name.
   */
  private function getProfileName() {
    $encryption_profile = '';
    $use_encrypt = $this->get(WebformContentCreatorInterface::USE_ENCRYPT);
    if ($use_encrypt) {
      $encryption_profile = \Drupal::service('entity_type.manager')->getStorage(WebformContentCreatorInterface::ENCRYPTION_PROFILE)->load($this->getEncryptionProfile());
    }

    return $encryption_profile;
  }

  /**
   * Get decrypted value with the configured encryption profile.
   *
   * @param string $value
   *   Encrypted value.
   * @param string $profile
   *   Encryption profile name.
   *
   * @return string
   *   Encryption profile used to encrypt/decrypt $value
   */
  private function getDecryptionFromProfile($value, $profile = '') {
    if ($this->getEncryptionCheck()) {
      $result = WebformContentCreatorUtilities::getDecryptedValue($value, $profile);
    }
    else {
      $result = $value;
    }
    return $result;
  }

  /**
   * Use a single mapping to set a Node field value.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $initial_content
   *   Content being mapped with a webform submission.
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *   Webform submission entity.
   * @param array $fields
   *   Node fields.
   * @param array $webform_fields
   *   Webform fields (flattened).
   * @param array $data
   *   Webform submission data.
   * @param string $encryption_profile
   *   Encryption profile used in Webform encrypt module.
   * @param string $field_id
   *   Node field id.
   * @param array $mapping
   *   Single mapping between node and webform submissions.
   * @param array $attributes
   *   All mapping values between Node and Webform submission values.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   Created node.
   */
  private function mapNodeField(ContentEntityInterface $initial_content, WebformSubmissionInterface $webform_submission, array $fields = [], array $webform_fields = [], array $data = [], $encryption_profile = '', $field_id = '', array $mapping = [], array $attributes = []) {
    $content = $initial_content;
    $webform = $webform_submission->getWebform();

    if (!$content->hasField($field_id) || !is_array($mapping)) {
      return $content;
    }

    // Get the field mapping plugin.
    $field_mapping = \Drupal::service('plugin.manager.webform_content_creator.field_mapping')->getPlugin($attributes[$field_id][WebformContentCreatorInterface::FIELD_MAPPING]);

    $component_fields = $field_mapping->getEntityComponentFields($fields[$field_id]);
    $values = [];
    $webform_element = [];
    if (sizeOf($component_fields) > 0) {
      foreach ($component_fields as $component_field) {
        $webform_element[$component_field] = $webform->getElement($mapping[$component_field][WebformContentCreatorInterface::WEBFORM_FIELD], FALSE);
        // If the custom check functionality is active then we do need to evaluate
        // webform fields.
        if ($attributes[$field_id][WebformContentCreatorInterface::CUSTOM_CHECK]) {
          $field_value = WebformContentCreatorUtilities::getTokenValue($mapping[WebformContentCreatorInterface::CUSTOM_VALUE], $encryption_profile, $webform_submission);
        }
        else {
          if (!$attributes[$field_id][WebformContentCreatorInterface::TYPE]) {
            if (!array_key_exists(WebformContentCreatorInterface::WEBFORM_FIELD, $mapping) || !array_key_exists($mapping[WebformContentCreatorInterface::WEBFORM_FIELD], $data)) {
              return $content;
            }
            $field_value = $this->getDecryptionFromProfile($data[$mapping[WebformContentCreatorInterface::WEBFORM_FIELD]], $encryption_profile);
            if ($fields[$field_id]->getType() === 'entity_reference' && (!is_array($field_value) && intval($field_value) === 0)) {
              $content->set($field_id, []);
              return $content;
            }
          }
          else {
            $field_object = $webform_submission->{$mapping[WebformContentCreatorInterface::WEBFORM_FIELD]};
            if ($field_object instanceof EntityReferenceFieldItemList) {
              $field_value = $webform_submission->{$mapping[WebformContentCreatorInterface::WEBFORM_FIELD]}->getValue()[0]['target_id'];
            }
            else {
              $field_value = $webform_submission->{$mapping[WebformContentCreatorInterface::WEBFORM_FIELD]}->value;
            }

          }
        }
        $values[$component_field] = $field_value;
      }
    } else {
      // If the custom check functionality is active then we do need to evaluate
      // webform fields.
      if ($attributes[$field_id][WebformContentCreatorInterface::CUSTOM_CHECK]) {
        $field_value = WebformContentCreatorUtilities::getTokenValue($mapping[WebformContentCreatorInterface::CUSTOM_VALUE], $encryption_profile, $webform_submission);
      }
      else {
        if (!$attributes[$field_id][WebformContentCreatorInterface::TYPE]) {
          if (!array_key_exists(WebformContentCreatorInterface::WEBFORM_FIELD, $mapping) || !array_key_exists($mapping[WebformContentCreatorInterface::WEBFORM_FIELD], $data)) {
            return $content;
          }
          $field_value = $this->getDecryptionFromProfile($data[$mapping[WebformContentCreatorInterface::WEBFORM_FIELD]], $encryption_profile);
          if ($fields[$field_id]->getType() === 'entity_reference' && (!is_array($field_value) && intval($field_value) === 0)) {
            $content->set($field_id, []);
            return $content;
          }
        }
        else {
          $field_object = $webform_submission->{$mapping[WebformContentCreatorInterface::WEBFORM_FIELD]};
          if ($field_object instanceof EntityReferenceFieldItemList) {
            $field_value = $webform_submission->{$mapping[WebformContentCreatorInterface::WEBFORM_FIELD]}->getValue()[0]['target_id'];
          }
          else {
            $field_value = $webform_submission->{$mapping[WebformContentCreatorInterface::WEBFORM_FIELD]}->value;
          }

        }
      }
      $values[$field_id] = $field_value;
    }
    if ($fields[$field_id]->getType() == 'datetime') {
      $field_value = $this->convertTimestamp($fields, $field_id, $field_value);
    }

    // Map the field type using the selected field mapping.
    $field_value = $field_mapping->mapEntityField($content, $webform_element, $values, $fields[$field_id]);

    return $content;
  }

  /**
   * Create node from webform submission.
   *
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *   Webform submission.
   * 
   * @return int
   *   Result after saving content.
   */
  public function createNode(WebformSubmissionInterface $webform_submission) {
    $node_title = $this->getNodeTitle();

    // Get webform submission data.
    $data = $webform_submission->getData();
    if (empty($data)) {
      return 0;
    }

    $encryption_profile = $this->getProfileName();

    // Get title.
    $title = WebformContentCreatorUtilities::getTokenValue($node_title, $encryption_profile, $webform_submission);

    // Decode HTML entities, returning them to their original UTF-8 characters.
    $decoded_title = Html::decodeEntities($title);

    // Create new node.
    $content = Node::create([
      WebformContentCreatorInterface::TYPE => $this->getContentType(),
      'title' => $decoded_title,
    ]);

    // Set node fields values.
    $attributes = $this->get(WebformContentCreatorInterface::ELEMENTS);

    $content_type = \Drupal::entityTypeManager()->getStorage('node_type')->load($this->getContentType());
    if (empty($content_type)) {
      return 0;
    }

    // Get the webform fields flattened to identify field types.
    $webform = $webform_submission->getWebform();
    $webform_fields = [];
    if ($webform) {
      $webform_fields = $webform->getElementsDecodedAndFlattened();
    }

    $fields = WebformContentCreatorUtilities::contentTypeFields($content_type);
    if (empty($fields)) {
      return 0;
    }
    foreach ($attributes as $k2 => $v2) {
      $content = $this->mapNodeField($content, $webform_submission, $fields, $webform_fields, $data, $encryption_profile, $k2, $v2, $attributes);
    }

    $result = 0;

    // Save node.
    try {
      $result = $content->save();
    }
    catch (\Exception $e) {
      \Drupal::logger(WebformContentCreatorInterface::WEBFORM_CONTENT_CREATOR)->error($this->t('A problem occurred when creating a new node.'));
      \Drupal::logger(WebformContentCreatorInterface::WEBFORM_CONTENT_CREATOR)->error($e->getMessage());
    }
    return $result;
  }

  /**
   * Update node from webform submission.
   *
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *   Webform submission.
   * @param string $op
   *   Operation.
   *
   * @return int
   *   Result after saving content.
   */
  public function updateNode(WebformSubmissionInterface $webform_submission, $op = 'edit') {
    if (empty($this->getSyncContentField())) {
      return 0;
    }

    $content_type = \Drupal::entityTypeManager()->getStorage('node_type')->load($this->getContentType());
    if (empty($content_type)) {
      return 0;
    }

    // Get the webform fields flattened to identify field types.
    $webform = $webform_submission->getWebform();
    $webform_fields = [];
    if ($webform) {
      $webform_fields = $webform->getElementsDecodedAndFlattened();
    }

    $fields = WebformContentCreatorUtilities::contentTypeFields($content_type);
    if (empty($fields)) {
      return 0;
    }

    if (!array_key_exists($this->getSyncContentField(), $fields)) {
      return 0;
    }

    $node_title = $this->getNodeTitle();

    // Get webform submission data.
    $data = $webform_submission->getData();
    if (empty($data)) {
      return 0;
    }

    $encryption_profile = $this->getProfileName();

    // Get title.
    $title = WebformContentCreatorUtilities::getTokenValue($node_title, $encryption_profile, $webform_submission);

    // Decode HTML entities, returning them to their original UTF-8 characters.
    $decoded_title = Html::decodeEntities($title);

    // Get nodes created from this webform submission.
    $nodes = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadByProperties([$this->getSyncContentField() => $webform_submission->id()]);

    // Use only first result, if exists.
    if (!($content = reset($nodes))) {
      return 0;
    }

    if ($op === 'delete' && !empty($this->getSyncDeleteContentCheck())) {
      $result = $content->delete();
      return $result;
    }

    if (empty($this->getSyncEditContentCheck())) {
      return 0;
    }

    // Set title.
    $content->setTitle($decoded_title);

    // Set node fields values.
    $attributes = $this->get(WebformContentCreatorInterface::ELEMENTS);

    foreach ($attributes as $k2 => $v2) {
      $content = $this->mapNodeField($content, $webform_submission, $fields, $webform_fields, $data, $encryption_profile, $k2, $v2, $attributes);
    }

    $result = 0;

    // Save node.
    try {
      $result = $content->save();
    }
    catch (\Exception $e) {
      \Drupal::logger(WebformContentCreatorInterface::WEBFORM_CONTENT_CREATOR)->error($this->t('A problem occurred while updating node.'));
      \Drupal::logger(WebformContentCreatorInterface::WEBFORM_CONTENT_CREATOR)->error($e->getMessage());
    }

    return $result;

  }

  /**
   * Check if the content type entity exists.
   *
   * @return bool
   *   True, if content type entity exists. Otherwise, returns false.
   */
  public function existsContentType() {
    // Get content type id.
    $content_type_id = $this->getContentType();

    // Get content type entity.
    $content_type_entity = \Drupal::entityTypeManager()->getStorage('node_type')->load($content_type_id);
    return !empty($content_type_entity);
  }

  /**
   * Check if the content type id is equal to the configured content type.
   *
   * @param string $ct
   *   Content type id.
   *
   * @return bool
   *   True, if the parameter is equal to the content type id of Webform
   *   content creator entity. Otherwise, returns false.
   */
  public function equalsContentType($ct) {
    return $ct === $this->getContentType();
  }

  /**
   * Check if the webform id is equal to the configured webform id.
   *
   * @param string $webform
   *   Webform id.
   *
   * @return bool
   *   True, if the parameter is equal to the webform id of Webform
   *   content creator entity. Otherwise, returns false.
   */
  public function equalsWebform($webform) {
    return $webform === $this->getWebform();
  }

  /**
   * Show a message accordingly to status, after creating/updating an entity.
   *
   * @param int $status
   *   Status int, returned after creating/updating an entity.
   */
  public function statusMessage($status) {
    if ($status) {
      $this->messenger()->addMessage($this->t('Saved the %label entity.', ['%label' => $this->getTitle()]));
    }
    else {
      $this->messenger()->addMessage($this->t('The %label entity was not saved.', ['%label' => $this->getTitle()]));
    }
  }

  /**
   * Convert timestamp value according to field type.
   *
   * @param array $fields
   *   Content type fields.
   * @param string $field_id
   *   Field machine name.
   * @param string $value
   *   Original datetime value.
   *
   * @return string
   *   Converted datetime value.
   */
  public function convertTimestamp(array $fields, $field_id, $field_value) {
    $date_time = new DrupalDateTime($field_value, 'UTC');
    $date_type = $fields[$field_id]->getSettings()['datetime_type'];
    if ($date_type === 'datetime') {
      $result = \Drupal::service('date.formatter')->format(
        $date_time->getTimestamp(), 'custom',
        DateTimeItemInterface::DATETIME_STORAGE_FORMAT, 'UTC'
      );
    }
    else {
      $result = \Drupal::service('date.formatter')->format(
        $date_time->getTimestamp(), 'custom', 
        DateTimeItemInterface::DATE_STORAGE_FORMAT, 'UTC'
      );
    }

    return $result;
  }

}
