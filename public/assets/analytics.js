'use strict';
(()=>{
 const board=document.querySelector('#analytics-board');if(!board)return;
 const form=document.querySelector('#analytics-filters'),status=document.querySelector('#analytics-status');let busy=false,sequence=0;
 const text=(tag,value)=>{const node=document.createElement(tag);node.textContent=String(value);return node;};
 function render(data){
  for(const [key,value] of Object.entries(data.counts))board.querySelector(`[data-count="${key}"]`).textContent=value;
  const tbody=document.querySelector('#analytics-departments');tbody.replaceChildren();
  data.departments.forEach(d=>{const row=document.createElement('tr');[d.name,d.permiso,d.compensatorio,d.licencia,d.total].forEach(v=>row.append(text('td',v)));tbody.append(row);});
  const trend=document.querySelector('#analytics-trend');trend.replaceChildren();
  const max=Math.max(1,...data.trend.map(r=>r.permiso+r.compensatorio+r.licencia));
  data.trend.forEach(r=>{const article=document.createElement('article');article.append(text('h3',r.year));const bar=document.createElement('div');bar.className='analytics-bar';
   [['permiso','Permisos'],['compensatorio','Compensatorios'],['licencia','Licencias']].forEach(([key,label])=>{const span=text('span',r[key]||'');span.className=`analytics-${key}`;span.style.width=`${r[key]/max*100}%`;span.title=`${label}: ${r[key]}`;bar.append(span);});article.append(bar,text('p',`Permisos ${r.permiso} · Compensatorios ${r.compensatorio} · Licencias ${r.licencia}`));trend.append(article);});
  if(!data.trend.length)trend.append(text('p','Sin disfrute registrado para esta selección.'));
  const years=form.elements.year,selected=years.value;years.replaceChildren(...data.years.map(y=>new Option(String(y),String(y))),new Option('Todas las vigencias','all'));years.value=selected;
  const departments=form.elements.department,previous=departments.value;departments.replaceChildren(new Option('Todas las dependencias','all'),...data.department_options.map(d=>new Option(d.name,String(d.id))));departments.value=previous;
  status.textContent=`Actualizado: ${data.generated_at} · Bogotá. Actualización automática cada 30 segundos.`;
  document.querySelector('#analytics-export').href='/?page=analytics_export&'+new URLSearchParams({year:form.elements.year.value,department:form.elements.department.value});
 }
 async function refresh(){if(busy)return;busy=true;const current=sequence,params=new URLSearchParams(new FormData(form));params.set('page','analytics_data');const controller=new AbortController(),timer=setTimeout(()=>controller.abort(),20000);
  try{const response=await fetch('/?'+params,{credentials:'same-origin',cache:'no-store',signal:controller.signal});if(response.redirected||response.status===403||response.status===401){board.replaceChildren(text('p','Su sesión o autorización cambió. Ingrese nuevamente para consultar el tablero.'));clearInterval(interval);return;}if(!response.ok||!response.headers.get('content-type')?.includes('application/json'))throw new Error();const data=await response.json();if(current===sequence)render(data);}
  catch{if(current===sequence)status.textContent='No se pudo actualizar. Se mantienen los últimos datos visibles; se reintentará automáticamente.';}finally{clearTimeout(timer);busy=false;if(current!==sequence)refresh();}
 }
 form.addEventListener('submit',event=>{event.preventDefault();sequence++;refresh();});form.addEventListener('change',()=>{sequence++;refresh();});
 render(JSON.parse(document.querySelector('#analytics-initial').textContent));const interval=setInterval(()=>{if(!document.hidden)refresh();},30000);document.addEventListener('visibilitychange',()=>{if(!document.hidden)refresh();});
})();
