import { readFileSync,writeFileSync,readdirSync,copyFileSync,mkdirSync,existsSync,rmSync } from 'node:fs';
import { createHash } from 'node:crypto';
import { resolve,relative,dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
const root=resolve(dirname(fileURLToPath(import.meta.url)),'..');
copyFileSync(resolve(root,'resources/css/theme.css'),resolve(root,'dist/theme.css'));
copyFileSync(resolve(root,'resources/css/storefront.css'),resolve(root,'dist/storefront.css'));
// Packaged type: resources/fonts is the source, dist/fonts is what ships.
const fontSource = resolve(root,'resources/fonts');
if (existsSync(fontSource)) {
    mkdirSync(resolve(root,'dist/fonts'),{recursive:true});
    for (const file of readdirSync(fontSource)) {
        if (typeof file === 'string') copyFileSync(resolve(root,fontSource,file),resolve(root,'dist/fonts',file));
    }
}
// Packaged imagery: resources/images is the source, dist/images is what ships.
const imageSource = resolve(root,'resources/images');
if (existsSync(imageSource)) {
    mkdirSync(resolve(root,'dist/images'),{recursive:true});
    for (const file of readdirSync(imageSource,{withFileTypes:true})) {
        if (file.isDirectory()) {
            mkdirSync(resolve(root,'dist/images',file.name),{recursive:true});
            for (const nested of readdirSync(resolve(root,'resources/images',file.name),{withFileTypes:true})) {
                if (nested.isFile()) copyFileSync(resolve(root,'resources/images',file.name,nested.name),resolve(root,'dist/images',file.name,nested.name));
            }
            continue;
        }
        if (file.isFile()) copyFileSync(resolve(root,'resources/images',file.name),resolve(root,'dist/images',file.name));
    }
}
const entry=path=>({path,sha256:createHash('sha256').update(readFileSync(resolve(root,path))).digest('hex')});
const vite=JSON.parse(readFileSync(resolve(root,'dist/build/.vite/manifest.json')));
const manifest={format:1,styles:[entry('dist/design/css/tailwind.css'),entry('dist/design/css/styles.css'),entry('dist/storefront.css'),entry('dist/theme.css')],scripts:[entry('dist/build/'+vite['resources/js/main.js'].file)],files:{}};
function visit(dir) {
    for(const file of readdirSync(dir,{withFileTypes:true})) {
        if(file.name.startsWith('.')) continue;
        const path=resolve(dir,file.name);
        if(file.isDirectory()) visit(path);
        else if(/\.(css|js|jpe?g|png|webp|avif|svg|woff2?|ttf|otf|webmanifest)$/.test(file.name)) {
            const name=relative(root,path).replaceAll('\\','/');
            manifest.files[name]=entry(name);
            if(name.startsWith('dist/images/')) manifest.files[name.slice(5)]=entry(name);
        }
    }
}
visit(resolve(root,'dist'));
writeFileSync(resolve(root,'dist/manifest.json'),JSON.stringify(manifest,null,4)+'\n');
console.log('Built RJ Motorbike asset manifest.');
