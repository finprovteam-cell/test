import { createServer } from 'node:http';
import { readFile, stat } from 'node:fs/promises';
import path from 'node:path';
const config=JSON.parse(await readFile('site.json','utf8'));
const prefix=new URL(config.productionUrl).pathname;
const root=path.resolve('site');
const types={'.html':'text/html','.js':'application/javascript','.css':'text/css','.png':'image/png','.webp':'image/webp','.jpg':'image/jpeg','.svg':'image/svg+xml'};
createServer(async(req,res)=>{
  try {
    const url=new URL(req.url,'http://localhost');
    if(url.pathname==='/'){res.writeHead(302,{location:prefix}).end();return;}
    if(/\/api\//.test(url.pathname)){res.writeHead(503,{'Content-Type':'application/json'}).end('{"ok":false,"error":"Local preview does not submit to LeadSquared"}');return;}
    if(!url.pathname.startsWith(prefix)){res.writeHead(404).end();return;}
    let file=path.resolve(root,decodeURIComponent(url.pathname.slice(prefix.length))||'index.html');
    if(file!==root&&!file.startsWith(root+path.sep)){res.writeHead(403).end();return;}
    if((await stat(file)).isDirectory())file=path.join(file,'index.html');
    res.writeHead(200,{'Content-Type':types[path.extname(file)]||'application/octet-stream'}).end(await readFile(file));
  }catch{res.writeHead(404).end();}
}).listen(4173,'127.0.0.1',()=>console.log(`Preview: http://127.0.0.1:4173${prefix}`));
