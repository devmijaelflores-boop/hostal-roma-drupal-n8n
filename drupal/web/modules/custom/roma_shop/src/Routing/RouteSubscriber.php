<?php
namespace Drupal\roma_shop\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/** Mantiene un único carrito con fechas y controles CSRF anónimos explícitos. */
final class RouteSubscriber extends RouteSubscriberBase {
  protected function alterRoutes(RouteCollection $collection): void {
    if ($route = $collection->get('commerce_cart.page')) {
      $route->setDefault('_controller','\Drupal\roma_shop\Controller\ShopController::cartRedirect');
    }
  }
}
