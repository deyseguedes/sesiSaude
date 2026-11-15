import test from 'node:test'
import assert from 'node:assert/strict'
import {guideFor} from '../src/utils/on-screen-guide.js'
test('each profile gets a readable on-screen guide without executable actions',()=>{
 for(const role of ['admin','manager','doctor','nurse','navigator','worker','dependent']){
  const steps=guideFor({role,management_level:role==='admin'?'platform':role==='manager'?'company':'none'},'/')
  assert.ok(steps.length>=3)
  for(const step of steps){assert.deepEqual(Object.keys(step),['target','title','text']);assert.ok(step.target&&step.title&&step.text)}
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
