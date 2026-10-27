/* Interações visuais progressivas, com pausa manual e movimento reduzido. */
(() => {
  const media=window.matchMedia('(prefers-reduced-motion: reduce)');
  let paused=media.matches;
  try{paused=paused||localStorage.getItem('atlas-motion')==='paused';}catch{}
  document.documentElement.classList.toggle('motion-paused',paused);
  const toggle=document.createElement('button');toggle.type='button';toggle.className='motion-control';
  const updateToggle=()=>{toggle.setAttribute('aria-pressed',String(paused));toggle.setAttribute('aria-label',paused?'Ativar animações decorativas':'Pausar animações decorativas');toggle.innerHTML=`<span aria-hidden="true">${paused?'▷':'Ⅱ'}</span><span class="motion-label">${paused?'Ativar animações':'Pausar animações'}</span>`;};
  toggle.onclick=()=>{paused=!paused;document.documentElement.classList.toggle('motion-paused',paused);try{localStorage.setItem('atlas-motion',paused?'paused':'active');}catch{}updateToggle();};updateToggle();document.body.append(toggle);
  const onPreference=e=>{if(e.matches){paused=true;document.documentElement.classList.add('motion-paused');updateToggle();}};media.addEventListener?.('change',onPreference);
  const gauge=document.createElement('div');gauge.className='voyage-progress';gauge.setAttribute('aria-hidden','true');document.body.append(gauge);
  let scheduled=false;
  function progress(){const range=document.documentElement.scrollHeight-window.innerHeight;gauge.style.setProperty('--voyage-progress',range>0?Math.min(1,window.scrollY/range):0);scheduled=false;}
  addEventListener('scroll',()=>{if(!scheduled){scheduled=true;requestAnimationFrame(progress);}},{passive:true});progress();
  const sparkLayer=document.createElement('div');sparkLayer.className='sea-sparks';sparkLayer.setAttribute('aria-hidden','true');sparkLayer.innerHTML=Array.from({length:9},(_,i)=>`<i style="--x:${8+i*11}%;--delay:${-i*2.3}s;--duration:${18+(i%4)*4}s"></i>`).join('');document.body.prepend(sparkLayer);
  let revealObserver=null;
  if('IntersectionObserver' in window){revealObserver=new IntersectionObserver(entries=>{for(const entry of entries){if(entry.isIntersecting){entry.target.classList.add('is-visible');revealObserver.unobserve(entry.target);}}},{threshold:.08});}
  function decorate(root=document){
    root.querySelectorAll('.catalog-card:not([data-voyage]),.tcg-card:not([data-voyage]),.treasure-poster:not([data-voyage]),.info-panel:not([data-voyage]),.story-copy:not([data-voyage]),.join-section:not([data-voyage])').forEach((el,i)=>{el.dataset.voyage='true';el.style.setProperty('--entrance-delay',Math.min(i%12,7)*35+'ms');if(revealObserver&&!paused){el.classList.add('voyage-reveal');revealObserver.observe(el);}});
    root.querySelectorAll('img:not([data-image-state])').forEach(img=>{img.dataset.imageState='watching';const loaded=()=>{img.classList.add('image-ready');img.closest('.tile-media')?.classList.add('art-ready');};if(img.complete&&img.naturalWidth)loaded();else img.addEventListener('load',loaded,{once:true});});
  }
  const ticker=document.querySelector('.ticker');if(ticker&&!ticker.querySelector('.ticker-track')){const content=ticker.innerHTML;ticker.innerHTML='<div class="ticker-track">'+content+' ✦ '+content+' ✦ </div>';}
  const welcome=document.querySelector('.catalog-welcome>div');if(welcome&&window.ATLAS_IMAGE_LIBRARY){const status=document.createElement('span');status.className='gallery-status';const count=window.ATLAS_IMAGE_LIBRARY.localCount.toLocaleString('pt-BR');status.innerHTML='<i></i>'+count+' ilustrações incluídas no projeto';welcome.append(status);}
  decorate();
  const observer=new MutationObserver(records=>{if(records.some(r=>r.type==='childList'&&r.addedNodes.length))decorate();});
  [document.querySelector('#catalog-grid'),document.querySelector('#card-grid'),document.querySelector('#card-page'),document.querySelector('#lesson-article'),document.querySelector('#pool-grid')].filter(Boolean).forEach(el=>observer.observe(el,{childList:true,subtree:true}));
  const fine=window.matchMedia('(hover: hover) and (pointer: fine)');
  let tiltTarget=null;
  document.addEventListener('pointermove',e=>{if(paused||!fine.matches||e.pointerType==='touch')return;const target=e.target.closest('.tile-media,.card-picture,.zoom-card');if(!target)return;const rect=target.getBoundingClientRect();if(!rect.width||!rect.height)return;const x=(e.clientX-rect.left)/rect.width,y=(e.clientY-rect.top)/rect.height;target.style.setProperty('--tilt-x',((.5-y)*7).toFixed(2)+'deg');target.style.setProperty('--tilt-y',((x-.5)*7).toFixed(2)+'deg');target.style.setProperty('--shine-x',(x*100).toFixed(1)+'%');target.style.setProperty('--shine-y',(y*100).toFixed(1)+'%');if(tiltTarget&&tiltTarget!==target)resetTilt(tiltTarget);tiltTarget=target;},{passive:true});
  function resetTilt(target){target.style.removeProperty('--tilt-x');target.style.removeProperty('--tilt-y');target.style.removeProperty('--shine-x');target.style.removeProperty('--shine-y');}
  document.addEventListener('pointerout',e=>{const target=e.target.closest('.tile-media,.card-picture,.zoom-card');if(target&&!target.contains(e.relatedTarget)){resetTilt(target);if(tiltTarget===target)tiltTarget=null;}});
  document.addEventListener('click',e=>{const favorite=e.target.closest('[data-favorite]');if(favorite){const current=[...document.querySelectorAll('[data-favorite]')].find(b=>b.dataset.favorite===favorite.dataset.favorite);const card=current?.closest('.catalog-card,.tcg-card');if(card){card.classList.remove('treasure-saved');requestAnimationFrame(()=>card.classList.add('treasure-saved'));}}});
})();
