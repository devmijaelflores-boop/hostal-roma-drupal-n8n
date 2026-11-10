import { createChat } from '../assets/n8n-chat/chat.bundle.es.js';
const status = document.querySelector('#chat-connection');
try {
  const response = await fetch('/api/roma/chat/session', {credentials:'same-origin',headers:{Accept:'application/json'}});
  if (!response.ok) throw new Error('Sesión no disponible');
  const session = await response.json();
  createChat({
    webhookUrl:'/api/roma/chat', target:'#n8n-chat', mode:'window', loadPreviousSession:false, showWelcomeScreen:false,
    webhookConfig:{method:'POST',headers:{'X-ROMA-CSRF':session.csrf}},
    defaultLanguage:'es', initialMessages:['¡Hola! Soy el asistente de Golden Roma, un prototipo académico no oficial. Consulta precios, servicios o disponibilidad de la demo. Usa solo datos ficticios.'],
    i18n:{es:{title:'Asistente Golden Roma',subtitle:'Demo académica · Datos de Drupal mediante n8n',footer:'',getStarted:'Nueva consulta',inputPlaceholder:'Pregunta por precios, servicios o fechas…',closeButtonTooltip:'Cerrar chat'}},
    enableStreaming:false, allowFileUploads:false,
  });
  if (status) status.remove();
} catch {
  if (status) status.textContent='Chat en modo demostración sin conexión. Puedes consultar disponibilidad y solicitar una reserva desde los formularios.';
}
