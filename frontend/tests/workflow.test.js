import { test } from 'node:test'
import assert from 'node:assert/strict'
import { initialFieldValue, normalizeSearch, defaultPlan, navigationFor, formatDate, formatDateTime } from '../src/utils/workflow.js'

test('preselects a unique required choice without selecting an optional field', () => {
  const options = [{ value: 7, label: 'Equipe APS' }]
  assert.equal(initialFieldValue({ name: 'team', options }), 7)
  assert.equal(initialFieldValue({ name: 'team', options, optional: true }), '')
  assert.equal(initialFieldValue({ name: 'team', options, noAutoSelect: true }), '')
})
test('never authorizes checkboxes implicitly and preserves explicit false/null values', () => {
  assert.equal(initialFieldValue({ name: 'authorize_team', type: 'checkbox' }), false)
  assert.equal(initialFieldValue({ name: 'verified', type: 'checkbox', default: true }, { verified: false }), false)
  assert.equal(initialFieldValue({ name: 'unit', default: 1 }, { unit: null }), null)
  assert.deepEqual(initialFieldValue({ name: 'contents', type: 'checkbox_group' }), [])
})
test('uses the requested plan only if present, otherwise opens the active plan', () => {
  const plans = [{ id: 1, status: 'closed' }, { id: 2, status: 'active' }, { id: 3, status: 'paused' }]
  assert.equal(defaultPlan(plans, 3).id, 3)
  assert.equal(defaultPlan(plans, 999).id, 2)
  assert.equal(defaultPlan([]), null)
})
test('search ignores accents and casing', () => assert.equal(normalizeSearch('  EVOLUÇÃO  '), 'evolucao'))
test('beneficiaries get only individual navigation', () => {
  for (const role of ['worker', 'dependent']) {
    const nav = navigationFor({ role, management_level: 'none' })
    assert.deepEqual(nav.primary.map(n => n.to), ['/', '/find-care', '/my-care'])
    assert.deepEqual(nav.secondary.map(n=>n.to), ['/security'])
    assert.ok(nav.actions.every(a => !a.to.startsWith('/management')))
  }
})
test('management navigation does not grant clinical links', () => {
  const nav = navigationFor({ role: 'manager', management_level: 'company' })
  assert.ok(nav.primary.some(n => n.to === '/management'))
  assert.ok(![...nav.primary, ...nav.secondary].some(n => ['/patients', '/my-agenda', '/continuity'].includes(n.to)))
  assert.ok(!nav.actions.some(a => a.to.includes('new=slot')))
})
test('clinical coordination does not grant contracts or invitations', () => {
  const nav = navigationFor({ role: 'nurse', management_level: 'coordination' })
  assert.ok(nav.primary.some(n => n.to === '/patients'))
  assert.ok(![...nav.primary,...nav.secondary,...nav.actions].some(n => n.to.startsWith('/management')))
  assert.ok(!navigationFor({ role: 'doctor', management_level: 'none' }).actions.some(a => a.to.startsWith('/management')))
})
test('navigation for a signed-out user is empty', () => assert.deepEqual(navigationFor(null), { primary: [], secondary: [], actions: [] }))
test('dates are formatted for the care timezone', () => {
  assert.equal(formatDate('2026-11-14'), '14/11/2026')
  assert.match(formatDateTime('2026-11-14 09:30:00'), /14\/11\/2026.*09:30/)
  assert.equal(formatDateTime('2026-11-14T12:30:00Z'),formatDateTime('2026-11-14 09:30:00'))
  assert.equal(formatDate(null), '—')
})
