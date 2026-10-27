'use strict';
document.querySelector('.mobile-menu')?.addEventListener('click',e=>{const open=document.body.classList.toggle('menu-open');e.currentTarget.setAttribute('aria-expanded',String(open));});
document.querySelectorAll('form[data-confirm]').forEach(form=>form.addEventListener('submit',e=>{if(!confirm(form.dataset.confirm))e.preventDefault();}));
document.querySelectorAll('form[data-policy-form]').forEach(policy=>{
 const toggle=policy.querySelector('[name=supports_required]'),description=policy.querySelector('[name=supports]');
 const update=()=>{description.required=toggle.checked;};
 toggle.addEventListener('change',update);update();
});
const form=document.querySelector('#request-form');
if(form){
 const slots=document.querySelector('#slots'),category=document.querySelector('#category');let next=slots.children.length,sequence=0;
 const fmt=m=>`${Math.floor(m/60)} h ${String(m%60).padStart(2,'0')} min`;
 function updateSupports(){
  const selected=category.selectedOptions[0],required=selected?.dataset.supportsRequired==='1',upload=form.querySelector('[name="supports[]"]');
  upload.required=required&&Number(upload.dataset.existingSupports||0)===0;
  document.querySelector('#supports-label').textContent=selected?.dataset.slug?(required?'Selecciona tus archivos *':'Selecciona tus archivos (opcional)'):'Selecciona tus archivos';
  document.querySelector('#supports-guide').textContent=!selected?.dataset.slug?'Selecciona un tipo para consultar los soportes.':`${required?'Soporte obligatorio.':'Soporte opcional.'} ${selected.dataset.supports||'Puedes adjuntar documentos si lo consideras necesario.'}`;
 }
 async function refresh(){
  updateSupports();
  const seq=++sequence;let total=0;
  await Promise.all([...slots.children].map(async row=>{
   const date=row.querySelector('[type=date]'),times=row.querySelectorAll('[type=time]'),full=row.querySelector('select').value==='full';times.forEach(t=>t.disabled=full);
   if(!date.value){row.dataset.minutes='0';row.querySelector('.slot-result').textContent='';return;}
   const p=new URLSearchParams({page:'estimate',date:date.value});if(!full){p.set('start',times[0].value);p.set('end',times[1].value);}
   try{const response=await fetch('/?'+p);if(!response.ok)throw new Error();const data=await response.json();if(seq!==sequence)return;if(full&&data.periods.length){times[0].value=data.periods[0][0];times[1].value=data.periods[data.periods.length-1][1];}row.dataset.minutes=String(data.minutes);row.querySelector('.slot-result').textContent=data.periods.length?fmt(data.minutes):'Día no laborable';}
   catch{if(seq===sequence){row.dataset.minutes='0';row.querySelector('.slot-result').textContent='Revisa las fechas y el horario.';}}
  }));
  if(seq!==sequence)return;
  [...slots.children].forEach(row=>total+=Number(row.dataset.minutes||0));document.querySelector('#total-minutes').textContent=fmt(total);
  const selected=category.selectedOptions[0],slug=selected?.dataset.slug,high=document.querySelector('#route-guide').dataset.highRank;
  document.querySelector('#allocation-panel').hidden=slug!=='acto';document.querySelector('#relationship-field').hidden=slug!=='luto';document.querySelector('[name=relationship]').required=slug==='luto';
  const guide=document.querySelector('#route-guide');guide.textContent=!slug?'Selecciona el tipo y las fechas.':high&&selected.dataset.family==='permiso'?'Alcalde · aprobación exclusiva':slug==='ordinario'&&total>0&&total<=120?'Jefe inmediato · aprobación única':'Jefe inmediato → Subsecretario de Talento Humano';
 }
 slots.addEventListener('change',refresh);category.addEventListener('change',refresh);
 slots.addEventListener('click',e=>{const btn=e.target.closest('.remove-slot');if(btn&&slots.children.length>1){btn.closest('.slot-row').remove();refresh();}});
 document.querySelector('#add-slot').addEventListener('click',()=>{const copy=slots.firstElementChild.cloneNode(true);copy.querySelectorAll('[name]').forEach(input=>{input.name=input.name.replace(/slots\[\d+\]/,`slots[${next}]`);if(input.type==='date')input.value='';if(input.type==='time')input.disabled=false;});copy.querySelector('select').value='hours';copy.querySelector('.slot-result').textContent='';copy.dataset.minutes='0';next++;slots.append(copy);copy.querySelector('input').focus();});refresh();
}

const bulkForm=document.querySelector('#bulk-approval-form');
if(bulkForm){
 const choices=[...bulkForm.querySelectorAll('input[name="approval_ids[]"]')],all=bulkForm.querySelector('#bulk-select-all'),count=bulkForm.querySelector('#bulk-selected-count'),button=bulkForm.querySelector('#bulk-approve-button');
 const update=()=>{const selected=choices.filter(input=>input.checked).length;count.textContent=`${selected} seleccionada(s)`;button.disabled=selected===0;all.checked=selected>0&&selected===choices.length;all.indeterminate=selected>0&&selected<choices.length;};
 bulkForm.querySelector('#bulk-select-all-label').hidden=false;count.hidden=false;
 all.addEventListener('change',()=>{choices.forEach(input=>input.checked=all.checked);update();});choices.forEach(input=>input.addEventListener('change',update));
 bulkForm.addEventListener('submit',event=>{if(!choices.some(input=>input.checked)){event.preventDefault();return;}button.disabled=true;button.textContent='Procesando aprobaciones…';});update();
}
