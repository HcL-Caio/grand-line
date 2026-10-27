/* Facetas de descoberta: características impressas e classificações editoriais.
 * “Herói/antagonista” não são campos oficiais do TCG e podem variar por arco. */
(() => {
  const normalize=s=>String(s||'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase();
  const canonical=s=>normalize(s).replace(/[^a-z0-9]+/g,' ').trim().replace(/\s+/g,' ');
  const topics=[
    {id:'straw-hat',label:'Chapéu de Palha',kind:'printed',hint:'Característica Straw Hat Crew',aliases:'chapeu de palha mugiwara'},
    {id:'pirate',label:'Piratas',kind:'editorial',hint:'Grupos piratas e ex-piratas indicados nas características',aliases:'pirata pirates'},
    {id:'marine',label:'Marinha',kind:'printed',hint:'Característica Navy',aliases:'marinha marine navy'},
    {id:'revolutionary',label:'Revolucionários',kind:'printed',hint:'Característica Revolutionary Army',aliases:'revolucionario exercito'},
    {id:'d-family',label:'Nome com “D.”',kind:'editorial',hint:'Líderes e Personagens com D. no nome publicado',aliases:'familia d vontade d'},
    {id:'heroes',label:'Heróis / aliados',kind:'editorial',hint:'Seleção editorial de protagonistas e aliados; varia por arco',aliases:'heroi herois protagonistas aliados'},
    {id:'antagonists',label:'Antagonistas',kind:'editorial',hint:'Personagens que atuam como antagonistas em arcos; não é classificação moral fixa',aliases:'vilao viloes antagonista'},
    {id:'warlords',label:'Shichibukai',kind:'printed',hint:'Característica The Seven Warlords of the Sea',aliases:'shichibukai sete corsarios'},
    {id:'emperors',label:'Imperadores',kind:'printed',hint:'Característica The Four Emperors',aliases:'yonkou imperadores'},
    {id:'wano',label:'Wano',kind:'printed',hint:'Característica Land of Wano',aliases:'pais de wano'},
    {id:'egghead',label:'Egghead',kind:'printed',hint:'Característica Egghead',aliases:'ilha do futuro'}
  ];
  const heroNames=['luffy','roronoa zoro','nami','usopp','sanji','tony tony chopper','nico robin','franky','brook','jinbe','trafalgar law','portgas d ace','sabo','nefeltari vivi','yamato','kozuki oden','kozuki momonosuke','carrot','rebecca','shanks','jewelry bonney'];
  const antagonistNames=['kaido','charlotte linlin','big mom','donquixote doflamingo','arlong','rob lucci','marshall d teach','enel','crocodile','sakazuki','spandam','hody jones','caesar clown','gecko moria','kuro'];
  const memo=new Map();
  function tags(card){const cacheKey=card.uid||card.id;if(memo.has(cacheKey))return memo.get(cacheKey);const features=canonical(card.archetype),name=canonical(card.name);const character=['LEADER','CHARACTER'].includes(card.type);const result=[];
    if(features.includes('straw hat crew'))result.push('straw-hat');
    if(/pirates|straw hat crew|supernovas|four emperors|seven warlords/.test(features))result.push('pirate');
    if(/\bnavy\b/.test(features))result.push('marine');if(features.includes('revolutionary army'))result.push('revolutionary');
    if(character&&/(?:^|[.\s&/])D\./.test(card.name))result.push('d-family');
    if(character&&heroNames.some(n=>name.includes(n)))result.push('heroes');
    if(character&&antagonistNames.some(n=>name.includes(n)))result.push('antagonists');
    if(features.includes('seven warlords'))result.push('warlords');if(features.includes('four emperors'))result.push('emperors');
    if(features.includes('land of wano'))result.push('wano');if(features.includes('egghead'))result.push('egghead');memo.set(cacheKey,result);return result;
  }
  function matches(card,selected,mode='all'){if(!selected.length)return true;const found=tags(card);return mode==='any'?selected.some(t=>found.includes(t)):selected.every(t=>found.includes(t));}
  function characterMatches(card,query){return !query||(['LEADER','CHARACTER'].includes(card.type)&&canonical(card.name).includes(canonical(query)));}
  function searchWords(card){return tags(card).map(id=>{const t=topics.find(t=>t.id===id);return t.label+' '+t.aliases;}).join(' ');}
  window.AtlasTaxonomy={topics,tags,matches,characterMatches,searchWords,canonical};
})();
