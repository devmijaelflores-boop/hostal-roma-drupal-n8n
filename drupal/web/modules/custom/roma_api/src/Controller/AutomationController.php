<?php
namespace Drupal\roma_api\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
final class AutomationController extends ApiController {
  public function tick(Request $request): JsonResponse {
    $this->guard($request, 'roma.automation', 15);
    $shop = \Drupal::hasService('roma_shop.store') ? \Drupal::service('roma_shop.store')->reconcile() : [];
    return $this->respond(['demo'=>TRUE,'result'=>\Drupal::service('roma_api.automation')->tick(),'commerce'=>$shop]);
  }
}
