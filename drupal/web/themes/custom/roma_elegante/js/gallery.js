(function (Drupal, once) {
 'use strict';
 Drupal.behaviors.hotelGallery={attach(context){
  const viewer=document.querySelector('#photo-viewer');
  once('hotel-photo-viewer','#photo-viewer',context).forEach(dialog=>{
   dialog.querySelector('button').addEventListener('click',()=>dialog.close());
   dialog.addEventListener('click',e=>{if(e.target===dialog)dialog.close();});
  });
  once('hotel-gallery','[data-gallery]',context).forEach(gallery=>{
   let images;try{images=JSON.parse(gallery.dataset.images);}catch{return;}
   if(!Array.isArray(images)||!images.length)return;
   const img=gallery.querySelector('[data-gallery-image]');const count=gallery.querySelector('[data-gallery-count]');let index=0;
   const show=step=>{index=(index+step+images.length)%images.length;gallery.dispatchEvent(new CustomEvent('gallery-change'));const previous=img.cloneNode();previous.removeAttribute('data-gallery-image');previous.setAttribute('aria-hidden','true');previous.classList.add('gallery-transition');gallery.querySelector('.gallery-transition')?.remove();img.after(previous);const clear=()=>{if(window.matchMedia('(prefers-reduced-motion: reduce)').matches||!previous.animate){previous.remove();return;}const animation=previous.animate([{opacity:1},{opacity:0}],{duration:650,easing:'ease-out'});animation.onfinish=()=>previous.remove();};img.addEventListener('load',clear,{once:true});img.addEventListener('error',()=>previous.remove(),{once:true});img.src=images[index];count.textContent=`${index+1} / ${images.length}`;gallery.querySelectorAll('[data-slide-index]').forEach(dot=>dot.setAttribute('aria-pressed',String(Number(dot.dataset.slideIndex)===index)));};
   count.textContent=`1 / ${images.length}`;
   if(images.length===1){gallery.querySelectorAll('[data-gallery-prev],[data-gallery-next],[data-gallery-play]').forEach(button=>button.hidden=true);}
   const play=gallery.querySelector('[data-gallery-play]');
   if(play&&images.length>1){
    const dots=document.createElement('div');dots.className='gallery-dots';
    images.forEach((url,i)=>{const dot=document.createElement('button');dot.type='button';dot.dataset.slideIndex=String(i);dot.setAttribute('aria-label',`Foto ${i+1}`);dot.setAttribute('aria-pressed',String(i===0));dot.addEventListener('click',()=>show(i-index));dots.append(dot);});
    play.before(dots);
    const reduced=window.matchMedia('(prefers-reduced-motion: reduce)');let paused=reduced.matches,hover=false,focused=false;
    const update=()=>{play.textContent=paused?'▶':'Ⅱ';play.setAttribute('aria-pressed',String(paused));const label=paused?'Reproducir carrusel':'Pausar carrusel';play.setAttribute('aria-label',document.documentElement.lang==='en'?(paused?'Play slideshow':'Pause slideshow'):label);play.removeAttribute('data-es-aria-label');};
    play.addEventListener('click',()=>{paused=!paused;update();});
    gallery.addEventListener('mouseenter',()=>{hover=true;});gallery.addEventListener('mouseleave',()=>{hover=false;});
    gallery.addEventListener('focusin',()=>{focused=true;});gallery.addEventListener('focusout',()=>{focused=false;});
    reduced.addEventListener('change',()=>{paused=reduced.matches;update();});
    window.addEventListener('hotel-language-change',update);update();
    window.setInterval(()=>{if(!gallery.dataset.videoActive&&!paused&&!hover&&!focused&&!document.hidden&&!viewer?.open)show(1);},5000);
   }
   gallery.querySelector('[data-gallery-prev]').addEventListener('click',()=>show(-1));
   gallery.querySelector('[data-gallery-next]').addEventListener('click',()=>show(1));
   gallery.querySelector('[data-gallery-expand]').addEventListener('click',()=>{if(!viewer)return;const photo=viewer.querySelector('img');photo.src=images[index];photo.alt=img.alt;viewer.showModal();});
   gallery.querySelector('.gallery-controls').addEventListener('keydown',event=>{if(event.key==='ArrowRight'||event.key==='ArrowLeft'){event.preventDefault();show(event.key==='ArrowRight'?1:-1);}});
   let start=null;
   gallery.addEventListener('pointerdown',event=>{if(event.target.closest('button,a,input,select'))return;start={x:event.clientX,y:event.clientY};});
   gallery.addEventListener('pointerup',event=>{if(!start)return;const dx=event.clientX-start.x,dy=event.clientY-start.y;start=null;if(Math.abs(dx)>50&&Math.abs(dx)>Math.abs(dy)*1.5)show(dx<0?1:-1);});
   gallery.addEventListener('pointercancel',()=>{start=null;});
   // Image buttons also support touch swipes without opening the enlarged view.
   const imageButton=gallery.querySelector('.gallery-image-button');let touch=null,swiped=false;
   imageButton?.addEventListener('touchstart',e=>{touch={x:e.touches[0].clientX,y:e.touches[0].clientY};swiped=false;},{passive:true});
   imageButton?.addEventListener('touchend',e=>{if(!touch)return;const dx=e.changedTouches[0].clientX-touch.x,dy=e.changedTouches[0].clientY-touch.y;touch=null;if(Math.abs(dx)>50&&Math.abs(dx)>Math.abs(dy)*1.5){swiped=true;show(dx<0?1:-1);}},{passive:true});
   imageButton?.addEventListener('click',e=>{if(swiped){e.stopImmediatePropagation();swiped=false;}},true);
  });
 }};
})(Drupal, once);
