// Calendar months, clamped at month end; no medical inference or risk assignment.
export function suggestedReturn(risk,date) {
 const months=risk===1||risk===2?6:risk===3?3:0
 if(!months||!/^\d{4}-\d{2}-\d{2}$/.test(date))return ''
 const [year,month,day]=date.split('-').map(Number)
 const target=new Date(Date.UTC(year,month-1+months,1))
 const last=new Date(Date.UTC(target.getUTCFullYear(),target.getUTCMonth()+1,0)).getUTCDate()
 target.setUTCDate(Math.min(day,last))
 return target.toISOString().slice(0,10)
}
