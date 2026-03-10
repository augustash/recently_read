<?php

namespace Drupal\recently_read\Plugin\views\relationship;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\SessionManager;
use Drupal\views\Plugin\views\relationship\RelationshipPluginBase;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\Core\Config\CachedStorage;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a views relationship to recently read.
 *
 * @ViewsRelationship("recently_read_relationship")
 */
class RecentlyReadRelationship extends RelationshipPluginBase {

  /**
   * Drupal\Core\Session\AccountProxy definition.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected $currentUser;

  /**
   * Drupal\Core\PageCache\ResponsePolicy\KillSwitch definition.
   *
   * @var \Drupal\Core\PageCache\ResponsePolicy\KillSwitch
   */
  protected $killSwitch;

  /**
   * Drupal\Core\Config\CachedStorage definition.
   *
   * @var \Drupal\Core\Config\CachedStorage
   */
  protected $cachedStorage;

  /**
   * Drupal\Core\Session\SessionManager.
   *
   * @var \Drupal\Core\Session\SessionManager
   */
  protected $sessionManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * RecentlyReadRelationship constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $pluginDefinition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Session\AccountProxy $currentUser
   *   The current user service.
   * @param \Drupal\Core\PageCache\ResponsePolicy\KillSwitch $killSwitch
   *   The page_cache_kill_switch service.
   * @param \Drupal\Core\Config\CachedStorage $cachedStorage
   *   The config.storage service.
   * @param \Drupal\Core\Session\SessionManager $sessionManager
   *   The session_manager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    array $configuration,
    $pluginId,
    $pluginDefinition,
    AccountProxy $currentUser,
    KillSwitch $killSwitch,
    CachedStorage $cachedStorage,
    SessionManager $sessionManager,
    EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $pluginId, $pluginDefinition);

    $this->currentUser = $currentUser;
    $this->killSwitch = $killSwitch;
    $this->cachedStorage = $cachedStorage;
    $this->sessionManager = $sessionManager;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * RecentlyReadRelationship create function.
   */
  public static function create(ContainerInterface $container, array $configuration, $pluginId, $pluginDefinition): RecentlyReadRelationship {
    return new self(
      $configuration,
      $pluginId,
      $pluginDefinition,
      // Load the service required to construct this class.
      $container->get('current_user'),
      $container->get('page_cache_kill_switch'),
      $container->get('config.storage'),
      $container->get('session_manager'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defineOptions(): array {
    $options = parent::defineOptions();
    $options['bundles'] = ['default' => []];
    $options['current_user_related'] = ['default' => FALSE];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state): void {
    parent::buildOptionsForm($form, $form_state);

    $form['current_user_related'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Join only records which are related to the current user.'),
      '#default_value' => $this->options['current_user_related'],
    ];

    $entity_type = $this->definition['recently_read_type'];
    $typesOptions = FALSE;

    // Read the entity_type configuration and load the types.
    $types = $this->cachedStorage->read('recently_read.recently_read_type.' . $entity_type)['types'];

    // If types are enabled prepare the array for checkboxes options.
    if (isset($types) && !empty($types)) {
      $typesOptions = array_combine($types, $types);
    }

    if ($typesOptions) {
      $form['bundles'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Bundles'),
        '#default_value' => $this->options['bundles'],
        '#options' => $typesOptions,
      ];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function query(): void {
    $this->ensureMyTable();

    // Get base table and entity_type from relationship.
    $basetable = $this->definition['base_table'];
    $entity_type = $this->definition['recently_read_type'];

    $this->definition['extra'][] = [
      'field' => 'type',
      'value' => $entity_type,
    ];

    if ($this->options['current_user_related']) {
      // Add query to filter data if auth.user or anonymous.
      if ($this->currentUser->id() === 0) {
        // Disable page caching for anonymous users.
        $this->killSwitch->trigger();
        $this->definition['extra'][] = [
          'field' => 'session_id',
          'value' => $this->sessionManager->getId(),
        ];
      }
      else {
        $this->definition['extra'][] = [
          'field' => 'user_id',
          'value' => '***CURRENT_USER***',
        ];
      }
    }

    parent::query();

    // Filter by entity bundles selected while configuring the relationship.
    if (!empty(array_filter($this->options['bundles']))) {
      $entity_type_definition = $this->entityTypeManager->getDefinition($this->definition['entity_type']);
      $bundle_key = $entity_type_definition->getKey('bundle');
      $this->query->addWhere('recently_read', "{$basetable}.{$bundle_key}", array_filter(array_values($this->options['bundles'])), 'IN');
    }
  }

}
