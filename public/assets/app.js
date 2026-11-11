'use strict';
const jobSelect=document.querySelector('#user-job');
if(jobSelect){
 const code=document.querySelector('#user-job-code'),grade=document.querySelector('#user-job-grade'),category=document.querySelector('#user-job-category'),appointment=document.querySelector('#user-appointment');
 const options=[...grade.querySelectorAll('option[data-job]')].map(o=>o.cloneNode(true)),jobs=[...jobSelect.options].slice(1).map(o=>o.cloneNode(true));
 function updateJob(keep=false){
  const previous=keep?grade.value:null,official=appointment.value==='Trabajador oficial',job=jobSelect.selectedOptions[0];
  code.value=official?'':(job?.dataset.code||'');category.value=official?(job?.dataset.category||''):'';
  code.closest('label').hidden=official;grade.closest('label').hidden=official;category.closest('label').hidden=!official;
  grade.replaceChildren(new Option('Selecciona un grado',''));
  if(!official)options.filter(o=>o.dataset.job===jobSelect.value).forEach(o=>grade.add(o.cloneNode(true)));
  if(previous!==null&&[...grade.options].some(o=>o.value===previous))grade.value=previous;
  grade.disabled=official||!jobSelect.value;grade.required=!official&&!(grade.dataset.legacyJob===jobSelect.value&&grade.dataset.legacyBlank==='1');
 }
 function updateGroup(keep=false){const selected=keep?jobSelect.value:'',group=appointment.value==='Trabajador oficial'?'official_worker':'regular';jobSelect.replaceChildren(new Option('Selecciona un cargo',''));jobs.filter(o=>o.dataset.group===group).forEach(o=>jobSelect.add(o.cloneNode(true)));jobSelect.value=selected;updateJob(keep);}
 jobSelect.addEventListener('change',()=>updateJob());appointment.addEventListener('change',()=>updateGroup());updateGroup(true);
}
const catalogForm=document.querySelector('#job-catalog-form');
if(catalogForm){const group=catalogForm.elements.employment_group;const update=()=>{const official=group.value==='official_worker';for(const name of ['code','grade']){const f=catalogForm.elements[name];if(f){f.closest('label').hidden=official;f.disabled=official;f.required=!official&&(name==='code'||catalogForm.dataset.needGrade==='1');}}const category=catalogForm.elements.official_category_id;category.closest('label').hidden=!official;category.disabled=!official;category.required=official;};group.addEventListener('change',update);update();}

document.querySelector('.mobile-menu')?.addEventListener('click',e=>{const open=document.body.classList.toggle('menu-open');e.currentTarget.setAttribute('aria-expanded',String(open));});
document.querySelectorAll('form[data-confirm]').forEach(form=>form.addEventListener('submit',e=>{if(!confirm(form.dataset.confirm))e.preventDefault();}));
document.querySelectorAll('form[data-policy-form]').forEach(policy=>{
 const toggle=policy.querySelector('[name=supports_required]'),description=policy.querySelector('[name=supports]');
 const update=()=>{description.required=toggle.checked;};
 toggle.addEventListener('change',update);update();
});
const form=document.querySelector('#request-form');
if(form){
 // Mantener el mismo formulario evita que el navegador vacíe los campos de archivo.
 let sending=false;
 const sendButton=form.querySelector('button[type="submit"]'),sendLabel=sendButton.innerHTML;
 let submitError=document.querySelector('#main > .alert.error');
 form.addEventListener('submit',async event=>{
  event.preventDefault();if(sending)return;
  const upload=form.querySelector('[name="supports[]"]');
  const files=[...upload.files];
  if(files.length && files.length+Number(upload.dataset.existingSupports||0)>5){
   showSubmitError('Una solicitud admite hasta cinco soportes en total.');return;
  }
  if(files.some(file=>file.size>2*1024*1024)){
   showSubmitError('Cada soporte nuevo debe pesar máximo 2 MB.');return;
  }
  const payload=new FormData(form);
  sending=true;sendButton.disabled=true;sendButton.textContent='Enviando solicitud…';
  if(submitError)submitError.hidden=true;
  try{
   const response=await fetch(form.getAttribute('action')||window.location.href,{method:'POST',body:payload,credentials:'same-origin'});
   if(response.redirected){window.location.assign(response.url);return;}
   const page=new DOMParser().parseFromString(await response.text(),'text/html');
   const validation=page.querySelector('#main > .alert.error');
   if(validation)showSubmitError(validation.textContent.trim());
   else showSubmitError('No se pudo confirmar el envío. Los archivos siguen seleccionados. Consulta Mis solicitudes antes de volver a intentar.');
  }catch{
   showSubmitError('No se pudo confirmar el envío. Los archivos siguen seleccionados. Revisa la conexión y consulta Mis solicitudes antes de volver a intentar.');
  }finally{sending=false;sendButton.disabled=false;sendButton.innerHTML=sendLabel;}
 });
 function showSubmitError(message){
  if(!submitError){submitError=document.createElement('div');submitError.className='alert error';submitError.setAttribute('role','alert');submitError.tabIndex=-1;form.querySelector('.request-main').prepend(submitError);}
  submitError.textContent=message;submitError.tabIndex=-1;submitError.hidden=false;submitError.scrollIntoView({behavior:'smooth',block:'center'});submitError.focus();
 }
 document.querySelector('#supports-retention-note')?.removeAttribute('hidden');
 const slots=document.querySelector('#slots'),category=document.querySelector('#category'),medicalAnswer=document.querySelector('#medical-appointment');let next=slots.children.length,sequence=0;
 const annualAvailability=JSON.parse(form.dataset.annualAvailability),birthdayYear=form.elements.birthday_year;
 const addSlot=document.querySelector('#add-slot'),annualGuide=document.querySelector('#annual-days-guide');
 const recessGuide=document.querySelector('#school-break-guide');
 const bereavementGuide=document.querySelector('#bereavement-guide');
 const bereavementDays=bereavementGuide.querySelector('ol');
 const fmt=m=>`${Math.floor(m/60)} h ${String(m%60).padStart(2,'0')} min`;
 function updateSupports(){
  const selected=category.selectedOptions[0],ordinary=selected?.dataset.slug==='ordinario';
  const medical=ordinary&&medicalAnswer.value==='1',required=medical||selected?.dataset.supportsRequired==='1',upload=form.querySelector('[name="supports[]"]');
  medicalAnswer.closest('label').hidden=!ordinary;
  medicalAnswer.disabled=!ordinary;
  medicalAnswer.required=ordinary;
  upload.required=required&&Number(upload.dataset.existingSupports||0)===0;
  document.querySelector('#supports-label').textContent=selected?.dataset.slug?(required?'Selecciona tus archivos *':'Selecciona tus archivos (opcional)'):'Selecciona tus archivos';
  document.querySelector('#supports-guide').textContent=medical?'Soporte obligatorio. Adjunta un documento de la cita médica.':!selected?.dataset.slug?'Selecciona un tipo para consultar los soportes.':`${required?'Soporte obligatorio.':'Soporte opcional.'} ${selected.dataset.supports||'Puedes adjuntar documentos si lo consideras necesario.'}`;
 }
 function updateAllocations(){
  const panel=document.querySelector('#allocation-panel'),message=document.querySelector('#allocation-summary'),inputs=[...panel.querySelectorAll('[data-allocation-hours]')];
  inputs.forEach(input=>input.setCustomValidity(''));
  if(category.selectedOptions[0]?.dataset.slug!=='acto')return;
  const allocated=inputs.reduce((sum,input)=>sum+Math.round(Number(input.value||0)*60),0),requested=[...slots.children].reduce((sum,row)=>sum+Number(row.dataset.minutes||0),0),limit=Number(panel.dataset.dailyLimit);
  const days={};for(const row of slots.children){const date=row.querySelector('[type=date]').value;if(date)days[date]=(days[date]||0)+Number(row.dataset.minutes||0);}let error='';
  const exceeded=Object.keys(days).find(date=>days[date]>limit);
  if(exceeded)error=`El ${exceeded} supera el máximo de ${fmt(limit)} por jornada.`;
  else if(allocated!==requested)error='La suma de los actos debe coincidir con el tiempo solicitado.';
  message.textContent=`Seleccionado en actos: ${fmt(allocated)} · Solicitado: ${fmt(requested)} · Máximo diario: ${fmt(limit)}${error?' · '+error:''}`;
  message.className=error?'alert warning':'alert success';
  if(error&&inputs.length)inputs[0].setCustomValidity(error);
 }
 function updateDayFields(slug){
  const annual=Object.hasOwn(annualAvailability,slug),recess=slug==='receso',jury=slug==='jurado';
  const year=slug==='cumpleanos'?birthdayYear.value:form.dataset.year;
  const available=annual?annualAvailability[slug][year]:null;
  const bereavement=slug==='luto',fullOnly=annual||jury||bereavement,rows=[...slots.children];
  const dates=rows.map(row=>{
   const date=row.querySelector('input[type=date]'),choice=row.querySelector('[data-recess-date]'),mode=row.querySelector('[data-mode]');
   date.hidden=date.disabled=recess;date.required=!recess;
   choice.hidden=choice.disabled=!recess;choice.required=recess;
   date.setCustomValidity('');choice.setCustomValidity('');
   mode.querySelector('option[value=hours]').disabled=fullOnly;
   mode.querySelector('option[value=full]').disabled=slug==='acto';
   if(fullOnly)mode.value='full';else if(slug==='acto')mode.value='hours';
   mode.disabled=bereavement;
   row.querySelector('[data-slot-date-label]').textContent=bereavement?'Primera fecha de la licencia *':'Fecha de la jornada *';
   row.querySelector('.remove-slot').disabled=bereavement;
   return recess?choice:date;
  });
  const recessCount=recess?dates[0].querySelectorAll('option[value]:not([value=""]):not([data-ended])').length:Infinity;
  const limit=Math.min(jury||bereavement?1:90,available??Infinity,recessCount);
  addSlot.disabled=rows.length>=limit;
  addSlot.hidden=bereavement;
  bereavementGuide.hidden=!bereavement;
  bereavementDays.replaceChildren();
  annualGuide.hidden=!annual;
  recessGuide.hidden=!recess;
  if(annual){
   const balance=available===null?`Sin límite anual para ${year}.`:`Días completos disponibles en ${year}: ${available}.`;
   const error=rows.length>limit?(limit===0?'No hay días disponibles para esta solicitud.':'Elimina las fechas que superen los días disponibles.'):'';
   annualGuide.textContent=balance+(error&&recessCount!==0?' '+error:'');
   if(error)dates[0].setCustomValidity(error);
  }
  if(recess)for(const date of dates){
   const used=new Set(dates.filter(other=>other!==date).map(other=>other.value).filter(Boolean));
   for(const option of date.options)option.disabled=option.hasAttribute('data-ended')||(used.has(option.value)&&option.value!==date.value);
   if(date.value&&used.has(date.value))date.setCustomValidity('Selecciona una fecha de receso diferente para cada jornada.');
  }
  return dates;
 }
 document.querySelector('#allocation-panel').addEventListener('input',updateAllocations);
 async function refresh(){
  updateSupports();
  const seq=++sequence;let total=0;
  const selected=category.selectedOptions[0],slug=selected?.dataset.slug;
  const dates=updateDayFields(slug);
  await Promise.all([...slots.children].map(async (row,index)=>{
   const date=dates[index],times=row.querySelectorAll('[type=time]'),full=row.querySelector('[data-mode]').value==='full';times.forEach(t=>t.disabled=full);
   if(!date.value){row.dataset.minutes='0';row.querySelector('.slot-result').textContent='';return;}
   if(slug==='luto'){
    date.setCustomValidity('Espera a que se calculen las seis jornadas.');
    row.dataset.minutes='0';
    row.querySelector('.slot-result').textContent='Calculando las seis jornadas…';
    try {
     const params=new URLSearchParams({page:'estimate',kind:'luto',date:date.value});
     const response=await fetch('/?'+params),data=await response.json();
     if(seq!==sequence)return;
     if(!response.ok)throw new Error(data.error||'No se pudieron calcular las jornadas.');
     for(const day of data.slots.slice(1)){
      const item=document.createElement('li');
      item.textContent=day.date.split('-').reverse().join('/')+' · '+fmt(day.minutes);
      bereavementDays.append(item);
     }
     row.dataset.minutes=String(data.slots.reduce((sum,day)=>sum+day.minutes,0));
     times[0].value=data.slots[0].start_time;times[1].value=data.slots[0].end_time;
     row.querySelector('.slot-result').textContent=fmt(data.slots[0].minutes);
     date.setCustomValidity('');
    }catch(error){
     if(seq!==sequence)return;
     const message=error instanceof SyntaxError?'No se pudieron calcular las jornadas.':error.message;
     row.querySelector('.slot-result').textContent=message;
     date.setCustomValidity(message);
    }
    return;
   }
   const p=new URLSearchParams({page:'estimate',date:date.value});if(!full){p.set('start',times[0].value);p.set('end',times[1].value);}
   try{const response=await fetch('/?'+p);if(!response.ok)throw new Error();const data=await response.json();if(seq!==sequence)return;if(full&&data.periods.length){times[0].value=data.periods[0][0];times[1].value=data.periods[data.periods.length-1][1];}row.dataset.minutes=String(data.minutes);row.querySelector('.slot-result').textContent=data.periods.length?fmt(data.minutes):'Día no laborable';}
   catch{if(seq===sequence){row.dataset.minutes='0';row.querySelector('.slot-result').textContent='Revisa las fechas y el horario.';}}
  }));
  if(seq!==sequence)return;
  [...slots.children].forEach(row=>total+=Number(row.dataset.minutes||0));document.querySelector('#total-minutes').textContent=fmt(total);
  const high=document.querySelector('#route-guide').dataset.highRank;
  document.querySelector('#allocation-panel').hidden=slug!=='acto';document.querySelector('#relationship-field').hidden=slug!=='luto';document.querySelector('[name=relationship]').required=slug==='luto';
  document.querySelector('#birthday-year-field').hidden=slug!=='cumpleanos';
  document.querySelector('#jury-role-field').hidden=slug!=='jurado';document.querySelector('#jury-role').required=slug==='jurado';
  document.querySelector('#electoral-guide').hidden=slug!=='sufragar';
  updateAllocations();
  const guide=document.querySelector('#route-guide');guide.textContent=!slug?'Selecciona el tipo y las fechas.':high&&selected.dataset.family==='permiso'?'Alcalde · aprobación exclusiva':slug==='ordinario'&&total>0&&total<=120?'Jefe inmediato · aprobación única':'Jefe inmediato → Subsecretario de Talento Humano';
 }
 slots.addEventListener('change',refresh);
 category.addEventListener('change',()=>{
  const firstRow=slots.firstElementChild;
  slots.replaceChildren(firstRow);
  firstRow.querySelector('input[type=date]').value='';
  firstRow.querySelector('[data-recess-date]').value='';
  firstRow.querySelector('[data-mode]').value='hours';
  firstRow.querySelectorAll('[data-request-time]').forEach(input=>{input.value=input.defaultValue;});
  refresh();
 });
 birthdayYear.addEventListener('change',refresh);medicalAnswer.addEventListener('change',updateSupports);
 slots.addEventListener('click',e=>{const btn=e.target.closest('.remove-slot');if(btn&&slots.children.length>1){btn.closest('.slot-row').remove();refresh();}});
 addSlot.addEventListener('click',()=>{
  const copy=slots.firstElementChild.cloneNode(true);
  copy.querySelectorAll('[name]').forEach(input=>{input.name=input.name.replace(/slots\[\d+\]/,`slots[${next}]`);});
  copy.querySelector('input[type=date]').value='';
  copy.querySelector('[data-recess-date]').value='';
  copy.querySelector('.slot-result').textContent='';copy.dataset.minutes='0';
  next++;slots.append(copy);refresh();copy.querySelector('input[type=date]:not(:disabled),[data-recess-date]:not(:disabled)').focus();
 });refresh();
}

const bulkForm=document.querySelector('#bulk-approval-form');
if(bulkForm){
 const choices=[...bulkForm.querySelectorAll('input[name="approval_ids[]"]')],all=bulkForm.querySelector('#bulk-select-all'),count=bulkForm.querySelector('#bulk-selected-count'),button=bulkForm.querySelector('#bulk-approve-button');
 const update=()=>{const selected=choices.filter(input=>input.checked).length;count.textContent=`${selected} seleccionada(s)`;button.disabled=selected===0;all.checked=selected>0&&selected===choices.length;all.indeterminate=selected>0&&selected<choices.length;};
 bulkForm.querySelector('#bulk-select-all-label').hidden=false;count.hidden=false;
 all.addEventListener('change',()=>{choices.forEach(input=>input.checked=all.checked);update();});choices.forEach(input=>input.addEventListener('change',update));
 bulkForm.addEventListener('submit',event=>{if(!choices.some(input=>input.checked)){event.preventDefault();return;}button.disabled=true;button.textContent='Procesando aprobaciones…';});update();
}
const balanceForm=document.querySelector('form[data-balance-filters]');
if(balanceForm){
 const start=balanceForm.elements.from,end=balanceForm.elements.to;
 const validateYear=()=>end.setCustomValidity(start.value&&end.value&&start.value.slice(0,4)!==end.value.slice(0,4)?'La fecha de inicio y la fecha final deben pertenecer al mismo año.':'');
 start.addEventListener('change',validateYear);end.addEventListener('change',validateYear);validateYear();
}
document.querySelector('[name="schedule_group"]')?.addEventListener('change',function(){
 const worker=this.value==='official_worker';
 for(let day=1;day<=5;day++)for(let i=0;i<3;i++)for(let side=0;side<2;side++){
  const field=this.form.elements.namedItem(`periods[${day}][${i}][${side}]`);
  if(field)field.value=worker?(i===0?(side===0?'07:00':'15:00'):''):(i===0?(side===0?(day===5?'07:00':'07:30'):'12:00'):i===1?(side===0?(day===5?'12:30':'13:00'):(day===5?'16:00':'17:00')):'');
 }
});
