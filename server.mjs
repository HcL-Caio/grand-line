import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import {fileURLToPath} from 'node:url';
const root=path.join(path.dirname(fileURLToPath(import.meta.url)),'dist');
const port=Number(process.env.PORT||4173);
const types={'.html':'text/html; charset=utf-8','.css':'text/css; charset=utf-8','.js':'text/javascript; charset=utf-8','.json':'application/json; charset=utf-8','.png':'image/png','.jpg':'image/jpeg','.webp':'image/webp','.svg':'image/svg+xml'};
http.createServer((req,res)=>{
  let requested;
  try{requested=decodeURIComponent(new URL(req.url,'http://localhost').pathname);}catch{res.writeHead(400);res.end('Invalid URL');return;}
  const filename=path.resolve(root,'.'+(requested==='/'?'/index.html':requested));
  if(!filename.startsWith(root+path.sep)){res.writeHead(403);res.end('Forbidden');return;}
  if(!['GET','HEAD'].includes(req.method)){res.writeHead(405,{'Allow':'GET, HEAD'});res.end('Frontend demonstrativo. Backend ainda nao conectado.');return;}
  fs.readFile(filename,(error,bytes)=>{
    if(error){res.writeHead(404,{'Content-Type':'text/plain; charset=utf-8'});res.end('Página não encontrada.');return;}
    res.writeHead(200,{'Content-Type':types[path.extname(filename)]||'application/octet-stream','X-Content-Type-Options':'nosniff','Cache-Control':'no-cache'});
    res.end(req.method==='HEAD'?undefined:bytes);
  });
}).listen(port,'127.0.0.1',()=>console.log(`Grand Line Atlas: http://127.0.0.1:${port}\nMantenha este terminal aberto. Ctrl+C para encerrar.`));
