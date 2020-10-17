<?php

namespace Drupal\A_MODULE\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Annotation\ViewsFilter;
use Drupal\views\Plugin\views\filter\FilterPluginBase;

/**
 * Filter by gruop context for administrative interfaces.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("user_group_content")
 */
class UserGroupContent extends FilterPluginBase {

  /**
   * No Admin summary necessary.
   *
   * @return string|void
   *   No description necessary.
   */
  public function adminSummary() {
    return '';
  }

  /**
   * There are no options for operators with this filter.
   *
   * @param $form
   *   The form render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   */
  protected function operatorForm(&$form, FormStateInterface $form_state) {}

  /**
   * This filter can't be exposed for user interaction.
   *
   * @return bool
   *   Return false for no interface.
   */
  public function canExpose() {
    return FALSE;
  }

  /**
   * See _node_access_where_sql() for a non-views query based implementation.
   */
  public function query() {
    $account = $this->view->getUser();
    if (!$account->hasPermission('bypass node access')) {
      $group_membership_service = \Drupal::service('group.membership_loader');
      $groups = [];
      $memberships = $group_membership_service->loadByUser($account);

      // Load all memberships a user has access to.
      foreach ($memberships as $membership) {
        $groups[] = $membership->getGroup();
      }
      $last = count($groups);
      $in_text = '';

      // Create comma separated list of group ids a user is in.
      foreach ($groups as $count => $gid) {
        if ($count + 1 < $last) {
          $in_text .= $gid->id() . ", ";
        }
        else {
          $in_text .= $gid->id();
        }
      }
      // Filter node ids by the group ids the content belongs to.
      $this->query->addWhere('', 'group_content_field_data_node_field_data.gid', $in_text, 'IN');
      $this->query->addWhere('AND', 'group_content_field_data_node_field_data.type', '%group_node%', 'LIKE');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    $contexts = parent::getCacheContexts();

    $contexts[] = 'user.node_grants:update';

    return $contexts;
  }

}
