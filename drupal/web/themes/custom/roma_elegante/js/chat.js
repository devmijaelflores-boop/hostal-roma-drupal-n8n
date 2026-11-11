const launcher = document.querySelector('#roma-chat-launch');
launcher?.addEventListener('click', async () => {
launcher.disabled=true;
launcher.textContent='Abriendo…';
const status = document.querySelector('#chat-connection');
try {
  const {createChat} = await import('../assets/n8n-chat/chat.bundle.es.js');
  const response = await fetch('/api/roma/chat/session', {credentials:'same-origin',headers:{Accept:'application/json'}});
  if (!response.ok) throw new Error('Sesión no disponible');
  const session = await response.json();
  createChat({
    webhookUrl:'/api/roma/chat', target:'#n8n-chat', mode:'window', loadPreviousSession:false, showWelcomeScreen:false,
    webhookConfig:{method:'POST',headers:{'X-ROMA-CSRF':session.csrf}},
    defaultLanguage:'es', initialMessages:[`¡Hola! Soy el asistente de ${launcher.dataset.hotel}. Consulta precios, servicios o fechas para tu estadía.`],
    i18n:{es:{title:`Asistente ${launcher.dataset.hotel}`,subtitle:'Precios, habitaciones y consultas',footer:'',getStarted:'Nueva consulta',inputPlaceholder:'Pregunta por precios, servicios o fechas…',closeButtonTooltip:'Cerrar chat'}},
    enableStreaming:false, allowFileUploads:false,
  });
  launcher.hidden=true;
  document.querySelector('#n8n-chat .chat-window-toggle')?.click();
  if (status) status.remove();
} catch {
  launcher.disabled=false;launcher.textContent='Reintentar chat';
  if(status)status.hidden=false;
  if (status) status.textContent='Chat en modo demostración sin conexión. Puedes consultar disponibilidad y solicitar una reserva desde los formularios.';
}

});
