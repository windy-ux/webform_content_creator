<?php

namespace Drupal\webform_content_creator\Form;

use Drupal\Core\Url;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\webform_content_creator\WebformContentCreatorUtilities;

/**
 * Form handler for the Webform content creator manage fields form.
 */
class WebformContentCreatorManageFieldsForm extends EntityForm {

  /**
   * Constructs an WebformContentCreatorForm object.
   *
   * @param \Drupal\Core\Entity\Query\QueryFactory $entity_query
   *   The entity query.
   */
  public function __construct(QueryFactory $entity_query) {
    $this->entityQuery = $entity_query;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
        $container->get('entity.query')
    );
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
      '#help' => 'Title of content created after webform submission. You may use tokens.',
      '#description' => $this->t("Default value: webform title."),
      '#weight' => 0,
    ];
    $form['intro_text'] = [
      '#markup' => '<p>' . t('You can create nodes based on webform submission values. In this page, you can do mappings between content type fields and webform submission values. You may also use tokens in custom text.') . '</p>',
    ];
    $form['tokens'] = [
      '#theme' => 'token_tree_link',
      '#token_types' => array('webform_submission'),
      '#global_types' => TRUE,
      '#click_insert' => TRUE,
      '#show_restricted' => FALSE,
      '#recursion_limit' => 3,
      '#text' => t('Browse available tokens'),
    ];
    // construct table with mapping between content type fields and webform elements
    $this->constructTable($form);
    return $form;
  }

  /**
   * Constructs an administration table to configure the mapping between webform elements and content type fields.
   *
   * @param Drupal\Core\Entity\EntityForm $form
   */
  function constructTable(&$form) {
    $fieldTypesDefinitions = \Drupal::service('plugin.manager.field.field_type')->getDefinitions();
    $attributes = $this->entity->getAttributes();
    $ct = $this->entity->getContentType();
    $contentType = \Drupal::entityTypeManager()->getStorage('node_type')->load($ct);
    $nodeFilteredFieldIds = WebformContentCreatorUtilities::getContentFieldsIds($contentType);
    $nodeFields = WebformContentCreatorUtilities::contentTypeFields($contentType);
    $webform_id = $this->entity->getWebform();
    $webformOptions = WebformContentCreatorUtilities::getWebformElements($webform_id);

    // table header
    $header = array(
      'content_type_field' => t('Content type field'),
      'field_type' => t('Field type'),
      'custom_check' => t('Custom'),
      'webform_field' => t('Webform field'),
      'custom_value' => t('Custom text'),
    );
    $form['table'] = [
      '#type' => 'table',
      '#header' => $header,
    ];

    foreach ($nodeFilteredFieldIds as $k2 => $v2) {
      $route_parameters = [
        'node_type' => 'exposition',
        'field_config' => 'node.' . $ct . '.' . $v2,
      ];

      //checkboxes with content type fields
      $form['table'][$v2]['content_type_field'] = [
        '#type' => 'checkbox',
        '#default_value' => array_key_exists($v2, $attributes),
        '#title' => $nodeFields[$v2]->getLabel() . ' (' . $v2 . ')',
      ];

      //link to edit field settings
      $form['table'][$v2]['field_type'] = [
        '#type' => 'link',
        '#title' => $fieldTypesDefinitions[$nodeFields[$v2]->getType()]['label'],
        '#url' => Url::fromRoute("entity.field_config.node_storage_edit_form", $route_parameters),
        '#options' => ['attributes' => ['title' => $this->t('Edit field settings.')]],
      ];

      //checkbox to select between webform element/property or custom text
      $form['table'][$v2]['custom_check'] = [
        '#type' => 'checkbox',
        '#default_value' => array_key_exists($v2, $attributes) ? $attributes[$v2]['custom_check'] : '',
        '#states' => [
          'disabled' => [
            ':input[name="table[' . $v2 . '][content_type_field]"]' => ['checked' => false],
          ],
        ],
      ];

      $type = !empty($attributes[$v2]) && $attributes[$v2]['type'] ? '1' : '0';
      //select with webform elements and basic properties
      $form['table'][$v2]['webform_field'] = [
        '#type' => 'select',
        '#options' => $webformOptions,
        '#states' => [
          'required' => [
            ':input[name="table[' . $v2 . '][content_type_field]"]' => ['checked' => true],
            ':input[name="table[' . $v2 . '][custom_check]"]' => ['checked' => false],
          ],
        ],
      ];

      if (array_key_exists($v2, $attributes) && !$attributes[$v2]['custom_check']) {
        $form['table'][$v2]['webform_field']['#default_value'] = $type . ',' . $attributes[$v2]['webform_field'];
      }

      // textfield with custom text (including tokens)
      $form['table'][$v2]['custom_value'] = ['#type' => 'textfield',
        '#default_value' => array_key_exists($v2, $attributes) ? $attributes[$v2]['custom_value'] : '',
        '#size' => 35,
      ];
    }

    // change table position in page
    $form['table']['#weight'] = 1;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    $ct = $this->entity->getContentType();
    $contentType = \Drupal::entityTypeManager()->getStorage('node_type')->load($ct);
    $nodeFields = WebformContentCreatorUtilities::contentTypeFields($contentType);
    $webform_id = $this->entity->getWebform();
    $webformElementTypes = WebformContentCreatorUtilities::getWebformElementsTypes($webform_id);
    foreach ($form_state->getValue('table') as $k => $v) { // for each table row
      if (!$v["content_type_field"]) { // check if a content type field is selected
        continue;
      }
      $args = explode(',', $v["webform_field"]);
      if (empty($args) || count($args) < 1) {
        continue;
      }

      $nodeFieldType = $nodeFields[$k]->getType();
      $webformOptionType = $webformElementTypes[$args[1]];
      if ($nodeFieldType === $webformOptionType) {
        continue;
      }

      if ($nodeFieldType === 'email') {
        $form_state->setErrorByName('table][' . $k . '][webform_field', t('Incompatible type'));
      }

      if ($webformOptionType === 'email' && (strpos($nodeFieldType, 'text') === false) && (strpos($nodeFieldType, 'string') === false)) {
        $form_state->setErrorByName('table][' . $k . '][webform_field', t('Incompatible type'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $attributes = [];
    foreach ($form_state->getValue('table') as $k => $v) { // for each table row
      if (!$v["content_type_field"]) { // check if a content type field is selected
        continue;
      }
      $args = explode(',', $v["webform_field"]);
      if (empty($args) || count($args) < 1) {
        continue;
      }

      $attributes[$k] = [
        'type' => explode(',', $v["webform_field"])[0] === '1' ? true : false,
        'webform_field' => !$v["custom_check"] ? explode(',', $v["webform_field"])[1] : '',
        'custom_check' => $v["custom_check"],
        'custom_value' => $v["custom_check"] ? $v["custom_value"] : '',
      ];
    }

    $this->entity->set('field_title', $form_state->getValue('title'));
    $this->entity->set('elements', $attributes);
    $status = $this->entity->save();
    $this->entity->statusMessage($status);
    $form_state->setRedirect('entity.webform_content_creator.collection');
  }

  /**
   * Helper function to check whether a Webform content type creator entity exists.
   *
   * @param type $id Entity id
   * @return boolean Return true if entity already exists
   */
  public function exist($id) {
    $entity = $this->entityQuery->get('webform_content_creator')
        ->condition('id', $id)
        ->execute();
    return (bool) $entity;
  }

}
