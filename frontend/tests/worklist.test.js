import test from 'node:test'
import assert from 'node:assert/strict'
import { restoreFilters, filterKey, nextPending } from '../src/utils/worklist.js'

test('worklist restores only supported filters, separately for each user and company', () => {
  const filters=restoreFilters('{"team_id":"2","assigned_to":3,"deadline":"overdue","status":"open","help_requested":"1","patient_name":"private"}')
  assert.equal(filters.team_id,'2');assert.equal(filters.assigned_to,'3');assert.equal(filters.help_requested,'1')
  assert.equal(filters.patient_name,undefined)
  assert.notEqual(filterKey({ id:1,company_id:1 }),filterKey({ id:2,company_id:1 }))
  assert.notEqual(filterKey({ id:1,company_id:1 }),filterKey({ id:1,company_id:2 }))
  assert.equal(restoreFilters('bad').status,'active')
  assert.equal(restoreFilters('{"team_id":"1 OR 1=1","status":"bad","deadline":"bad"}').team_id,'')
})
test('save and next uses the original order, skips removed cases and never repeats current case', () => {
  assert.equal(nextPending([1,2,3,4],2,[{ id:1 },{ id:2 },{ id:4 }]),4)
  assert.equal(nextPending([1,2,3],3,[{ id:1 },{ id:3 }]),1)
  assert.equal(nextPending([1],1,[{ id:1 }]),null)
  assert.equal(nextPending([1,2],1,[]),null)
  assert.equal(nextPending(['1','2'],'1',[{ id:2 }]),'2')
})
