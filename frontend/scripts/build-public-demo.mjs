import { spawnSync } from 'node:child_process'
import { access, cp, mkdir, readFile, writeFile } from 'node:fs/promises'
import path from 'node:path'
import { fileURLToPath } from 'node:url'
const root=fileURLToPath(new URL('../..',import.meta.url))
const runtime=path.join(root,'public-demo'),backend=path.join(root,'backend')
await access(path.join(root,'server-release/public/index.html'))
await mkdir(runtime,{recursive:true})
let existing=''
try{existing=await readFile(path.join(runtime,'backend/.env'),'utf8')}catch(e){if(e.code!=='ENOENT')throw e}
if(existing && (!/^APP_ENV="?demo"?$/m.test(existing)||!/^DB_DATABASE="?viva_elo_demo_[a-f0-9]{12}"?$/m.test(existing)))throw Error('Existing demo configuration is not isolated; review manually')
await cp(path.join(root,'server-release'),runtime,{recursive:true})
if(!existing){
 const fixture=spawnSync(process.env.PHP_BINARY||'php',['tests/browser-fixture.php'],{cwd:backend,input:JSON.stringify({action:'create',public_demo:true,nursing_scenario:true}),encoding:'utf8',windowsHide:true})
 if(fixture.status!==0)throw Error('Could not initialize isolated fictional database')
 const setup=JSON.parse(fixture.stdout)
 if(!/^viva_elo_demo_[a-f0-9]{12}$/.test(setup.database))throw Error('Unexpected demo database')
 const values={...setup.env,APP_ENV:'demo',PUBLIC_DEMO_ACCESS:'true',LOCAL_PROFILE_ACCESS:'false'}
 delete values.DEMO_PASSWORD
 const content=Object.entries(values).map(([key,value])=>key+'='+JSON.stringify(String(value)).replace(/\$/g,'\\$')).join('\n')+'\n'
 await writeFile(path.join(runtime,'backend/.env'),content,{mode:0o600})
}
console.log('Interactive fictional demo ready; the original application database is unchanged.')
