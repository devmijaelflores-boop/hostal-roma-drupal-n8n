(() => {
 'use strict';
 const reduced=window.matchMedia('(prefers-reduced-motion: reduce)');
 // Todo el contenido sigue visible si JavaScript o las animaciones fallan.
 if(!reduced.matches&&'IntersectionObserver' in window){
  const observer=new IntersectionObserver(entries=>{for(const entry of entries){if(!entry.isIntersecting)continue;observer.unobserve(entry.target);if(!reduced.matches&&entry.target.animate)entry.target.animate([{opacity:0,transform:'translateY(18px)'},{opacity:1,transform:'translateY(0)'}],{duration:550,easing:'cubic-bezier(.2,.7,.3,1)'});}},{threshold:.08});
  document.querySelectorAll('.welcome-visual,.welcome-heading,.welcome-copy,.room-card,.hotel-story,.services-grid article,.location-copy,.hotel-map').forEach(el=>observer.observe(el));
 }
 const video=document.querySelector('#hero-video'),control=document.querySelector('#hero-video-control');if(!video||!control)return;
 const hero=video.closest('.hero'),credit=hero.querySelector('[data-video-credit]');let requested=false,userPaused=false,visible=true,blocked=false;
 const english=()=>document.documentElement.lang==='en';
 function labels(){const playing=!video.paused;const text=playing?'Pausar video':(requested?'Reproducir video':'Ver video');control.textContent=english()?(playing?'Pause video':requested?'Play video':'Watch video'):text;control.setAttribute('aria-label',control.textContent);control.setAttribute('data-es-aria-label',text);control.setAttribute('aria-pressed',String(playing));}
 function pause(){video.pause();labels();}
 async function play(){if(blocked)return;if(!requested){requested=true;video.src=video.dataset.src;video.load();}video.muted=true;try{await video.play();hero.dataset.videoActive='true';video.classList.add('is-playing');if(credit)credit.textContent=english()?'Sample video · Mixkit':'Video de muestra · Mixkit';}catch{pause();}labels();}
 control.hidden=false;labels();
 control.addEventListener('click',()=>{if(!video.paused){userPaused=true;pause();}else{userPaused=false;play();}});
 video.addEventListener('error',()=>{blocked=true;pause();delete hero.dataset.videoActive;video.classList.remove('is-playing');control.hidden=true;if(credit)credit.textContent=english()?'Image · Mixkit':'Imagen · Mixkit';});
 hero.addEventListener('gallery-change',()=>{userPaused=true;pause();video.classList.remove('is-playing');delete hero.dataset.videoActive;if(credit)credit.textContent=hero.querySelector('[data-gallery-image]').src.includes('hotel-poster')?'Imagen · Mixkit':'Fotografía · Unsplash';});
 document.addEventListener('visibilitychange',()=>{if(document.hidden)pause();else if(requested&&visible&&!userPaused&&!reduced.matches)play();});
 if('IntersectionObserver' in window)new IntersectionObserver(entries=>{visible=entries[0].isIntersecting;if(!visible)pause();else if(requested&&!userPaused&&!document.hidden&&!reduced.matches)play();},{threshold:.1}).observe(hero);
 reduced.addEventListener('change',()=>{if(reduced.matches){userPaused=true;pause();}});
 window.addEventListener('hotel-language-change',labels);
 // Carga después de la foto. En móvil, ahorro de datos o movimiento reducido, solo bajo petición.
 const connection=navigator.connection;const constrained=connection?.saveData||['slow-2g','2g'].includes(connection?.effectiveType);
 if(!reduced.matches&&!constrained&&window.innerWidth>=768){const start=()=>window.setTimeout(()=>{if(visible&&!document.hidden&&!userPaused&&!reduced.matches)play();},1200);if(document.readyState==='complete')start();else window.addEventListener('load',start,{once:true});}
})();
