// Salva as ilustrações oficiais localmente. Reexecutar retoma arquivos concluídos.
// Node.js 18+ e sharp: npm install --no-save sharp (ou variável SHARP_MODULE).
import fs from 'node:fs';import path from 'node:path';import {fileURLToPath,pathToFileURL} from 'node:url';
const root=path.resolve(path.dirname(fileURLToPath(import.meta.url)),'..');
const sharpModule=process.env.SHARP_MODULE;
const sharp=(sharpModule?await import(pathToFileURL(sharpModule).href):await import('sharp')).default;
const data=JSON.parse(fs.readFileSync(path.join(root,'dist/catalog-data.json'),'utf8'));
const out=path.join(root,'dist/assets/cards');fs.mkdirSync(out,{recursive:true});
const manifestPath=path.join(root,'dist/image-manifest.js');
const manifest={};let cursor=0,done=0,newImages=0;const failures=[];
const pause=ms=>new Promise(r=>setTimeout(r,ms));
function save(){const report={updatedAt:new Date().toISOString(),total:data.cards.length,localCount:Object.keys(manifest).length,failed:failures,images:manifest};const temp=manifestPath+'.tmp';fs.writeFileSync(temp,'window.ATLAS_IMAGE_LIBRARY = '+JSON.stringify(report)+';\n');fs.renameSync(temp,manifestPath);}
for(const c of data.cards){const png=path.join(root,'dist/assets',c.uid+'.png');const webp=path.join(out,c.uid+'.webp');if(fs.existsSync(png))manifest[c.uid]='assets/'+c.uid+'.png';else if(fs.existsSync(webp)&&fs.statSync(webp).size>1000)manifest[c.uid]='assets/cards/'+c.uid+'.webp';}
save();
async function worker(){while(cursor<data.cards.length){const c=data.cards[cursor++];if(manifest[c.uid]){done++;continue;}let success=false;for(let attempt=0;attempt<3;attempt++){try{const response=await fetch(c.image,{signal:AbortSignal.timeout(35000)});if(!response.ok)throw Error('HTTP '+response.status);const bytes=Buffer.from(await response.arrayBuffer());const metadata=await sharp(bytes).metadata();if(metadata.width<150||metadata.height<200)throw Error('Imagem pequena ou inválida');const target=path.join(out,c.uid+'.webp');await sharp(bytes).resize({width:520,withoutEnlargement:true}).webp({quality:88,effort:3}).toFile(target+'.tmp');fs.renameSync(target+'.tmp',target);manifest[c.uid]='assets/cards/'+c.uid+'.webp';newImages++;success=true;break;}catch(e){if(attempt===2)failures.push({uid:c.uid,error:e.message});else await pause(1200*(attempt+1));}}done++;if(done%80===0){save();console.log(done+'/'+data.cards.length,'processadas;',Object.keys(manifest).length,'locais;',failures.length,'falhas');}if(!success)console.log('FALHA',c.uid);await pause(100);}}
await Promise.all(Array.from({length:6},()=>worker()));save();console.log(JSON.stringify({total:data.cards.length,local:Object.keys(manifest).length,newImages,failures:failures.length}));
