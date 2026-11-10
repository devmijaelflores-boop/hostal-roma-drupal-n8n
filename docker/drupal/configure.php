<?php

// Configuración inicial exclusivamente de infraestructura, sin catálogo ni reservas.
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;

if (\Drupal::state()->get('roma.infrastructure_configured_v2')) {
  return;
}

// El perfil Standard de Drupal 11.4 no garantiza tipos de contenido iniciales.
if (!NodeType::load('page')) {
  NodeType::create(['type' => 'page', 'name' => 'Página básica', 'display_submitted' => FALSE])->save();
}
if (!FieldStorageConfig::loadByName('node', 'body')) {
  FieldStorageConfig::create(['field_name' => 'body', 'entity_type' => 'node', 'type' => 'text_with_summary'])->save();
}
if (!FieldConfig::loadByName('node', 'page', 'body')) {
  FieldConfig::create([
    'field_name' => 'body', 'entity_type' => 'node', 'bundle' => 'page',
    'label' => 'Contenido', 'settings' => ['display_summary' => TRUE, 'allowed_formats' => ['basic_html']],
  ])->save();
}
$displays = \Drupal::service('entity_display.repository');
$displays->getFormDisplay('node', 'page')->setComponent('body', ['type' => 'text_textarea_with_summary'])->save();
$displays->getViewDisplay('node', 'page')->setComponent('body', ['label' => 'hidden', 'type' => 'text_default'])->save();

$notice = 'Prototipo académico no oficial. Habitaciones, disponibilidad y tarifas de demostración';
\Drupal::configFactory()->getEditable('system.site')->set('slogan', $notice)->save();
\Drupal::configFactory()->getEditable('system.date')->set('timezone.default', 'America/Lima')->save();
\Drupal::configFactory()->getEditable('user.settings')->set('register', 'admin_only')->save();
$existing = \Drupal::entityQuery('node')->accessCheck(FALSE)->condition('type', 'page')
  ->condition('title', 'Sistema Web de Reservas y Atención Automatizada')->range(0, 1)->execute();
$page = $existing ? Node::load(reset($existing)) : Node::create([
  'type' => 'page',
  'title' => 'Sistema Web de Reservas y Atención Automatizada',
  'langcode' => 'es',
  'uid' => 1,
  'status' => 1,
]);
$page->set('body', [
    'value' => '<p>' . $notice . '</p><p>Proyecto académico independiente, no autorizado ni afiliado oficialmente al establecimiento de referencia en Juliaca, Puno, Perú.</p><p>Fase 1: infraestructura. El catálogo, las reservas y el chatbot todavía no están implementados.</p>',
    'format' => 'basic_html',
]);
$page->save();
\Drupal::configFactory()->getEditable('system.site')->set('page.front', '/node/' . $page->id())->save();
\Drupal::state()->set('roma.infrastructure_configured_v2', TRUE);
