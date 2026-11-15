import { spawn } from 'node:child_process'
import { once } from 'node:events'
import { cp, mkdir, readFile, rm, writeFile, access } from 'node:fs/promises'
import path from 'node:path'
import { fileURLToPath } from 'node:url'
const root=fileURLToPath(new URL('../..',import.meta.url))
const frontend=path.join(root,'frontend'),backend=path.join(root,'backend')
const output=path.join(root,'server-release')
const compiled=path.join(frontend,'node_modules/.cache/server-release-web')
function checked(directory,parent) {
 const target=path.resolve(directory),base=path.resolve(parent)
 if(!target.startsWith(base+path.sep))throw Error('Release directory outside workspace')
 return target
}
await access(path.join(backend,'vendor/autoload.php'))
const vite=spawn(process.execPath,[path.join(frontend,'node_modules/vite/bin/vite.js'),'build','--outDir',compiled,'--emptyOutDir'],{cwd:frontend,env:{...process.env,VITE_SHARE_DEMO:'false'},stdio:'inherit',windowsHide:true})
const [code]=await once(vite,'exit')
if(code!==0)throw Error('Production frontend build failed')
await rm(checked(output,root),{recursive:true,force:true})
await mkdir(path.join(output,'public'),{recursive:true})
await cp(compiled,path.join(output,'public'),{recursive:true})
await rm(path.join(output,'public/demo-data.json'),{force:true})
for(const entry of ['bootstrap.php','environment.php','composer.json','composer.lock','src','routes','vendor','database','public/index.php','bin/migrate.php','bin/key.php','bin/seed-experience.php']) {
 const destination=path.join(output,'backend',entry)
 await mkdir(path.dirname(destination),{recursive:true})
 await cp(path.join(backend,entry),destination,{recursive:true})
}
await mkdir(path.join(output,'backend/storage/logs'),{recursive:true})
await mkdir(path.join(output,'backend/storage/sessions'),{recursive:true})
for(const entry of ['api.php','.htaccess'])await cp(path.join(root,'deploy/public',entry),path.join(output,'public',entry))
await cp(path.join(root,'.htaccess'),path.join(output,'.htaccess'))
await cp(path.join(root,'deploy/production.env.example'),path.join(output,'backend/.env.example'))
await cp(path.join(root,'docs/server-deployment.md'),path.join(output,'LEIA-ME.md'))
const html=await readFile(path.join(output,'public/index.html'),'utf8')
if(!html.includes('/assets/'))throw Error('Unexpected frontend asset paths')
await writeFile(path.join(output,'RELEASE.json'),JSON.stringify({generated_at:new Date().toISOString(),mode:'live-api',public_directory:'public',runtime:'PHP 8.2+ / MySQL 8',includes_credentials:false},null,2))
console.log('Dynamic server release ready: '+output)
if(process.argv.includes('--install-laragon')) {
 const localPublic=checked(path.join(root,'public'),root)
 await mkdir(localPublic,{recursive:true})
 await cp(path.join(output,'public'),localPublic,{recursive:true})
 await rm(path.join(localPublic,'demo-data.json'),{force:true})
 const accessFile=path.join(localPublic,'.htaccess')
 const accessRules=(await readFile(accessFile,'utf8')).replace('Require all granted','Require ip 127.0.0.1 ::1 10.0.0.0/8 172.16.0.0/12 192.168.0.0/16')
 await writeFile(accessFile,accessRules)
 console.log('Laragon public directory ready: '+localPublic)
}
