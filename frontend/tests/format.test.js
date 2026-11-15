import test from 'node:test'
import assert from 'node:assert/strict'
import {formatarData,formatarDataHora,formatarNumero,formatarPercentual} from '../src/utils/format.js'
test('Brazilian presentation has exact date, time and decimal conventions',()=>{
assert.equal(formatarData('2026-11-15'),'15/11/2026')
assert.equal(formatarData('2026-02-30'),'—')
assert.equal(formatarDataHora('2026-11-15 09:03:40'),'15/11/2026 09:03')
assert.equal(formatarDataHora('2026-11-15T12:03:40Z'),'15/11/2026 09:03')
assert.equal(formatarPercentual(66.7),'66,7%')
assert.equal(formatarNumero(1234.5,2),'1.234,50')
assert.equal(formatarPercentual(null),'—')
})