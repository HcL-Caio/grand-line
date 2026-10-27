(() => {
  const $ = (s, root = document) => root.querySelector(s);
  const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const normalize = value => String(value).normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
  function read(key, fallback) { try { return JSON.parse(localStorage.getItem(key)) ?? fallback; } catch { return fallback; } }
  function write(key, value) { try { localStorage.setItem(key, JSON.stringify(value)); return true; } catch { return false; } }
  function toast(message) { let el = $('#toast'); if (!el) { el = document.createElement('div'); el.id = 'toast'; el.setAttribute('role', 'status'); document.body.append(el); } el.textContent = message; el.classList.add('show'); clearTimeout(window.atlasToast); window.atlasToast = setTimeout(() => el.classList.remove('show'), 3000); }
  const colors = {Red:['Vermelho','#dc655c'],Green:['Verde','#70b68d'],Blue:['Azul','#659ecb'],Purple:['Roxo','#ac84cf'],Black:['Preto','#a3a9ac'],Yellow:['Amarelo','#e7c96c']};
  const types = {LEADER:'Líder',CHARACTER:'Personagem',EVENT:'Evento',STAGE:'Cenário',DON:'DON!!'};
  const sampleIds = ['OP01-001','OP01-002','OP01-003','OP01-004','OP01-060','OP01-061','OP01-062','OP01-091','OP01-094','OP01-006','OP01-016','OP01-025'];
  const favoriteIds = () => { const data = read('atlas-favorites', []); return Array.isArray(data) ? data.filter(x => typeof x === 'string') : []; };
  function favorite(id) { let list = favoriteIds(); const added = !list.includes(id); list = added ? [...list, id] : list.filter(x => x !== id); if (!write('atlas-favorites', list)) { toast('Este navegador não permite salvar favoritos.'); return; } toast(added ? 'Carta adicionada aos favoritos.' : 'Carta removida dos favoritos.'); document.dispatchEvent(new CustomEvent('atlas:favorites')); }
  const image = card => window.ATLAS_IMAGE_LIBRARY?.images?.[card.uid] || (sampleIds.includes(card.uid) ? `assets/${card.uid}.png` : card.image);
  const digits = value => /^\d+$/.test(String(value).trim()) ? Number(value) : null;
  function tile(card) {
    const palette = card.color.split('/').map(c => colors[c]?.[0] || c).join(' / ') || 'Sem cor';
    const details = window.AtlasDescriptions?.describe(card);
    const trait = card.type === 'DON' ? 'RECURSO' : card.type === 'LEADER' ? `♡ ${esc(card.life ?? '—')}` : `DON!! ${esc(card.cost ?? '—')}`;
    const power = digits(card.power), counter = digits(card.counter);
    return `<article class="catalog-card" data-rarity="${esc(card.rarity)}" style="--card-accent:${colors[card.color.split('/')[0]]?.[1] || '#e9bc65'}"><div class="tile-media"><a href="carta.html?id=${encodeURIComponent(card.uid)}" class="tile-image-link" aria-label="Ver ${esc(card.name)}, ${esc(card.id)}"><img src="${esc(image(card))}" data-original="${esc(card.image)}" alt="${esc(card.name)} — ${esc(card.uid)}" width="400" height="560" loading="lazy" decoding="async" referrerpolicy="no-referrer"><span class="image-fallback" hidden><b>${esc(card.name)}</b><small>${esc(card.uid)}</small><span>Ilustração indisponível</span></span></a><span class="foil-sheen" aria-hidden="true"></span><button class="tile-favorite" data-favorite="${esc(card.id)}" aria-pressed="${favoriteIds().includes(card.id)}" aria-label="Favoritar ${esc(card.name)}">${favoriteIds().includes(card.id) ? '♥' : '♡'}</button>${card.spoiler?'<span class="preview-badge">PRÉVIA</span>':''}<span class="tile-inspect" aria-hidden="true">EXPLORAR CARTA ↗</span></div><div class="tile-caption"><span>${esc(card.id)}</span><b>${esc(card.rarity)}</b></div><a class="tile-name" href="carta.html?id=${encodeURIComponent(card.uid)}">${esc(card.name)}</a><div class="tile-sub"><i style="background:${colors[card.color.split('/')[0]]?.[1] || '#aaa'}"></i>${palette} · ${types[card.type] || esc(card.type)}</div><div class="tile-numbers"><span title="${card.type==='LEADER'?'Vida inicial':'Custo impresso'}">${trait}</span>${power===null?'':`<span title="Poder base">⚔ ${power.toLocaleString('pt-BR')}</span>`}${counter===null?'':`<span title="Counter impresso">↗ +${counter.toLocaleString('pt-BR')}</span>`}</div>${details?`<p class="tile-description">${esc(details.preview)}</p>`:''}</article>`;
  }
  const page = document.body.dataset.page;
  if ($('#app-shell')) {
    const items = [['home','home.html','▦','Explorar cartas'],['decks','decks.html','⚑','Montar decks'],['buy','comprar.html','◉','Onde conseguir'],['favorites','favoritos.html','♡','Meus favoritos'],['learn','aprender.html','◈','Aprenda a jogar']];
    $('#app-shell').innerHTML = `<a class="skip" href="#main">Pular para o conteúdo</a><aside class="app-sidebar" id="sidebar"><a class="brand" href="index.html"><span class="brand-symbol">✦</span><span>GRAND LINE<span class="brand-sub">A T L A S</span></span></a><span class="sidebar-kicker">SEU DIÁRIO DE BORDO</span><nav class="side-nav" aria-label="Menu principal">${items.map(([id,url,icon,label])=>`<a href="${url}" ${page===id?'class="selected" aria-current="page"':''}><span>${icon}</span>${label}${id==='favorites'?'<small id="nav-favorite-count"></small>':''}</a>`).join('')}</nav><div class="sidebar-course"><span>CONHECIMENTO É TESOURO</span><b>Sua primeira partida<br>começa aqui.</b><a href="aprender.html">Conheça o jogo <span>↗</span></a><span class="sidebar-spark">✧</span></div><div class="sidebar-bottom"><a href="index.html">← Página de apresentação</a><span>Projeto acadêmico independente<br>One Piece TCG · Atlas</span></div></aside><button class="sidebar-scrim" aria-label="Fechar menu" hidden></button><header class="app-topbar"><button class="app-menu" aria-label="Abrir menu" aria-controls="sidebar" aria-expanded="false">☰</button><span class="topbar-path">ATLAS <span>/</span> ${page==='decks'?'ESTALEIRO DE DECKS':page==='buy'?'PORTO COMERCIAL':page==='learn'?'ACADEMIA PIRATA':page==='favorites'?'SUA COLEÇÃO':page==='card'?'DETALHES DA CARTA':'EXPLORAR O NOVO MUNDO'}</span><div class="topbar-actions"><a href="aprender.html" class="topbar-help" aria-label="Ajuda e tutorial">?</a><a class="topbar-login" href="login.html">Entrar <span>↗</span></a></div></header>`;
    const updateCount = () => { $('#nav-favorite-count').textContent = favoriteIds().length; };
    updateCount(); document.addEventListener('atlas:favorites',updateCount);
    const closeMenu = () => { $('#sidebar').classList.remove('is-open'); $('.sidebar-scrim').hidden=true; $('.app-menu').setAttribute('aria-expanded','false'); };
    $('.app-menu').onclick=()=>{ const open=$('#sidebar').classList.toggle('is-open'); $('.sidebar-scrim').hidden=!open; $('.app-menu').setAttribute('aria-expanded',String(open)); };
    $('.sidebar-scrim').onclick=closeMenu;
    document.addEventListener('keydown',e=>{if(e.key==='Escape')closeMenu();});
  }
  if ($('#app-footer')) $('#app-footer').innerHTML = `<span>GRAND LINE ATLAS · Projeto acadêmico de fã para fã.</span><span>One Piece © Eiichiro Oda / Shueisha / Toei Animation · Card Game © Bandai.<br>Sem afiliação oficial. <a href="https://en.onepiece-cardgame.com/cardlist/" target="_blank" rel="noopener">Catálogo Bandai ↗</a> · <a href="https://oplaytcg.com/en/library" target="_blank" rel="noopener">Complementos OPlayTCG ↗</a></span>`;
  document.addEventListener('click', e => { const button=e.target.closest('[data-favorite]'); if(button) favorite(button.dataset.favorite); });
  document.addEventListener('error',e=>{
    const img=e.target;if(img.tagName!=='IMG')return;
    const original=img.dataset.original;
    if(original&&!img.dataset.retried&&img.getAttribute('src')!==original){img.dataset.retried='true';img.src=original;return;}
    if(img.closest('.tile-image-link')){img.hidden=true;img.closest('.tile-media')?.classList.add('art-ready');const fallback=img.parentElement.querySelector('.image-fallback');if(fallback)fallback.hidden=false;}
  },true);
  window.Atlas = { $, esc, normalize, read, write, toast, colors, types, favoriteIds, favorite, image, digits, tile };
})();
