<?php
$inboxFilter="FROM requests r JOIN categories c ON c.id=r.category_id JOIN users u ON u.id=r.user_id JOIN approvals a ON a.request_id=r.id AND a.revision=r.revision WHERE r.status='pending' AND a.status='waiting' AND a.assigned_user_id=? AND NOT EXISTS(SELECT 1 FROM approvals b WHERE b.request_id=a.request_id AND b.revision=a.revision AND b.step<a.step AND b.status='waiting')";
$totalPending=(int)row('SELECT COUNT(*) n '.$inboxFilter,[$u['id']])['n'];
$pageCount=max(1,(int)ceil($totalPending/100));$inboxPage=min($pageCount,max(1,(int)($_GET['inbox_page']??1)));
$pendingRows=rows('SELECT r.*,c.name category,c.family,u.name,a.id pending_approval_id,a.role pending_role '.$inboxFilter.' ORDER BY r.created_at,r.id LIMIT 100 OFFSET '.(($inboxPage-1)*100),[$u['id']]);
$canBulk=(hasRole($u,'manager')||hasRole($u,'subsecretary')) && count(array_filter($pendingRows,fn($r)=>in_array($r['pending_role'],['manager','subsecretary'],true)))>0;
$bulkResult=$_SESSION['bulk_result']??null;unset($_SESSION['bulk_result']);
?>
<p class="page-intro">Aquí aparecen las solicitudes cuya siguiente decisión te corresponde. Revisa sus datos y soportes antes de aprobar.</p>
<?php if($bulkResult): ?>
<div class="alert <?=count($bulkResult['failed'])?'warning':'success'?>" role="status">
    <strong><?=count($bulkResult['approved'])?> <?=count($bulkResult['approved'])===1?'aprobación registrada':'aprobaciones registradas'?>. <?=count($bulkResult['failed'])?> <?=count($bulkResult['failed'])===1?'solicitud sin aprobar':'solicitudes sin aprobar'?>.</strong>
    <?php if($bulkResult['failed']): ?><ul><?php foreach($bulkResult['failed'] as $failure): ?><li><?=e($failure['request_id']?'TH-'.str_pad((string)$failure['request_id'],6,'0',STR_PAD_LEFT):'Etapa seleccionada '.$failure['approval_id'])?>: <?=e($failure['message'])?></li><?php endforeach; ?></ul><?php endif; ?>
</div>
<?php endif; ?>
<section class="panel">
    <div class="panel-header"><div><h2>Solicitudes por revisar</h2><p><?=$totalPending?> pendiente(s) · Página <?=$inboxPage?> de <?=$pageCount?></p></div></div>
    <?php if($canBulk): ?>
    <form method="post" id="bulk-approval-form">
        <?=csrf()?><input type="hidden" name="action" value="approve_bulk"><input type="hidden" name="inbox_page" value="<?=$inboxPage?>">
        <div class="bulk-toolbar">
            <label class="check-label" id="bulk-select-all-label" hidden><input type="checkbox" id="bulk-select-all"> Seleccionar todas de esta página</label>
            <span class="fine" id="bulk-selected-count" role="status" aria-live="polite" hidden></span>
            <span class="fine">Hasta 100 solicitudes por acción.</span>
        </div>
        <?php requestTable($pendingRows,true); ?>
        <div class="form-panel bulk-actions">
            <label>Observación para las solicitudes seleccionadas (opcional)<textarea name="comment" rows="2" maxlength="3000" placeholder="Esta observación quedará registrada en cada solicitud aprobada."></textarea></label>
            <button class="button" type="submit" id="bulk-approve-button"><?=icon('check')?> Aprobar seleccionadas</button>
            <p class="fine">Cada solicitud conserva su ruta de aprobación. Las que no puedan aprobarse se informarán por separado y no impedirán procesar las demás.</p>
        </div>
    </form>
    <?php else: requestTable($pendingRows); endif; ?>
    <?php if($pageCount>1): ?><nav class="bulk-pagination" aria-label="Páginas de solicitudes pendientes"><?php if($inboxPage>1): ?><a class="button secondary small" href="/?page=inbox&amp;inbox_page=<?=$inboxPage-1?>">Anterior</a><?php endif; ?><span class="fine">Página <?=$inboxPage?> de <?=$pageCount?></span><?php if($inboxPage<$pageCount): ?><a class="button secondary small" href="/?page=inbox&amp;inbox_page=<?=$inboxPage+1?>">Siguiente</a><?php endif; ?></nav><?php endif; ?>
</section>
<?php if(hasRole($u,'subsecretary')): $cancels=[]; foreach(rows("SELECT r.*,c.name category,c.family,u.name FROM requests r JOIN categories c ON c.id=r.category_id JOIN users u ON u.id=r.user_id WHERE r.status='cancel_pending'") as $r) { try { if((int)$r['cancellation_assignee_id']===(int)$u['id']) $cancels[]=$r; } catch(DomainException){} } ?>
<section class="panel"><div class="panel-header"><h2>Cancelaciones por autorizar</h2></div><?php requestTable($cancels); ?></section>
<?php endif; ?>
