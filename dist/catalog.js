(() => {
  const {$,esc,normalize,colors,types,digits,tile,favoriteIds} = Atlas;
  const data=window.ATLAS_CATALOG;
  if(!data?.cards?.length){ $('#catalog-grid').innerHTML='<div class="catalog-empty"><h2>O catálogo não foi carregado.</h2><p>Confira se catalog-data.js está na mesma pasta desta página.</p></div>';return; }
  const all=data.cards; const favoritePage=document.body.dataset.page==='favorites';
  const fmt=n=>n.toLocaleString('pt-BR');
  $('#stat-cards').textContent=fmt(data.meta.uniqueCardCount); $('#stat-prints').textContent=fmt(data.meta.printingCount); $('#stat-sets').textContent=data.meta.productCount;
  $('#catalog-date').textContent=new Date(data.meta.fetchedAt).toLocaleDateString('pt-BR');
  const filters=['q','character','tags','availability','color','type','rarity','set','attribute','counter','keyword','archetype','block','cost-min','cost-max','power-min','power-max'];
  const populate=(id,values)=>{const select=$('#'+id);for(const [value,label] of values){const o=document.createElement('option');o.value=value;o.textContent=label;select.append(o);}};
  populate('color',Object.entries(colors).map(([k,v])=>[k,v[0]]).concat([['multi','Multicolor']]));
  populate('type',Object.entries(types));
  const taxonomy=window.AtlasTaxonomy;
  $('#character-options').innerHTML=[...new Set(all.filter(c=>['LEADER','CHARACTER'].includes(c.type)).map(c=>c.name))].sort().map(name=>`<option value="${esc(name)}"></option>`).join('');
  $('#topic-tags').innerHTML=taxonomy.topics.map(t=>`<button class="topic-tag" data-topic="${t.id}" aria-pressed="false" title="${esc(t.hint)}">${esc(t.label)}${t.kind==='editorial'?'<sup>✧</sup>':''}</button>`).join('');
  $('#topic-tags').onclick=e=>{const button=e.target.closest('[data-topic]');if(!button)return;const selected=new Set($('#tags').value.split(',').filter(Boolean));selected.has(button.dataset.topic)?selected.delete(button.dataset.topic):selected.add(button.dataset.topic);$('#tags').value=[...selected].join(',');update();};
  populate('rarity',[...new Set(all.map(c=>c.rarity))].sort().map(r=>[r,r]));
  populate('set',data.meta.sets.map(s=>[s.id,s.name]));
  populate('attribute',[...new Set(all.map(c=>c.attribute))].filter(x=>x&&x!=='-').sort().map(a=>[a,a]));
  populate('counter',[['none','Sem Counter'],['1000','+1000'],['2000','+2000']]);
  populate('block',[...new Set(all.map(c=>c.block))].filter(Boolean).sort().map(b=>[b,b]));
  const keywords=['Blocker','Rush','Rush: Character','Double Attack','Banish','Unblockable','On Play','When Attacking','Activate: Main','On K.O.','On Block','On Your Opponent\'s Attack','End of Your Turn','Trigger'];
  populate('keyword',keywords.map(k=>[k,k]));
  const archetypes=[...new Set(all.flatMap(c=>c.archetype.split('/').map(x=>x.trim())))].filter(Boolean).sort();
  $('#archetype-list').innerHTML=archetypes.map(a=>`<option value="${esc(a)}"></option>`).join('');
  let page=1,matched=[];let timer;
  const initial=new URLSearchParams(location.search);
  [...filters,'sort','per-page','tag-mode'].forEach(id=>{if(initial.has(id))$('#'+id).value=initial.get(id);});
  $('#show-prints').checked=initial.get('prints')==='1';
  $('#only-favorites').checked=favoritePage||initial.get('favorites')==='1';
  if(favoritePage){$('#only-favorites').disabled=true;$('#page-heading').textContent='Seus tesouros, reunidos.';$('#page-intro').textContent='As cartas que você salvou para explorar de novo. Favoritos ficam neste navegador.';$('.catalog-welcome').classList.add('favorite-welcome');}
  const searchIndex=new Map(all.map(c=>[c.uid,normalize([c.id,c.name,c.archetype,c.effect,c.trigger,taxonomy.searchWords(c)].join(' '))]));
  function filterCatalog(){const values=Object.fromEntries(filters.map(id=>[id,$('#'+id).value.trim()]));const q=normalize(values.q);const favorites=new Set(favoriteIds());let result=all.filter(c=>{
    if(q&&!searchIndex.get(c.uid).includes(q))return false;
    if(!taxonomy.characterMatches(c,values.character))return false;
    if(!taxonomy.matches(c,values.tags.split(',').filter(Boolean),$('#tag-mode').value))return false;
    if(values.availability==='preview'&&!c.spoiler)return false;
    if(values.availability==='published'&&c.spoiler)return false;
    if(values.availability==='provisional'&&!['placeholder','missing','unknown'].includes(c.imageStatus))return false;
    if(values.color&&(values.color==='multi'?!c.color.includes('/'):!c.color.split('/').includes(values.color)))return false;
    if(values.type&&c.type!==values.type)return false;
    if(values.rarity&&c.rarity!==values.rarity)return false;
    if(values.set&&!c.sets.some(s=>s.id===values.set))return false;
    if(values.attribute&&c.attribute!==values.attribute)return false;
    if(values.block&&c.block!==values.block)return false;
    if(values.archetype&&!normalize(c.archetype).includes(normalize(values.archetype)))return false;
    if(values.counter&&(values.counter==='none'?digits(c.counter)!==null:digits(c.counter)!==+values.counter))return false;
    if(values.keyword){const effect=c.effect+'\n'+c.trigger;const token='['+values.keyword+']';if(!effect.includes(token))return false;}
    for(const [field,key,op] of [['cost-min','cost','min'],['cost-max','cost','max'],['power-min','power','min'],['power-max','power','max']]){if(values[field]!==''){const n=digits(c[key]);if(n===null||(op==='min'?n<+values[field]:n>+values[field]))return false;}}
    return !$('#only-favorites').checked||favorites.has(c.id);
  });
    // Aplicar filtros antes de agrupar preserva resultados de coleções e versões.
    if(!$('#show-prints').checked){const unique=new Map();for(const c of result){if(!unique.has(c.id)||c.uid===c.id)unique.set(c.id,c);}result=[...unique.values()];}
    const sort=$('#sort').value;const collator=new Intl.Collator('pt-BR',{numeric:true});result.sort((a,b)=>{
      if(sort==='name')return collator.compare(a.name,b.name)||collator.compare(a.uid,b.uid);
      if(sort==='cost'||sort==='power'){const av=digits(a[sort]),bv=digits(b[sort]);if(av===null&&bv!==null)return 1;if(bv===null&&av!==null)return -1;if(av!==null&&bv!==null&&av!==bv)return sort==='power'?bv-av:av-bv;}
      return (a.type==='DON')-(b.type==='DON')||collator.compare(a.id,b.id)||collator.compare(a.uid,b.uid);
    });
    return result;
  }
  function syncUrl(){const p=new URLSearchParams();[...filters,'sort','per-page','tag-mode'].forEach(id=>{const el=$('#'+id);if(el.value&&!(id==='tag-mode'&&el.value==='all')&&!(id==='sort'&&el.value==='id')&&!(id==='per-page'&&el.value==='24'))p.set(id,el.value);});if($('#show-prints').checked)p.set('prints','1');if($('#only-favorites').checked&&!favoritePage)p.set('favorites','1');try{history.replaceState(null,'',location.pathname+(p.size?'?'+p:'')+location.hash);}catch{}}
  function renderChips(){const names={q:'Busca',character:'Personagem',tags:'Grupos',availability:'Disponibilidade',color:'Cor',type:'Tipo',rarity:'Raridade',set:'Coleção',attribute:'Atributo',counter:'Counter',keyword:'Habilidade',archetype:'Característica',block:'Bloco','cost-min':'Custo mín.','cost-max':'Custo máx.','power-min':'Poder mín.','power-max':'Poder máx.'};$('#active-filters').innerHTML=filters.filter(id=>$('#'+id).value).map(id=>{const el=$('#'+id);const text=id==='tags'?el.value.split(',').map(id=>taxonomy.topics.find(t=>t.id===id)?.label||id).join(', '):el.tagName==='SELECT'?el.selectedOptions[0]?.textContent||el.value:el.value;return `<button class="filter-chip" data-clear="${id}" aria-label="Remover filtro ${names[id]}">${names[id]}: ${esc(text)} <span>×</span></button>`;}).join('');document.querySelectorAll('[data-topic]').forEach(b=>b.setAttribute('aria-pressed',String($('#tags').value.split(',').includes(b.dataset.topic))));}
  function render(){const per=+$('#per-page').value||24;const pages=Math.max(1,Math.ceil(matched.length/per));page=Math.min(Math.max(page,1),pages);const start=(page-1)*per;$('#catalog-grid').innerHTML=matched.slice(start,start+per).map(tile).join('');$('#catalog-empty').hidden=matched.length>0;$('#catalog-summary').textContent=matched.length?`${fmt(start+1)}–${fmt(Math.min(start+per,matched.length))} de ${fmt(matched.length)} ${$('#show-prints').checked?'versões':'cartas'}`:'Nenhuma carta encontrada';$('#page-label').textContent=`Página ${page} de ${fmt(pages)}`;$('#page-prev').disabled=page===1;$('#page-next').disabled=page===pages||!matched.length;$('#page-number').max=pages;$('#page-number').value=page;$('#pagination').hidden=!matched.length;$('#visible-count').textContent=fmt(matched.length);document.querySelectorAll('[data-favorite]').forEach(b=>b.setAttribute('aria-pressed',favoriteIds().includes(b.dataset.favorite)));}
  function update(){page=1;matched=filterCatalog();renderChips();syncUrl();render();}
  [...filters,'sort','per-page','show-prints','only-favorites','tag-mode'].forEach(id=>$('#'+id).addEventListener('input',()=>{clearTimeout(timer);timer=setTimeout(update,['q','archetype','character'].includes(id)?150:0);}));
  function reset(){filters.forEach(id=>$('#'+id).value='');$('#tag-mode').value='all';$('#show-prints').checked=false;$('#only-favorites').checked=favoritePage;$('#sort').value='id';update();}
  $('#clear-all').onclick=reset;$('#empty-clear').onclick=reset;
  $('#active-filters').onclick=e=>{const b=e.target.closest('[data-clear]');if(b){$('#'+b.dataset.clear).value='';update();}};
  function go(n){page=n;render();$('#catalog-results').scrollIntoView({behavior:matchMedia('(prefers-reduced-motion: reduce)').matches?'auto':'smooth',block:'start'});}
  $('#page-prev').onclick=()=>go(page-1);$('#page-next').onclick=()=>go(page+1);$('#page-jump').onsubmit=e=>{e.preventDefault();go(+$('#page-number').value);};
  $('#advanced-toggle').onclick=()=>{const open=$('#advanced-filters').hidden;$('#advanced-filters').hidden=!open;$('#advanced-toggle').setAttribute('aria-expanded',String(open));};
  document.addEventListener('atlas:favorites',()=>{matched=filterCatalog();render();});
  document.addEventListener('keydown',e=>{if(e.key==='/'&&!['INPUT','TEXTAREA','SELECT'].includes(document.activeElement.tagName)){e.preventDefault();$('#q').focus();}});
  update();
})();
