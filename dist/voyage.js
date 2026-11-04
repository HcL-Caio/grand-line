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
  // A Log Pose marks the course without adding another navigation control.
  const hero=document.querySelector('.hero');
  const heroArt=document.querySelector('.hero-art');
  if(hero && heroArt){
    const pose=document.createElement('div');
    pose.className='log-pose';pose.setAttribute('aria-hidden','true');
    pose.innerHTML='<span class="pose-north">N</span><span class="pose-west">W</span><span class="pose-east">E</span><span class="pose-south">S</span><i class="pose-needle"></i><span class="pose-caption">LOG POSE · NEW WORLD</span>';
    heroArt.prepend(pose);
    const sea=document.createElement('div');sea.className='grand-line-sea';sea.setAttribute('aria-hidden','true');
    sea.innerHTML='<i></i><i></i><i></i>';hero.append(sea);
  }
  const header=document.querySelector('header');
  const horizon=('IntersectionObserver' in window ? new IntersectionObserver(entries=>header?.classList.toggle('past-horizon',!entries[0].isIntersecting)) : null);
  if(hero)horizon?.observe(hero);
  decorate();
  const observer=new MutationObserver(records=>{if(records.some(r=>r.type==='childList'&&r.addedNodes.length))decorate();});
  [document.querySelector('#catalog-grid'),document.querySelector('#card-grid'),document.querySelector('#card-page'),document.querySelector('#lesson-article'),document.querySelector('#pool-grid')].filter(Boolean).forEach(el=>observer.observe(el,{childList:true,subtree:true}));
  const fine=window.matchMedia('(hover: hover) and (pointer: fine)');
  let tiltTarget=null;
  document.addEventListener('pointermove',e=>{if(paused||!fine.matches||e.pointerType==='touch')return;const target=e.target.closest('.tile-media,.card-picture,.zoom-card,.spotlight-art');if(!target)return;const rect=target.getBoundingClientRect();if(!rect.width||!rect.height)return;const x=Math.max(0,Math.min(1,(e.clientX-rect.left)/rect.width)),y=Math.max(0,Math.min(1,(e.clientY-rect.top)/rect.height));target.style.setProperty('--tilt-x',((.5-y)*7).toFixed(2)+'deg');target.style.setProperty('--tilt-y',((x-.5)*7).toFixed(2)+'deg');target.style.setProperty('--shine-x',(x*100).toFixed(1)+'%');target.style.setProperty('--shine-y',(y*100).toFixed(1)+'%');if(tiltTarget&&tiltTarget!==target)resetTilt(tiltTarget);tiltTarget=target;},{passive:true});
  const clearTilt=()=>{if(tiltTarget)resetTilt(tiltTarget);tiltTarget=null;};
  toggle.addEventListener('click',clearTilt);media.addEventListener?.('change',clearTilt);
  function resetTilt(target){target.style.removeProperty('--tilt-x');target.style.removeProperty('--tilt-y');target.style.removeProperty('--shine-x');target.style.removeProperty('--shine-y');}
  document.addEventListener('pointerout',e=>{const target=e.target.closest('.tile-media,.card-picture,.zoom-card,.spotlight-art');if(target&&!target.contains(e.relatedTarget)){resetTilt(target);if(tiltTarget===target)tiltTarget=null;}});
  document.addEventListener('click',e=>{const favorite=e.target.closest('[data-favorite]');if(favorite){const current=[...document.querySelectorAll('[data-favorite]')].find(b=>b.dataset.favorite===favorite.dataset.favorite);const card=current?.closest('.catalog-card,.tcg-card');if(card){card.classList.remove('treasure-saved');requestAnimationFrame(()=>card.classList.add('treasure-saved'));}}});

  // A manga impact rewards deliberate actions, never blocks a click.
  let lastImpact=0;
  document.addEventListener('click',event=>{
    const action=event.target.closest('.featured-card,[data-favorite],.favorite-btn,.tile-favorite');
    if(!action||paused||document.hidden||Date.now()-lastImpact<800)return;
    lastImpact=Date.now();
    const rect=action.getBoundingClientRect();
    const impact=document.createElement('span');impact.className='don-impact';
    impact.setAttribute('aria-hidden','true');impact.textContent='DON!!';
    impact.style.left=Math.max(65,Math.min(innerWidth-65,rect.left+rect.width/2))+'px';
    impact.style.top=Math.max(55,Math.min(innerHeight-55,rect.top+rect.height/2))+'px';
    document.body.append(impact);setTimeout(()=>impact.remove(),850);
  });
  if(heroArt){
    const haki=document.createElement('div');haki.className='haki-aura';haki.setAttribute('aria-hidden','true');
    haki.innerHTML='<i></i><i></i><i></i><span>ドン!!</span>';heroArt.prepend(haki);
    heroArt.addEventListener('pointerenter',()=>{if(!paused)heroArt.classList.add('haki-awake');});
    heroArt.addEventListener('pointerleave',()=>heroArt.classList.remove('haki-awake'));
    heroArt.addEventListener('focusin',()=>{if(!paused)heroArt.classList.add('haki-awake');});
    heroArt.addEventListener('focusout',e=>{if(!heroArt.contains(e.relatedTarget))heroArt.classList.remove('haki-awake');});
  }
  const library=document.querySelector('#biblioteca');
  if(library){
    const route=document.createElement('section');route.className='new-world-route container-xl';
    route.setAttribute('aria-labelledby','voyage-route-title');
    route.innerHTML=`<div class="route-heading"><span class="eyebrow gold">TRACE SUA PRÓPRIA ROTA</span><h2 id="voyage-route-title">O próximo Rei dos Piratas<br>começa por aqui.</h2><p>Conheça as cartas. Encontre seu líder. Reúna sua tripulação.</p></div><div class="route-map"><svg viewBox="0 0 1000 180" preserveAspectRatio="none" aria-hidden="true"><path class="route-base" d="M80 95 Q280 -20 500 95 T920 95"/><path class="route-current" d="M80 95 Q280 -20 500 95 T920 95"/></svg><a href="aprender.html" class="route-island"><span class="island-number">01</span><span class="island-mark" aria-hidden="true">⚓</span><b>Primeira viagem</b><small>Aprenda a jogar</small><span class="island-arrow" aria-hidden="true">↗</span></a><a href="home.html" class="route-island"><span class="island-number">02</span><span class="island-mark" aria-hidden="true">✦</span><b>Caça ao tesouro</b><small>Explore o catálogo</small><span class="island-arrow" aria-hidden="true">↗</span></a><a href="decks.html" class="route-island"><span class="island-number">03</span><span class="island-mark straw-hat" aria-hidden="true"></span><b>Sua tripulação</b><small>Monte seu deck</small><span class="island-arrow" aria-hidden="true">↗</span></a></div>`;
    library.before(route);
  }
})();
