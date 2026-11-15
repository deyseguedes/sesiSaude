// Keep the server's alias catalogue identical to the UI catalogue.
import {writeFile} from 'node:fs/promises'
import {canonical} from '../frontend/src/utils/import-aps.js'
await writeFile(new URL('../backend/config/aps-columns.json',import.meta.url),JSON.stringify(canonical,null,2)+'\n')
