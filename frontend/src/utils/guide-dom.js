let sequence=0
export const visible=el=>!!el&&el.getClientRects().length>0&&getComputedStyle(el).visibility!=='hidden'
export function findGuideTarget(selector,root=document){
 try{return [...root.querySelectorAll(selector)].find(visible)||null}catch{return null}
}
export function readGuideContext(preferredForm=null){
 const panel=[...document.querySelectorAll('dialog.side-panel[open]:not(.screen-guide-manual)')].filter(visible).at(-1)||null
 const form=panel?findGuideTarget('form',panel):(visible(preferredForm)?preferredForm:null)
 let formData=null
 if(form){
  if(!form.dataset.guideRuntimeId)form.dataset.guideRuntimeId='guide-form-'+(++sequence)
  const scope='form[data-guide-runtime-id="'+form.dataset.guideRuntimeId+'"]',seen=new Set(),fields=[]
  for(const control of form.querySelectorAll('input,select,textarea')){
   if(!visible(control)||control.type==='hidden')continue
   const wrapper=control.closest('[data-guide-field]')
   const key=wrapper?.dataset.guideField||control.id
   if(!key||seen.has(key))continue
   seen.add(key)
   const label=wrapper?.dataset.guideLabel||[...form.querySelectorAll('label')].find(l=>l.htmlFor===control.id)?.textContent.trim()||control.getAttribute('aria-label')||'Campo do formulário'
   const selector=wrapper?scope+' [data-guide-field="'+CSS.escape(key)+'"]':'#'+CSS.escape(control.id)
   fields.push({key,label:label.slice(0,180),target:selector,type:control.tagName==='SELECT'?'select':control.type||'text',required:control.required,disabled:control.disabled,readOnly:control.readOnly})
  }
  formData={id:form.dataset.guideRuntimeId,target:scope,fields}
 }
 return {panel,context:{notFound:document.querySelector('main h1')?.textContent.trim()==='Página não encontrada',tab:document.querySelector('[data-guide-management-tab]')?.dataset.guideManagementTab||'',section:document.querySelector('[data-guide-patient-section]')?.dataset.guidePatientSection||'',form:formData}}
}
export function openGuideTarget(selector){
 const target=findGuideTarget(selector)
 if(!target||target.disabled)return false
 const anchor=target.closest('a[href]')
 if(anchor){const href=anchor.getAttribute('href');if(!href.startsWith('/')||href.startsWith('//'))return false;anchor.click();return true}
 if(target.matches('button[data-guide-open]')&&(!target.closest('form')||target.getAttribute('type')==='button')){target.click();return true}
 return false
}
export function focusGuideTarget(selector){
 const target=findGuideTarget(selector),control=target?.matches('input,select,textarea')?target:findGuideTarget('input,select,textarea',target||document.createElement('div'))
 if(!control||control.disabled)return false
 control.focus({preventScroll:true});control.scrollIntoView({block:'center',behavior:'instant'});return true
}
