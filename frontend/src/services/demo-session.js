import {requestJson} from './http.js'

const pending=new WeakMap()
export const sessionIdentity=auth=>({id:auth.user?.id,role:auth.user?.role,generation:auth.sessionGeneration||0})
const unchanged=(auth,expected)=>String(auth.user?.id)===String(expected.id)&&auth.user?.role===expected.role&&(auth.sessionGeneration||0)===expected.generation

// Restore only the already selected seeded demo profile. Real sessions never use this path.
export async function recoverDemoSession(auth,expected){
 if(!auth.publicDemo||!expected.id||!expected.role||!unchanged(auth,expected))return false
 if(pending.has(auth))return pending.get(auth)
 const recovery=(async()=>{
  const {data:session}=await requestJson('/api/v1/auth/session')
  if(session.public_demo!==true||!unchanged(auth,expected))return false
  let current=session
  if(session.user){
   if(String(session.user.id)!==String(expected.id)||session.user.role!==expected.role)return false
  }else{
   if(!session.local_profiles?.includes(expected.role))return false
   const response=await requestJson('/api/v1/auth/local-profile',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':session.csrf_token},body:JSON.stringify({role:expected.role})})
   current=response.data
  }
  if(!unchanged(auth,expected)||String(current.user?.id)!==String(expected.id)||current.user?.role!==expected.role)return false
  auth.user=current.user;auth.csrf=current.csrf_token
  return true
 })()
 pending.set(auth,recovery)
 try{return await recovery}finally{if(pending.get(auth)===recovery)pending.delete(auth)}
}
