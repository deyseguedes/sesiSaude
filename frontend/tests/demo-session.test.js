import {test,afterEach,mock} from 'node:test'
import assert from 'node:assert/strict'
import {createPinia,setActivePinia} from 'pinia'
import {useAuthStore} from '../src/stores/auth.js'
import {apiGet,apiRequest} from '../src/services/api.js'
const response=(data,status=200,error=null)=>new Response(JSON.stringify({data,error}),{status})
const unauthenticated=()=>response(null,401,{code:'UNAUTHENTICATED',message:'Autenticação necessária.'})
function authState(){setActivePinia(createPinia());const auth=useAuthStore();auth.$patch({publicDemo:true,user:{id:11,role:'admin'},csrf:'expired',ready:true});return auth}
afterEach(()=>mock.restoreAll())

test('Concurrent expired demo calls restore the selected profile once and retry with fresh CSRF',async()=>{
 const auth=authState();let logged=false,sessions=0,profiles=0,writes=0
 mock.method(globalThis,'fetch',async(url,options)=>{
  if(url==='/api/v1/auth/session'){sessions++;await new Promise(resolve=>setTimeout(resolve,10));return response({user:null,public_demo:true,local_profiles:['admin','nurse'],csrf_token:'new-session'})}
  if(url==='/api/v1/auth/local-profile'){profiles++;assert.equal(options.headers['X-CSRF-Token'],'new-session');assert.deepEqual(JSON.parse(options.body),{role:'admin'});logged=true;return response({user:{id:11,role:'admin'},csrf_token:'renewed'})}
  if(!logged)return unauthenticated()
  if(options.method==='POST'){assert.equal(options.headers['X-CSRF-Token'],'renewed');assert.deepEqual(JSON.parse(options.body),{filename:'test.csv'});writes++}
  return response({ok:true})
 })
 const results=await Promise.all([apiGet('/aps/import/reviews'),apiRequest('/aps/import/inspect','POST',{filename:'test.csv'})])
 assert.deepEqual(results,[{ok:true},{ok:true}]);assert.equal(sessions,1);assert.equal(profiles,1);assert.equal(writes,1);assert.equal(auth.csrf,'renewed')
})
test('Real authenticated environments never select a demo profile on 401',async()=>{
 const auth=authState();auth.publicDemo=false;const fetch=mock.method(globalThis,'fetch',async()=>unauthenticated())
 await assert.rejects(apiGet('/aps/import/reviews'));assert.equal(fetch.mock.calls.length,1)
})
test('Server must independently confirm demo mode before profile recovery',async()=>{
 authState();let profiles=0
 mock.method(globalThis,'fetch',async url=>{if(url.endsWith('/auth/session'))return response({public_demo:false,user:null});if(url.endsWith('/auth/local-profile'))profiles++;return unauthenticated()})
 await assert.rejects(apiGet('/aps/import/reviews'));assert.equal(profiles,0)
})
test('A different active profile or an explicit logout prevents automatic reselection',async()=>{
 for(const changed of ['other-profile','logout']){
  const auth=authState();let profiles=0
  mock.method(globalThis,'fetch',async url=>{if(url.endsWith('/auth/session')){if(changed==='logout')auth.sessionGeneration++;return response({public_demo:true,user:changed==='other-profile'?{id:12,role:'worker'}:null,local_profiles:['admin'],csrf_token:'fresh'})}if(url.endsWith('/auth/local-profile'))profiles++;return unauthenticated()})
  await assert.rejects(apiGet('/aps/import/reviews'));assert.equal(profiles,0);mock.restoreAll()
 }
})
test('Forbidden operations never retry or change profiles',async()=>{
 authState();const fetch=mock.method(globalThis,'fetch',async()=>response(null,403,{code:'FORBIDDEN',message:'Sem permissão.'}))
 await assert.rejects(apiGet('/restricted'),{message:'Sem permissão.'});assert.equal(fetch.mock.calls.length,1)
})
test('Recovery retries the original operation once even if authentication still fails',async()=>{
 authState();let operations=0,profiles=0
 mock.method(globalThis,'fetch',async url=>{if(url.endsWith('/auth/session'))return response({public_demo:true,user:null,local_profiles:['admin'],csrf_token:'fresh'});if(url.endsWith('/auth/local-profile')){profiles++;return response({user:{id:11,role:'admin'},csrf_token:'renewed'})}operations++;return unauthenticated()})
 await assert.rejects(apiGet('/aps/import/reviews'));assert.equal(operations,2);assert.equal(profiles,1)
})
