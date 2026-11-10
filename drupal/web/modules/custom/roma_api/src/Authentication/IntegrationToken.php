<?php
namespace Drupal\roma_api\Authentication;

use Drupal\Core\Authentication\AuthenticationProviderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\HttpFoundation\Request;

final class IntegrationToken implements AuthenticationProviderInterface {
  public function __construct(private readonly EntityTypeManagerInterface $entities) {}
  public function applies(Request $request): bool {
    return str_starts_with($request->getPathInfo(), '/api/roma/v1/') && str_starts_with($request->headers->get('Authorization', ''), 'Bearer ');
  }
  public function authenticate(Request $request) {
    $expected = getenv('ROMA_INTEGRATION_TOKEN');
    $actual = substr($request->headers->get('Authorization', ''), 7);
    if (!$expected || strlen($expected) < 32 || !hash_equals($expected, $actual)) { return NULL; }
    $users = $this->entities->getStorage('user')->loadByProperties(['name' => 'roma_integration', 'status' => 1]);
    return $users ? reset($users) : NULL;
  }
}
