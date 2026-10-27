// Atualiza a fotografia do catálogo público inglês da Bandai. Node.js 18+.
// Execute na pasta do projeto: node tools/import-catalog.mjs
import fs from 'node:fs';
import path from 'node:path';
import {fileURLToPath} from 'node:url';
const root=path.resolve(path.dirname(fileURLToPath(import.meta.url)),'..');
const cache=path.resolve(root,'../../work/catalog-cache');
fs.mkdirSync(cache,{recursive:true});
const clean=s=>String(s||'').replace(/<br\s*\/?\s*>/gi,'\n').replace(/<[^>]*>/g,'').replace(/&amp;/g,'&').replace(/&lt;/g,'<').replace(/&gt;/g,'>').replace(/&#039;|&#39;/g,"'").replace(/&quot;/g,'"').replace(/&nbsp;/g,' ').replace(/&#(\d+);/g,(_,n)=>String.fromCodePoint(+n)).trim();
async function read(url,name){const file=path.join(cache,name+'.html');if(fs.existsSync(file)&&!process.argv.includes('--refresh'))return fs.readFileSync(file,'utf8');const r=await fetch(url,{signal:AbortSignal.timeout(60000)});if(!r.ok)throw Error(url+': '+r.status);const html=await r.text();fs.writeFileSync(file,html);return html;}
const index=await read('https://en.onepiece-cardgame.com/cardlist/','index');
const sets=[...index.matchAll(/<option[^>]*value="(\d+)"[^>]*>([\s\S]*?)<\/option>/g)].map(m=>({id:m[1],name:clean(clean(m[2])).replace(/\s+/g,' ')}));
if(sets.length<50)throw Error('Catálogo de produtos incompleto: '+sets.length);
const entries=new Map(),counts=[],failures=[];let cursor=0;
async function worker(){while(cursor<sets.length){const set=sets[cursor++];try{const html=await read('https://en.onepiece-cardgame.com/cardlist/?series='+set.id,set.id);const blocks=[...html.matchAll(/<dl class="modalCol" id="([^"]+)">([\s\S]*?)<\/dl>/g)];if(!blocks.length)throw Error('Nenhuma carta na página');let found=0;for(const [,uid,b] of blocks){const spans=[...b.matchAll(/<span>([^<]+)<\/span>/g)].map(m=>clean(m[1]));const name=clean(b.match(/class="cardName">([\s\S]*?)<\/div>/)?.[1]);const get=cls=>clean(b.match(new RegExp('<div class="'+cls+'">[\\s\\S]*?</h3>([\\s\\S]*?)</div>'))?.[1]);const image=b.match(/data-src="([^"?]+)/)?.[1];if(!name||!image||!spans[0])continue;const imageUrl=new URL(image,'https://en.onepiece-cardgame.com/cardlist/').href;const key=path.basename(new URL(imageUrl).pathname,'.png');const previous=entries.get(key);if(previous){if(!previous.sets.some(s=>s.id===set.id))previous.sets.push(set);}else{const cost=get('cost');const isLeader=spans[2]==='LEADER';entries.set(key,{uid:key,id:spans[0],name,rarity:spans[1],type:spans[2],cost:isLeader?null:cost,life:isLeader?cost:null,attribute:get('attribute'),power:get('power'),counter:get('counter'),color:get('color'),block:get('block'),archetype:get('feature'),effect:get('text'),trigger:get('trigger'),image:imageUrl,sets:[set],source:'https://en.onepiece-cardgame.com/cardlist/?series='+set.id});}found++;}counts.push({id:set.id,count:found});console.log(set.id,found,'cartas');}catch(e){failures.push({set:set.id,error:e.message});console.error('FALHA',set.id,e.message);}}}
await Promise.all([worker(),worker(),worker()]);
const cards=[...entries.values()].sort((a,b)=>a.id.localeCompare(b.id,undefined,{numeric:true})||a.uid.localeCompare(b.uid));
const meta={language:'en',region:'NA/EU/OC/LATAM/ME',fetchedAt:new Date().toISOString(),source:'https://en.onepiece-cardgame.com/cardlist/',productCount:counts.length,printingCount:cards.length,uniqueCardCount:new Set(cards.map(c=>c.id)).size,sets,failures};
if(failures.length){fs.writeFileSync(path.join(cache,'failures.json'),JSON.stringify(failures,null,2));throw Error('Importação incompleta; dados anteriores preservados.');}
fs.writeFileSync(path.join(root,'dist/catalog-data.js'),'window.ATLAS_CATALOG = '+JSON.stringify({meta,cards})+';\n');
fs.writeFileSync(path.join(root,'dist/catalog-data.json'),JSON.stringify({meta,cards}));
console.log(JSON.stringify({products:meta.productCount,printings:cards.length,unique:meta.uniqueCardCount,failures:failures.length}));
