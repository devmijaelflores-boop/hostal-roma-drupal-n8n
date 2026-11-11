<?php
namespace Drupal\roma_api\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/** Puente de mismo origen: el navegador nunca recibe secretos de integración. */
final class ChatController extends ApiController {
  public function session(Request $request): JsonResponse {
    $this->guard($request, 'roma.chat.session', 60);
    $request->getSession()->start();
    $request->getSession()->set('roma_chat', TRUE);
    return $this->respond(['csrf'=>\Drupal::csrfToken()->get('roma_chat')]);
  }
  public function chat(Request $request): JsonResponse {
    $this->guard($request, 'roma.chat', 25);
    if (!\Drupal::csrfToken()->validate($request->headers->get('X-ROMA-CSRF', ''), 'roma_chat')) { return $this->respond(['error'=>'Sesión inválida. Recarga la página.'], 403); }
    $body = $this->body($request, TRUE);
    if (($body['action'] ?? '') === 'loadPreviousSession') { return $this->respond(['data'=>[]]); }
    $input = trim((string) ($body['chatInput'] ?? ''));
    if (!$input || mb_strlen($input) > 1000) { return $this->respond(['output'=>'Escribe una consulta de hasta 1000 caracteres.']); }
    try {
      $path = getenv('ROMA_CHAT_MODE') === 'ai' ? 'roma-chat-ai' : 'roma-chat';
      $response = \Drupal::httpClient()->post('http://n8n:5678/webhook/' . $path, [
        'timeout'=>20, 'connect_timeout'=>3, 'headers'=>['X-ROMA-Webhook'=>getenv('ROMA_N8N_WEBHOOK_TOKEN')],
        'json'=>['chatInput'=>$input,'action'=>'sendMessage'],
      ]);
      $data = json_decode((string) $response->getBody(), TRUE, 16, JSON_THROW_ON_ERROR);
      if (!is_array($data) || !is_string($data['output'] ?? NULL)) { throw new \RuntimeException('Respuesta no válida.'); }
      return $this->respond(['output'=>$data['output'], 'demo'=>TRUE, 'ai'=>!empty($data['ai']), 'provider'=>$data['provider'] ?? NULL, 'fallback'=>!empty($data['fallback'])]);
    }
    catch (\Throwable) {
      try {
        $response=\Drupal::httpClient()->post('http://drupal/api/roma/v1/assistant', ['timeout'=>4,'connect_timeout'=>2,'headers'=>['Authorization'=>'Bearer '.getenv('ROMA_INTEGRATION_TOKEN')],'json'=>['chatInput'=>$input]]);
        $data=json_decode((string)$response->getBody(),TRUE,16,JSON_THROW_ON_ERROR);
        if (is_string($data['output'] ?? NULL)) { return $this->respond(['output'=>$data['output'],'demo'=>TRUE,'ai'=>FALSE,'fallback'=>TRUE]); }
      } catch (\Throwable) {}

      return $this->respond(['output'=>'El asistente de prueba no está disponible en este momento. No se ha registrado ninguna reserva. Puedes usar [el formulario de reservas](/reservar) o [consultar disponibilidad](/disponibilidad).', 'demo'=>TRUE, 'unavailable'=>TRUE]);
    }
  }
}
