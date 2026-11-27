<?php
ensure(canManageLegalizations($u),'Acceso exclusivo de Talento Humano autorizado.');
$search=trim((string)($_GET['q']??''));$type=(string)($_GET['type']??'all');$state=(string)($_GET['state']??'pending');
ensure(mb_strlen($search)<=180,'El filtro admite hasta 180 caracteres.');
ensure(in_array($type,['all','luto','matrimonio'],true) && in_array($state,['all','pending','legalized'],true),'Filtro no válido.');
$where=["c.slug IN ('luto','matrimonio')","r.status IN ('approved','cancel_pending')"];$params=[];
if($search!==''){$where[]='(u.name LIKE ? OR u.identification=?)';$params[]='%'.$search.'%';$params[]=$search;}
if($type!=='all'){$where[]='c.slug=?';$params[]=$type;}
if($state!=='all'){$where[]='COALESCE(l.legalized,0)=?';$params[]=$state==='legalized'?1:0;}
$from=' FROM requests r JOIN categories c ON c.id=r.category_id JOIN user_profiles u ON u.id=r.user_id LEFT JOIN request_legalizations l ON l.request_id=r.id LEFT JOIN user_profiles actor ON actor.id=l.updated_by WHERE '.implode(' AND ',$where);
$count=(int)row('SELECT COUNT(*) n'.$from,$params)['n'];$pages=max(1,(int)ceil($count/10));$current=min($pages,max(1,(int)($_GET['p']??1)));$offset=($current-1)*10;
$items=rows('SELECT r.*,c.name category,u.name,u.identification,l.legalized,l.updated_at legalization_at,l.version,actor.name legalization_actor'.$from.' ORDER BY r.created_at DESC,r.id DESC LIMIT 10 OFFSET '.$offset,$params);
?>
<p class="page-intro">Registra la recepción de los soportes de luto y matrimonio en Talento Humano. Marca o desmarca «Legalizada» y pulsa Guardar. No se requieren archivos ni motivo; el historial conserva cada cambio. La aprobación y las fechas de disfrute no se modifican.</p>
<section class="panel form-panel"><form method="get" class="filter-form"><input type="hidden" name="page" value="legalizations">
<label>Nombre o cédula<input name="q" value="<?=e($search)?>" maxlength="180"></label>
<label>Licencia<select name="type"><?php foreach(['all'=>'Todas','luto'=>'Luto','matrimonio'=>'Matrimonio'] as $key=>$label): ?><option value="<?=$key?>" <?=$type===$key?'selected':''?>><?=$label?></option><?php endforeach; ?></select></label>
<label>Legalización<select name="state"><?php foreach(['pending'=>'Pendiente de legalizar','legalized'=>'Legalizada','all'=>'Todas'] as $key=>$label): ?><option value="<?=$key?>" <?=$state===$key?'selected':''?>><?=$label?></option><?php endforeach; ?></select></label>
<button class="button secondary">Consultar</button></form></section>
<section class="panel form-panel"><h2>Licencias con aprobación final</h2><p><?=$count?> resultado(s) · Página <?=$current?> de <?=$pages?></p>
<?php if(!$items): ?><p>No hay licencias que coincidan con los filtros.</p><?php endif; ?>
<?php foreach($items as $r): $legalization=['legalized'=>$r['legalized'],'version'=>$r['version']];$legalizationReturn='legalizations'; ?>
<article class="act-card"><h3><a href="/?page=request&amp;id=<?=(int)$r['id']?>">TH-<?=str_pad((string)$r['id'],6,'0',STR_PAD_LEFT)?></a> · <?=e($r['category'])?></h3>
<p><?=e($r['name'])?> · <?=e($r['identification'])?></p>
<p><strong><?=!empty($r['legalized'])?'Legalizada':'Pendiente de legalizar'?></strong></p>
<?php if($r['legalization_at']): ?><p class="fine">Última marcación: <?=e($r['legalization_actor'])?> · <?=e($r['legalization_at'])?></p><?php endif; ?>
<?php require ROOT.'/views/legalization-form.php'; ?></article>
<?php endforeach; ?>
<nav aria-label="Páginas de legalizaciones"><?php foreach(['Anterior'=>$current-1,'Siguiente'=>$current+1] as $label=>$number): if($number<1||$number>$pages)continue; ?><a class="button secondary small" href="/?<?=e(http_build_query(['page'=>'legalizations','q'=>$search,'type'=>$type,'state'=>$state,'p'=>$number]))?>"><?=$label?></a> <?php endforeach; ?></nav>
</section>
