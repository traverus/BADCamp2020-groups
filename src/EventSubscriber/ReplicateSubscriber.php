<?php

namespace Drupal\A_MODULE\EventSubscriber;

use Drupal\Core\Session\AccountInterface;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupContent;
use Drupal\replicate\Events\AfterSaveEvent;
use Drupal\replicate\Events\ReplicateAlterEvent;
use Drupal\replicate\Events\ReplicatorEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriptions for replicate.
 */
class ReplicateSubscriber implements EventSubscriberInterface {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Constructs a new ReplicateSubscriber.
   *
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   */
  public function __construct(AccountInterface $current_user) {
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   *
   * @return array
   *   The event names to listen for, and the methods that should be executed.
   */
  public static function getSubscribedEvents() {
    return [
      ReplicatorEvents::REPLICATE_ALTER => 'replicateAlter',
      ReplicatorEvents::AFTER_SAVE => 'replicateAfterSave',
    ];
  }

  /**
   * Reacts to the replicate alter event.
   *
   * @param \Drupal\replicate\Events\ReplicateAlterEvent $event
   *   The replicate alter event.
   */
  public function replicateAlter(ReplicateAlterEvent $event) {
    $entity = $event->getEntity();
    if ($entity->getEntityTypeId() == 'node') {
      $entity->uid = $this->currentUser->id();
    }
  }

  /**
   * Reacts to the replicate after save event.
   *
   * @param \Drupal\replicate\Events\AfterSaveEvent $event
   *   The replicate after save event.
   */
  public function replicateAfterSave(AfterSaveEvent $event) {
    $origin_node = \Drupal::routeMatch()->getParameter('node');
    $destination_node = $event->getEntity();

    // We can't access the origin node, or destination isn't node do nothing else.
    if (!$origin_node instanceof \Drupal\node\NodeInterface || $destination_node->getEntityTypeId() !== 'node') {
      return;
    }

    // Define the plugin in the format of group_node:[content type/bundle]
    $pluginId = 'group_node:' . $destination_node->getType();
    $group_contents = GroupContent::loadByEntity($origin_node);
    $group_ids = [];
    foreach ($group_contents as $group_content) {
      $group_ids[] = $group_content->getGroup()->id();
    }

    // Add relevant groups to the new, replicated, node.
    foreach($group_ids as $gid) {
      $group = Group::load($gid);
      $relation = $group->getContentByEntityId($pluginId, $destination_node->id());
      if (!$relation) {
        $group->addContent($destination_node, $pluginId);
      }
    }
  }

}
