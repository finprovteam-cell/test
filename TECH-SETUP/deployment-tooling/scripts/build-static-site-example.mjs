import { cp, mkdir, rm, readFile, writeFile } from 'node:fs/promises';
import { createHash } from 'node:crypto';
import path from 'node:path';
await rm('dist',{recursive:true,force:true});
await mkdir('dist',{recursive:true});
await cp('site','dist',{recursive:true});
// Stable source files get content-based cache versions; marketers edit the originals in site/.
for (const html of ['index.html','thank-you/index.html']) {
  const file=path.join('dist',html);
  let source=await readFile(file,'utf8');
  for(const asset of ['site-config.js','tracking.js','assets/app.js','assets/styles.css']) {
    const content=await readFile(path.join('dist',asset));
    const version=createHash('sha256').update(content).digest('hex').slice(0,12);
    const escaped=asset.replace(/[.*+?^${}()|[\]\\]/g,'\\$&');
    source=source.replace(new RegExp(`((?:src|href)=["'][^"']*?${escaped})(?:\\?[^"']*)?(["'])`,'g'),`$1?v=${version}$2`);
  }
  await writeFile(file,source);
}
console.log('Built static release with content-versioned scripts and CSS');
