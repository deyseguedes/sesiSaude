export const ACCESSIBILITY_KEY='life-accessibility-v1'
export const defaultAccessibility=()=>({textSize:100,contrast:false,spacing:false,links:false,reducedMotion:false})
export function normalizeAccessibility(value){
 const defaults=defaultAccessibility()
 if(!value||typeof value!=='object'||Array.isArray(value))return defaults
 return {textSize:[100,125,150].includes(value.textSize)?value.textSize:100,...Object.fromEntries(['contrast','spacing','links','reducedMotion'].map(key=>[key,value[key]===true]))}
}
export function readAccessibility(storage){try{return normalizeAccessibility(JSON.parse((storage??globalThis.localStorage).getItem(ACCESSIBILITY_KEY)))}catch{return defaultAccessibility()}}
export function saveAccessibility(value,storage){try{(storage??globalThis.localStorage).setItem(ACCESSIBILITY_KEY,JSON.stringify(normalizeAccessibility(value)));return true}catch{return false}}
export function applyAccessibility(root,value){
 const settings=normalizeAccessibility(value)
 if(settings.textSize===100)root.style.removeProperty('font-size');else root.style.fontSize=settings.textSize+'%'
 for(const key of ['contrast','spacing','links','reducedMotion'])root.setAttribute('data-life-'+key.replace(/[A-Z]/g,letter=>'-'+letter.toLowerCase()),String(settings[key]))
}
