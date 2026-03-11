<?php

namespace Drupal\recently_read\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\recently_read\RecentlyReadService;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class AjaxController.
 *
 * @package Drupal\recently_read\Controller
 */
class AjaxController extends ControllerBase {

  /**
   * The recently read service.
   *
   * @var \Drupal\recently_read\RecentlyReadService
   */
  protected $recentlyReadService;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * AjaxController constructor.
   *
   * @param \Drupal\recently_read\RecentlyReadService $recentlyReadService
   *   The recently read service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   */
  public function __construct(RecentlyReadService $recentlyReadService, RendererInterface $renderer) {
    $this->recentlyReadService = $recentlyReadService;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('recently_read'),
      $container->get('renderer')
    );
  }

  /**
   * Callback for /ajax/recently-read/insert/{entity_type}/{entity}.
   *
   * @param string $entity_type
   *   The entity type.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity that is currently read.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   An empty response object.
   */
  public function insert(string $entity_type, EntityInterface $entity): Response {
    $this->recentlyReadService->insertEntity($entity);
    return new Response('');
  }

  /**
   * Renders products by IDs (from localStorage).
   *
   * @param string $ids
   *   Comma-separated product IDs.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response with the rendered HTML.
   */
  public function products(string $ids): JsonResponse {
    $ids = array_filter(array_map('intval', explode(',', $ids)));
    $ids = array_slice($ids, 0, 10);

    if (empty($ids)) {
      return new JsonResponse(['html' => '']);
    }

    $storage = $this->entityTypeManager()->getStorage('commerce_product');
    $products = $storage->loadMultiple($ids);

    if (empty($products)) {
      return new JsonResponse(['html' => '']);
    }

    // Maintain the order from the request (most recently viewed first).
    $ordered = [];
    foreach ($ids as $id) {
      if (isset($products[$id])) {
        $ordered[$id] = $products[$id];
      }
    }

    $viewBuilder = $this->entityTypeManager()->getViewBuilder('commerce_product');
    $build = [
      '#prefix' => '<div class="view recently-viewed"><header><h2>Recently Viewed Products</h2></header><div class="inner">',
      '#suffix' => '</div></div>',
      'content' => $viewBuilder->viewMultiple($ordered, 'teaser'),
    ];

    $html = $this->renderer->renderRoot($build);
    return new JsonResponse(['html' => (string) $html]);
  }

  /**
   * Renders a recently read view display via AJAX.
   *
   * @param string $view_id
   *   The view ID.
   * @param string $display_id
   *   The view display ID.
   * @param string $exclude_id
   *   An entity ID to exclude from the view results.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response with the rendered HTML.
   */
  public function view(string $view_id, string $display_id, string $exclude_id = '0'): JsonResponse {
    $view = Views::getView($view_id);
    if (!$view || !$view->access($display_id)) {
      return new JsonResponse(['html' => ''], 403);
    }

    $view->setDisplay($display_id);
    $args = $exclude_id !== '0' ? [$exclude_id] : [];
    $view->setArguments($args);
    $view->execute();

    if (empty($view->result)) {
      return new JsonResponse(['html' => '']);
    }

    $render = $view->buildRenderable($display_id, $args);
    $html = $this->renderer->renderRoot($render);

    return new JsonResponse(['html' => (string) $html]);
  }

}
