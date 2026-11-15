import test from 'node:test'
import assert from 'node:assert/strict'
import { suggestedReturn } from '../src/utils/clinical-return.js'
import { restoreFilters } from '../src/utils/worklist.js'
test('return suggestions use calendar months and clamp month end',()=>{
 assert.equal(suggestedReturn(1,'2026-08-31'),'2027-02-28')
 assert.equal(suggestedReturn(2,'2027-08-31'),'2028-02-29')
 assert.equal(suggestedReturn(3,'2026-11-30'),'2027-02-28')
})
test('undefined risk and doctor-defined risk never invent a date',()=>{
 assert.equal(suggestedReturn(4,'2026-11-14'),'')
 assert.equal(suggestedReturn(0,'2026-11-14'),'')
 assert.equal(suggestedReturn(3,''),'')
})
test('contact lost filter restores only supported value',()=>{
 assert.equal(restoreFilters('{"contact_state":"lost"}').contact_state,'lost')
 assert.equal(restoreFilters('{"contact_state":"anything"}').contact_state,'')
})
