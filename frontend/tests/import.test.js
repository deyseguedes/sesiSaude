import test from 'node:test';import assert from 'node:assert/strict';import {readFileSync} from 'node:fs';import {parseCsv,mapHeaders,populatedRows} from '../src/utils/import-aps.js'
test('All 32 original SOC labels have unique destinations without changing source headers',()=>{
 const profile=JSON.parse(readFileSync(new URL('../../docs/diagnostico-aps-sesi/qualidade-agregada.json',import.meta.url),'utf8'))
 const headers=profile.columns.map(c=>c.header),original=[...headers],mapping=Object.values(mapHeaders(headers))
 assert.equal(mapping.length,32);assert.ok(mapping.every(Boolean));assert.equal(new Set(mapping).size,32);assert.deepEqual(headers,original)
})
test('Formatted blank rows do not consume the import limit and orphan observations remain',()=>{
 const rows=[['matricula','nome'],['001','Pessoa'],...Array.from({length:1290},()=>Array(32)),[,,,46341]]
 assert.equal(populatedRows(rows).length,3);assert.equal(populatedRows(rows)[2][3],46341)
})
test('CSV mapping ignores case and accents and supports quoted cells',()=>{assert.deepEqual(mapHeaders(['MATRÍCULA','Nome','Empresa','STATUS']),{0:'registration',1:'name',2:'company',3:'status'});assert.deepEqual(parseCsv('nome;empresa\n"Pessoa; teste";"Indústria ""A"""'),[['nome','empresa'],['Pessoa; teste','Indústria "A"']]);assert.throws(()=>parseCsv('nome\n"aberto'))})
