import test from 'node:test'
import assert from 'node:assert/strict'
import {guideFor} from '../src/utils/on-screen-guide.js'
test('each profile gets a readable on-screen guide without executable actions',()=>{
 for(const role of ['admin','manager','doctor','nurse','navigator','worker','dependent']){
  const steps=guideFor({role,management_level:role==='admin'?'platform':role==='manager'?'company':'none'},'/')
  assert.ok(steps.length>=3)
  for(const step of steps){assert.ok(Object.keys(step).every(k=>['target','title','text','action'].includes(k)));assert.ok(step.target&&step.title&&step.text);if(step.action)assert.ok(['open','focus'].includes(step.action.kind))}
 }
})
test('clinical instructions follow role and do not grant guidance for restricted pages',()=>{
 const doctor=guideFor({role:'doctor'},'/patients/1'),nurse=guideFor({role:'nurse'},'/patients/1'),worker=guideFor({role:'worker'},'/patients/1')
 assert.ok(doctor.some(s=>s.title==='Confirme risco e retorno'))
 assert.ok(nurse.some(s=>s.title.startsWith('Consulte o retorno')))
 assert.ok(!worker.some(s=>s.target.includes('clinical-return')))
 assert.ok(!guideFor({role:'worker'},'/nursing-worklist').some(s=>s.target==='.worklist-row'))
})
test('corporate settings are not described as nursing coordination controls',()=>{
 assert.ok(guideFor({role:'manager',management_level:'company'},'/management').some(s=>s.target.includes('company-gamification')))
 assert.ok(!guideFor({role:'nurse',management_level:'coordination'},'/management').some(s=>s.target.includes('company-gamification')))
})
test('guest, registration and open-panel instructions preserve the current context',()=>{
 assert.equal(guideFor(null,'/login').length,3)
 assert.ok(guideFor(null,'/register')[0].title.includes('convite'))
 assert.ok(guideFor({role:'nurse'},'/','Ficha de acompanhamento').every(s=>s.target.startsWith('dialog.side-panel[open]')))
})

test('guide covers secondary dashboards, management tabs and clinical sections',()=>{
 const user={role:'doctor',management_level:'coordination'}
 assert.equal(guideFor(user,'/care-dashboard')[0].title,'Indicadores assistenciais')
 assert.equal(guideFor(null,'/system')[0].title,'Status da plataforma')
 for(const tab of ['structure','contracts','agenda','access'])assert.ok(guideFor({role:'admin',management_level:'platform'},'/management','',{tab}).some(s=>s.action?.kind==='open'))
 for(const section of ['plan','records','indicators','goals','referral'])assert.ok(guideFor(user,'/patients/1','',{section})[0].target.includes('clinical-quick-actions'))
 assert.ok(guideFor(null,'/unknown','',{notFound:true})[1].action)
})
test('every form gets field guidance without including entered values or submitting',()=>{
 const form={id:'sample',target:'form',fields:[{target:'#email',label:'Email',type:'email',required:true,value:'private-value'},{target:'#password',label:'Password',type:'password',required:true,value:'secret-value'},{target:'#consent',label:'Consent',type:'checkbox',required:true}]}
 const result=guideFor({role:'admin'},'/management','Novo profissional',{form})
 assert.equal(result.length,5)
 assert.ok(result.filter(s=>s.action).every(s=>s.action.kind==='focus'))
 assert.ok(!JSON.stringify(result).includes('private-value'))
 assert.ok(!JSON.stringify(result).includes('secret-value'))
 assert.ok(result.at(-1).text.includes('decis'))
})
