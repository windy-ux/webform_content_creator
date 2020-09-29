<?php

namespace Drupal\webform_content_creator\Form;

use Drupal\Core\Url;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\webform_content_creator\WebformContentCreatorUtilities;

/**
 * Form handler for the Webform content creator manage fields form.
 */
class WebformContentCreatorManageFieldsForm extends EntityForm {

  const CONTENT_TYPE_FIELD = 'content_type_field';

  const FIELD_TYPE = 'field_type';

  const WEBFORM_FIELD = 'webform_field';

  const FIELD_MAPPING = 'field_mapping';

  const CUSTOM_CHECK = 'custom_check';

  const CUSTOM_VALUE = 'custom_value';

  const FORM_TABLE = 'table';

  /**
   * Plugin field type.
   *
   * @var object
   */
  protected $pluginFieldType;

  /**
   * Entity type manager.
   *
   * @var object
   */
  protected $entityTypeManager;

  /**
   * Field mapping manager.
   *
   * @var object
   */
  protected $fieldMappingManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static();
    $instance->pluginFieldType = $container->get('plugin.manager.field.field_type');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->fieldMappingManager = $container->get('plugin.manager.webform_content_creator.field_mapping');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#maxlength' => 255,
      '#default_value' => $this->entity->get('field_title'),
      '#help' => $this->t('Title of content created after webform submission. You may use tokens.'),
      '#description' => $this->t("Default value: webform title."),
      '#weight' => 0,
    ];
    $form['intro_text'] = [
      '#markup' => '<p>' . $this->t('You can create nodes based on webform submission values. In this page, you can do mappings between content type fields and webform submission values. You may also use tokens in custom text.') . '</p>',
    ];
    if (\Drupal::service('module_handler')->moduleExists('token')) {
      $form['tokens'] = [
        '#theme' => 'token_tree_link',
        '#token_types' => ['webform_submission'],
        '#global_types' => TRUE,
        '#click_insert' => TRUE,
        '#show_restricted' => FALSE,
        '#recursion_limit' => 3,
        '#text' => $this->t('Browse available tokens'),
      ];
    }
    // Construct table with mapping between content type and webform.
    $this->constructTable($form);
    return $form;
  }

  /**
   * Constructs table with mapping between webform and content type.
   *
   * @param array $form
   *   Form entity array.
   */
  public function constructTable(array &$form) {
    $field_types_definitions = $this->pluginFieldType->getDefinitions();
    $attributes = $this->entity->getAttributes();
    $ct = $this->entity->getContentType();
    $content_type = $this->entityTypeManager->getStorage('node_type')->load($ct);
    $node_filtered_field_ids = WebformContentCreatorUtilities::getContentFieldsIds($content_type);
    asort($node_filtered_field_ids);
    $node_fields = WebformContentCreatorUtilities::contentTypeFields($content_type);
    $webform_id = $this->entity->getWebform();
    $webform_options = WebformContentCreatorUtilities::getWebformElements($webform_id);

    // Table header.
    $header = [
      self::CONTENT_TYPE_FIELD => $this->t('Content type field'),
      self::FIELD_TYPE => $this->t('Field type'),
      self::FIELD_MAPPING => $this->t('Field mapping'),
      self::CUSTOM_CHECK => $this->t('Custom'),
      self::WEBFORM_FIELD => $this->t('Webform field'),
      self::CUSTOM_VALUE => $this->t('Custom text'),
    ];
    $form[self::FORM_TABLE] = [
      '#type' => 'table',
      '#header' => $header,
    ];

    // Get the field mapping plugin manager.
    // $field_mapping_manager = \Drupal::service('plugin.manager.webform_content_creator.field_mappings');

    foreach ($node_filtered_field_ids as $field_id) {
      $route_parameters = [
        'node_type' => $ct,
        'field_config' => 'node.' . $ct . '.' . $field_id,
      ];

      $field_mapping_plugin = isset($attributes[$field_id][self::FIELD_MAPPING]) ? $attributes[$field_id][self::FIELD_MAPPING] : 'default_mapping';
      $selected_field_mapping = $this->fieldMappingManager->getPlugin($field_mapping_plugin);

      // Checkboxes with content type fields.
      $form[self::FORM_TABLE][$field_id][self::CONTENT_TYPE_FIELD] = [
        '#type' => 'checkbox',
        '#default_value' => array_key_exists($field_id, $attributes),
        '#title' => $node_fields[$field_id]->getLabel() . ' (' . $field_id . ')',
      ];

      // The field type.
      $node_field_type = $node_fields[$field_id]->getType();

      // Link to edit field settings.
      $form[self::FORM_TABLE][$field_id][self::FIELD_TYPE] = [
        '#type' => 'link',
        '#title' => $field_types_definitions[$node_field_type]['label'],
        '#url' => Url::fromRoute("entity.field_config.node_storage_edit_form", $route_parameters),
        '#options' => ['attributes' => ['title' => $this->t('Edit field settings.')]],
      ];

      // Find available field mappings for the element type.
      $field_mapping_options = [];
      foreach ($this->fieldMappingManager->getFieldMappings($node_field_type) as $field_mapping) {
        $field_mapping_options[$field_mapping->getId()] = $field_mapping->getLabel();
      }

      // Select the field mapping.
      $default_value = array_key_exists($field_id, $attributes) && isset($attributes[$field_id][self::FIELD_MAPPING]) ? $attributes[$field_id][self::FIELD_MAPPING] : '';
      $form[self::FORM_TABLE][$field_id][self::FIELD_MAPPING] = [
        '#type' => 'select',
        '#options' => $field_mapping_options,
        '#default_value' => $default_value,
        '#states' => [
          'disabled' => [
            ':input[name="' . self::FORM_TABLE . '[' . $field_id . '][' . self::CONTENT_TYPE_FIELD . ']"]' => ['checked' => FALSE],
          ],
        ],
      ];

      $component_fields = $field_mapping->getEntityComponentFields($node_fields[$field_id]);
      $webform_options = $field_mapping->getSupportedWebformFields($webform_id);
      if (sizeOf($component_fields) > 0) {
        foreach ($component_fields as $component_field) {
          $type = !empty($attributes[$field_id][$component_field]) && $attributes[$field_id][$component_field]['type'] ? '1' : '0';
          if (empty($attributes[$field_id][$component_field]) || $selected_field_mapping->supportsCustomFields()) {
            $form[self::FORM_TABLE][$field_id][self::CUSTOM_CHECK][$component_field] = $this->constructCustomCheck($field_id, $attributes, $component_field);
          } else {
            $form[self::FORM_TABLE][$field_id][self::CUSTOM_CHECK][$component_field] = [];
          }
          $form[self::FORM_TABLE][$field_id][self::WEBFORM_FIELD][$component_field] = $this->constructWebformField($field_id, $webform_options, $attributes, $type, $component_field);
          if (empty($attributes[$field_id][$component_field]) || $selected_field_mapping->supportsCustomFields()) {
            $form[self::FORM_TABLE][$field_id][self::CUSTOM_VALUE][$component_field] = $this->constructCustomValue($field_id, $attributes, $component_field);
          } else {
            $form[self::FORM_TABLE][$field_id][self::CUSTOM_VALUE][$component_field] = [];
          }
        }
      } else {
        $type = !empty($attributes[$field_id]) && $attributes[$field_id]['type'] ? '1' : '0';
        if (empty($attributes[$field_id]) || $selected_field_mapping->supportsCustomFields()) {
          $form[self::FORM_TABLE][$field_id][self::CUSTOM_CHECK] = $this->constructCustomCheck($field_id, $attributes);
        } else {
          $form[self::FORM_TABLE][$field_id][self::CUSTOM_CHECK] = [];
        }
        $form[self::FORM_TABLE][$field_id][self::WEBFORM_FIELD] = $this->constructWebformField($field_id, $webform_options, $attributes, $type);
        if (empty($attributes[$field_id]) || $selected_field_mapping->supportsCustomFields()) {
          $form[self::FORM_TABLE][$field_id][self::CUSTOM_VALUE] = $this->constructCustomValue($field_id, $attributes);
        } else {
          $form[self::FORM_TABLE][$field_id][self::CUSTOM_VALUE] = [];
        }
      }
    }

    // Change table position in page.
    $form[self::FORM_TABLE]['#weight'] = 1;
  }

  protected function constructCustomCheck($field_id, array $attributes, $component_field = NULL) {
    // Checkbox to select between webform element/property or custom text.
    if (!empty($component_field)) {
      $default_value = array_key_exists($field_id, $attributes) && isset($attributes[$field_id][$component_field][self::CUSTOM_CHECK]) ? $attributes[$field_id][$component_field][self::CUSTOM_CHECK] : '';
    } else {
      $default_value = array_key_exists($field_id, $attributes) && isset($attributes[$field_id][self::CUSTOM_CHECK]) ? $attributes[$field_id][self::CUSTOM_CHECK] : '';
    }

    $custom_checkbox = [
      '#type' => 'checkbox',
      '#title' => !empty($component_field) ? '(' . $component_field . ')' : NULL,
      '#default_value' => $default_value,
      '#states' => [
        'disabled' => [
          ':input[name="' . self::FORM_TABLE . '[' . $field_id . '][' . self::CONTENT_TYPE_FIELD . ']"]' => ['checked' => FALSE],
         ],
      ]
    ];

    return $custom_checkbox;
  }

  protected function constructWebformField($field_id, array $webform_options, $attributes, $type, $component_field = NULL) {
    $webform_field = [
      '#type' => 'select',
      '#title' => !empty($component_field) ? '(' . $component_field . ')' : NULL,
      '#options' => $webform_options,
      '#states' => [
        'required' => [
          ':input[name="' . self::FORM_TABLE . '[' . $field_id . '][' . self::CONTENT_TYPE_FIELD . ']"]' => ['checked' => TRUE],
        ],
      ]
    ];
     
    if (array_key_exists($field_id, $attributes) && !$attributes[$field_id][self::CUSTOM_CHECK]) {
      $form[self::FORM_TABLE][$field_id][self::WEBFORM_FIELD]['#default_value'] = $type . ',' . $attributes[$field_id][self::WEBFORM_FIELD];
      if (!empty($component_field)) {
        $webform_field['#states']['required'][':input[name="' . self::FORM_TABLE . '[' . $field_id . '][' . self::CUSTOM_CHECK . '][' . $component_field . ']"]'] = ['checked' => FALSE];
        if (array_key_exists($field_id, $attributes) && (!isset($attributes[$field_id][self::CUSTOM_CHECK][$component_field]) || !$attributes[$field_id][self::CUSTOM_CHECK][$component_field])) {
          $webform_field['#default_value'] = $type . ',' . $attributes[$field_id][$component_field][self::WEBFORM_FIELD];
        }
      } else {
        $webform_field['#states']['required'][':input[name="' . self::FORM_TABLE . '[' . $field_id . '][' . self::CUSTOM_CHECK . ']"]'] = ['checked' => FALSE];
        if (array_key_exists($field_id, $attributes) && isset($attributes[$field_id][self::CUSTOM_CHECK])  && !$attributes[$field_id][self::CUSTOM_CHECK]) {
          $webform_field['#default_value'] = $type . ',' . $attributes[$field_id][self::WEBFORM_FIELD];
        }
      }
     
      // Textarea with custom text (including tokens)
      $form[self::FORM_TABLE][$field_id][self::CUSTOM_VALUE] = [
        '#type' => 'textarea',
        '#default_value' => array_key_exists($field_id, $attributes) ? $attributes[$field_id][self::CUSTOM_VALUE] : '',
      ];
    }

    return $webform_field;
  }

  protected function constructCustomValue($field_id, array $attributes, $component_field = NULL) {
    if (!empty($component_field)) {
      $default_value = array_key_exists($field_id, $attributes) ? $attributes[$field_id][$component_field][self::CUSTOM_VALUE] : '';
    } else {
      $default_value = array_key_exists($field_id, $attributes) ? $attributes[$field_id][self::CUSTOM_VALUE] : '';
    }

    $custom_value = [
      '#type' => !empty($component_field) ? 'textfield' : 'textarea',
      '#title' => !empty($component_field) ? '(' . $component_field . ')' : NULL,
      '#default_value' => $default_value,
    ];

    return $custom_value;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    $ct = $this->entity->getContentType();
    $content_type = $this->entityTypeManager->getStorage('node_type')->load($ct);
    $node_fields = WebformContentCreatorUtilities::contentTypeFields($content_type);
    $webform_id = $this->entity->getWebform();
    $webform_element_types = WebformContentCreatorUtilities::getWebformElementsTypes($webform_id);
    // For each table row.
    foreach ($form_state->getValue(self::FORM_TABLE) as $k => $v) {
      // Check if a content type field is selected.
      if (!$v[self::CONTENT_TYPE_FIELD]) {
        continue;
      }
      if (is_array($v[self::WEBFORM_FIELD])) {
        $args = explode(',', $v[self::WEBFORM_FIELD]);
        if (empty($args) || count($args) < 2) {
          continue;
        }
      } else {
        foreach($v[self::WEBFORM_FIELD] as $key => $component) {
          $args = explode(',', $v[self::WEBFORM_FIELD][$key]);
          if (empty($args) || count($args) < 2) {
            continue;
          }
        }
      }
      $node_field_type = $node_fields[$k]->getType();
      $webform_option_type = array_key_exists($args[1], $webform_element_types) ? $webform_element_types[$args[1]] : '';
      if ($node_field_type === $webform_option_type) {
        continue;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $attributes = [];
    // For each table row.
    foreach ($form_state->getValue(self::FORM_TABLE) as $k => $v) {
      // Check if a content type field is selected.
      if (!$v[self::CONTENT_TYPE_FIELD]) {
        continue;
      }
      if (is_array($v[self::WEBFORM_FIELD])) {
        foreach($v[self::WEBFORM_FIELD] as $key => $component) {
          $args = explode(',', $component);
          if (empty($args) || count($args) < 1) {
            continue;
          }

          $attributes[$k][$key] = [
            'type' => explode(',', $v[self::WEBFORM_FIELD][$key])[0] === '1',
            self::WEBFORM_FIELD => (!isset($v[self::CUSTOM_CHECK][$key]) || !$v[self::CUSTOM_CHECK][$key]) && sizeof(explode(',', $v[self::WEBFORM_FIELD][$key])) > 1 ? explode(',', $v[self::WEBFORM_FIELD][$key])[1] : '',
            self::CUSTOM_CHECK => isset($v[self::CUSTOM_CHECK][$key]) ? $v[self::CUSTOM_CHECK][$key] : 0,
            self::CUSTOM_VALUE => (isset($v[self::CUSTOM_CHECK][$key]) && $v[self::CUSTOM_CHECK][$key]) ? $v[self::CUSTOM_VALUE][$key] : '',
            self::FIELD_MAPPING => isset($v[self::FIELD_MAPPING][$key]) ? $v[self::FIELD_MAPPING][$key] : '',
          ];
        }
      } else {
        $args = explode(',', $v[self::WEBFORM_FIELD]);
        if (empty($args) || count($args) < 1) {
          continue;
        }

        $attributes[$k] = [
          'type' => explode(',', $v[self::WEBFORM_FIELD])[0] === '1',
          self::WEBFORM_FIELD => (!isset($v[self::CUSTOM_CHECK]) || !$v[self::CUSTOM_CHECK]) && sizeOf(explode(',', $v[self::WEBFORM_FIELD])) > 1 ? explode(',', $v[self::WEBFORM_FIELD])[1] : '',
          self::CUSTOM_CHECK => isset($v[self::CUSTOM_CHECK]) ? $v[self::CUSTOM_CHECK] : 0,
          self::CUSTOM_VALUE => (isset($v[self::CUSTOM_CHECK]) && $v[self::CUSTOM_CHECK]) ? $v[self::CUSTOM_VALUE] : '',
          self::FIELD_MAPPING => isset($v[self::FIELD_MAPPING]) ? $v[self::FIELD_MAPPING] : '',
        ];
      }
    }

    $this->entity->set('field_title', $form_state->getValue('title'));
    $this->entity->set('elements', $attributes);
    $status = $this->entity->save();
    $this->entity->statusMessage($status);
    $form_state->setRedirect('entity.webform_content_creator.collection');
  }

  /**
   * Helper function to check whether a Webform content creator entity exists.
   *
   * @param mixed $id
   *   Entity id.
   *
   * @return bool
   *   True if entity already exists.
   */
  public function exist($id) {
    return WebformContentCreatorUtilities::existsWebformContentCreatorEntity($id);
  }

}
