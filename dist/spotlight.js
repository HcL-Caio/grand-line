(() => {
  const gallery=document.querySelector('#spotlight-gallery');if(!gallery)return;
  const esc=value=>String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const cards=window.ATLAS_FEATURED||[];
  gallery.innerHTML=cards.map((c,i)=>{const reading=AtlasDescriptions.describe(c);const photo=window.ATLAS_IMAGE_LIBRARY?.images?.[c.uid]||c.image;return `<article class="treasure-poster"><div class="treasure-topline"><span>ARQUIVO ${String(i+1).padStart(2,'0')}</span><span>${esc(c.rarity)} · ${esc(c.id)}</span></div><a class="spotlight-art" href="carta.html?id=${encodeURIComponent(c.uid)}" aria-label="Explorar ${esc(c.name)}"><img src="${esc(photo)}" data-original="${esc(c.image)}" alt="${esc(c.name)} — ${esc(c.id)}" width="400" height="560" loading="lazy"><span class="spotlight-art-shine" aria-hidden="true"></span></a><div class="treasure-copy"><span>UM NOME PARA A SUA COLEÇÃO</span><h3><a href="carta.html?id=${encodeURIComponent(c.uid)}">${esc(c.name)}</a></h3><p>${esc(reading.focus)}</p><a class="treasure-discover" href="carta.html?id=${encodeURIComponent(c.uid)}">Conhecer esta carta <span>↗</span></a></div></article>`;}).join('');
  const previous=document.querySelector('#spotlight-prev'),next=document.querySelector('#spotlight-next');
  const scroll=direction=>gallery.scrollBy({left:direction*((gallery.querySelector('article')?.getBoundingClientRect().width||280)+22),behavior:matchMedia('(prefers-reduced-motion: reduce)').matches?'auto':'smooth'});
  previous.onclick=()=>scroll(-1);next.onclick=()=>scroll(1);
})();
