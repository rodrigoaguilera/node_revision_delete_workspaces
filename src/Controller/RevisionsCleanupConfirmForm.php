<?php

namespace Drupal\node_revision_delete_workspaces\Controller;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\node_revision_delete_workspaces\WorkspacesEntityRevisionDeleteBatch;
use Drupal\workspaces\WorkspaceManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for the revisions cleanup of an entity.
 *
 * Class RevisionsCleanup
 * @package Drupal\node_revision_delete_workspaces\Controller
 */
class RevisionsCleanupConfirmForm extends ConfirmFormBase {

  /**
   * @var ContentEntityInterface
   */
  protected $entity;

  /**
   * @var WorkspaceManagerInterface
   */
  protected $workspaceManager;

  /**
   * @var EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * RevisionsCleanupConfirmForm constructor.
   */
  public function __construct(WorkspaceManagerInterface $workspace_manager, EntityTypeManagerInterface $entity_type_manager) {
    $this->workspaceManager = $workspace_manager;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container){
    return new static(
      $container->get('workspaces.manager'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritDoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $entity_type_id = NULL) {
    $this->entity = $this->getRouteMatch()->getParameter($entity_type_id);
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritDoc}
   */
  public function getQuestion() {
    return $this->t('Do you want to cleanup the revisions for this content?');
  }

  /**
   * {@inheritDoc}
   */
  public function getCancelUrl() {
    return new Url('entity.' . $this->entity->getEntityTypeId() . '.canonical', [$this->entity->getEntityTypeId() => $this->entity->id()]);
  }

  /**
   * {@inheritDoc}
   */
  public function getFormId() {
    return 'revisions_cleanup_confirm_form';
  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $workspaces = $this->entityTypeManager->getStorage('workspace')->loadMultiple();
    $batch = (new BatchBuilder())
      ->setTitle($this->t('Revisions cleanup'))
      ->setFinishCallback([WorkspacesEntityRevisionDeleteBatch::class, 'finish'])
      ->setInitMessage($this->t('Starting to cleanup revisions'));
    foreach ($workspaces as $workspace) {
      $batch->addOperation(
        [WorkspacesEntityRevisionDeleteBatch::class, 'executeForWorkspace'],
        [$workspace->id(), $this->entity->getEntityTypeId(), $this->entity->bundle(), $this->entity->id()]
      );
    }
    batch_set($batch->toArray());
  }
}
