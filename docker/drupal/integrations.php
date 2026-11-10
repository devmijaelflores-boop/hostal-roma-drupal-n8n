<?php
// Usuario de servicio sin acceso al panel y sin contraseña publicada.
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
$role = Role::load('roma_integration') ?: Role::create(['id' => 'roma_integration', 'label' => 'Integración n8n de demostración']);
foreach (['use roma integration', 'run roma automation'] as $permission) { $role->grantPermission($permission); }
$role->save();
$users = \Drupal::entityTypeManager()->getStorage('user')->loadByProperties(['name' => 'roma_integration']);
if (!$users) { User::create(['name' => 'roma_integration', 'mail' => 'integration@example.invalid', 'pass' => bin2hex(random_bytes(48)), 'status' => 1, 'roles' => ['roma_integration']])->save(); }

\Drupal::configFactory()->getEditable('system.site')->set('name', 'Golden Roma — Prototipo académico')->save();
