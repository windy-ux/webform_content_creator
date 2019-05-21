<?php

namespace Drupal\webform_content_creator\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\webform_content_creator\WebformContentCreatorUtilities;

/**
 * Form handler for the Webform content creator add and edit forms.
 */
class WebformContentCreatorForm extends EntityForm {

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

    $form = parent::form($form, $form_state);
    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#maxlength' => 255,
      '#default_value' => $this->entity->getTitle(),
      '#help' => $this->t('Configuration title'),
      '#required' => TRUE,
    ];
    $form['id'] = [
      '#type' => 'machine_name',
      '#title' => $this->t('Machine name'),
      '#default_value' => $this->entity->id(),
      '#machine_name' => [
        'exists' => [$this, 'exist'],
        'source' => ['title'],
      ],
      '#disabled' => !$this->entity->isNew(),
      '#description' => t('A unique machine-readable name for this content type. It must only contain lowercase letters, numbers, and underscores. This name will be used for constructing the URL of the %webform-content-creator-add page, in which underscores will be converted into hyphens.'),
    ];

    // select with all webforms
    $webforms_formatted = WebformContentCreatorUtilities::getFormattedWebforms();
    $form['webform'] = [
      '#type' => 'select',
      '#title' => $this->t('Webform'),
      '#options' => $webforms_formatted,
      '#default_value' => $this->entity->getWebform(),
      '#description' => $this->t("Webform title"),
      '#required' => TRUE,
    ];

    // select with all content types
    $contentTypes_formatted = WebformContentCreatorUtilities::getFormattedContentTypes();
    $form['content_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Content type'),
      '#options' => $contentTypes_formatted,
      '#default_value' => $this->entity->getContentType(),
      '#description' => $this->t("Content type title"),
      '#required' => TRUE,
    ];

    $form['use_encrypt'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Decrypt values'),
      '#description' => $this->t('This only applies when Webform encrypt module is being used in one or more webform elements.'),
      '#default_value' => $this->entity->getEncryptionCheck(),
    ];

    // select with all encryption profiles
    $encryptionProfiles_formatted = WebformContentCreatorUtilities::getFormattedEncryptionProfiles();
    $form['encryption_profile'] = [
      '#type' => 'select',
      '#title' => $this->t('Encryption profile'),
      '#options' => $encryptionProfiles_formatted,
      '#default_value' => $this->entity->getEncryptionProfile(),
      '#description' => $this->t("Encryption profile name"),
      '#required' => TRUE,
      '#states' => [
        'visible' =>
          [
          ':input[name="use_encrypt"]' => ['checked' => true],
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if (!$this->entity->equalsWebform($form['webform']['#default_value']) || !$this->entity->equalsContentType($form['content_type']['#default_value'])) {
      $this->entity->set('elements', []);
    }
    $status = $this->entity->save();
    $this->entity->statusMessage($status);
    $form_state->setRedirect('entity.webform_content_creator.collection');
  }

  /**
   * Helper function to check whether an Webform content creator configuration entity exists.
   */
  public function exist($id) {
    $entity = $this->entityQuery->get('webform_content_creator')
        ->condition('id', $id)
        ->execute();
    return (bool) $entity;
  }

}
