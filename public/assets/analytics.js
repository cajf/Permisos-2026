'use strict';
(()=>{
 const families=[['permiso','Permisos'],['compensatorio','Compensatorios'],['licencia','Licencias']];
 const quantity=new Intl.NumberFormat('es-CO');
 const percentage=new Intl.NumberFormat('es-CO',{style:'percent',maximumFractionDigits:1});
 const departmentOrder=new Intl.Collator('es-CO');
 document.querySelectorAll('[data-analytics-board], [data-analytics-distribution], [data-analytics-medical]').forEach(board=>{
 const form=board.querySelector('form'),status=board.querySelector('[role=status]');let busy=false,authorized=true,sequence=0;
 const distribution=board.hasAttribute('data-analytics-distribution');
 const medical=board.hasAttribute('data-analytics-medical');
 const yearField=medical?'medical_year':distribution?'distribution_year':'year';
 const text=(tag,value)=>{const node=document.createElement(tag);node.textContent=String(value);return node;};
 function renderDistribution(data){
  const chart=board.querySelector('.analytics-distribution-chart'),fragment=document.createDocumentFragment();
  for(const [key,label] of families){
   const group=document.createElement('section');group.className='analytics-distribution-group';group.append(text('h3',label));
   const total=data.counts[key];
   if(!total)group.append(text('p','No hay solicitudes aprobadas de este tipo en la vigencia seleccionada.'));
   // Dentro de cada tipo el denominador es común: ordenar por cantidad equivale a ordenar por porcentaje exacto.
   const ranked=[...data.departments].sort((a,b)=>b[key]-a[key]||departmentOrder.compare(a.name,b.name));
   for(const department of ranked){
    const count=department[key],share=total?count/total:0;
    const row=document.createElement('div');row.className='analytics-distribution-row';
    const bar=document.createElement('progress');bar.max=100;bar.value=share*100;bar.className=`analytics-progress-${key}`;bar.setAttribute('aria-hidden','true');
    const value=text('strong',total?`${quantity.format(count)} · ${share>0&&share<0.0005?`<${percentage.format(0.001)}`:percentage.format(share)}`:'0 · Sin datos');
    row.append(text('span',department.name),bar,value);group.append(row);
   }
   fragment.append(group);
  }
  chart.replaceChildren(fragment);
 }
 function render(data){
  const years=form.elements[yearField];
  years.replaceChildren(...data.years.map(y=>new Option(String(y),String(y))),new Option('Todas las vigencias','all'));years.value=data.year;
  status.textContent=`Actualizado: ${data.generated_at} · Bogotá. Actualización automática cada 30 segundos.`;
  if(medical){
   const {total,appointments}=data.medical,share=total?appointments/total:0,bar=board.querySelector('progress');
   board.querySelector('.analytics-medical-result').textContent=total?`${quantity.format(appointments)} de ${quantity.format(total)} permisos · ${percentage.format(share)}`:'No hay permisos Ordinario-Remunerado aprobados para esta selección.';
   bar.value=share*100;board.querySelector('.analytics-medical-chart').hidden=!total;return;
  }
  if(distribution){renderDistribution(data);return;}
  for(const [key,value] of Object.entries(data.counts))board.querySelector(`[data-count="${key}"]`).textContent=value;
  const tbody=board.querySelector('tbody');tbody.replaceChildren();
  data.departments.forEach(d=>{const row=document.createElement('tr');[d.name,d.permiso,d.compensatorio,d.licencia,d.total].forEach(v=>row.append(text('td',v)));tbody.append(row);});
  const trend=board.querySelector('.analytics-trend');trend.replaceChildren();
  const max=Math.max(1,...data.trend.map(r=>r.permiso+r.compensatorio+r.licencia));
  data.trend.forEach(r=>{const article=document.createElement('article');article.append(text('h3',r.year));const bar=document.createElement('div');bar.className='analytics-bar';
   [['permiso','Permisos'],['compensatorio','Compensatorios'],['licencia','Licencias']].forEach(([key,label])=>{const span=text('span',r[key]||'');span.className=`analytics-${key}`;span.style.width=`${r[key]/max*100}%`;span.title=`${label}: ${r[key]}`;bar.append(span);});article.append(bar,text('p',`Permisos ${r.permiso} · Compensatorios ${r.compensatorio} · Licencias ${r.licencia}`));trend.append(article);});
  if(!data.trend.length)trend.append(text('p','Sin solicitudes registradas para esta selección.'));
  const departments=form.elements.department,previous=departments.value;departments.replaceChildren(new Option('Todas las dependencias','all'),...data.department_options.map(d=>new Option(d.name,String(d.id))));departments.value=previous;
  board.querySelector('a').href='/?page=analytics_export&'+new URLSearchParams({metric:form.elements.metric.value,year:form.elements.year.value,department:form.elements.department.value});
 }
 async function refresh(){if(busy||!board.open||!authorized)return;busy=true;const current=sequence,params=new URLSearchParams(new FormData(form));params.set('page','analytics_data');if(distribution||medical){params.set('year',params.get(yearField));params.delete(yearField);params.set('metric','approved');params.set('department','all');}const controller=new AbortController(),timer=setTimeout(()=>controller.abort(),20000);
  try{const response=await fetch('/?'+params,{credentials:'same-origin',cache:'no-store',signal:controller.signal});if(response.redirected||response.status===403||response.status===401){authorized=false;board.querySelector('.analytics-panel-content').replaceChildren(text('p','Su sesión o autorización cambió. Ingrese nuevamente para consultar el tablero.'));clearInterval(interval);return;}if(!response.ok||!response.headers.get('content-type')?.includes('application/json'))throw new Error();const data=await response.json();if(current===sequence)render(data);}
  catch{if(current===sequence)status.textContent='No se pudo actualizar. Se mantienen los últimos datos visibles; se reintentará automáticamente.';}finally{clearTimeout(timer);busy=false;if(current!==sequence)refresh();}
 }
 form.addEventListener('submit',event=>{event.preventDefault();sequence++;refresh();});form.addEventListener('change',()=>{sequence++;refresh();});
 render(JSON.parse(board.querySelector('.analytics-initial').textContent));const interval=setInterval(()=>{if(!document.hidden)refresh();},30000);document.addEventListener('visibilitychange',()=>{if(!document.hidden)refresh();});board.addEventListener('toggle',()=>{if(!document.hidden)refresh();});
});})();
