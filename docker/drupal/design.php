<?php

// Activación idempotente del tema Drupal de la fase 2.
\Drupal::service('extension.list.theme')->reset();
\Drupal::service('theme_installer')->install(['roma_elegante']);
\Drupal::configFactory()->getEditable('system.theme')->set('default', 'roma_elegante')->save();
if (!\Drupal::state()->get('roma.design_configured_v1')) {
  $front = \Drupal::config('system.site')->get('page.front');
  if (preg_match('@^/node/(\d+)$@', $front ?? '', $matches)) {
    $node = \Drupal::entityTypeManager()->getStorage('node')->load($matches[1]);
    if ($node && $node->getTitle() === 'Sistema Web de Reservas y Atención Automatizada') {
      $node->setTitle('Tu pausa en el altiplano')->save();
    }
  }
  \Drupal::state()->set('roma.design_configured_v1', TRUE);
}
