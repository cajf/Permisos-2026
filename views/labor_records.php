<?php
$filters=laborRecordFilters($_GET);
$searched=isset($_GET['from'],$_GET['to']);
$pageNumber=max(1,min(100001,(int)($_GET['records_page']??1)));
$query='/?'.http_build_query(['page'=>'labor_records']+$filters);
$download=can($u,'resource.labor_records');
$records=$searched?laborRecordQuery($filters,null,$pageNumber)->fetchAll():[];
$more=count($records)>25;
if($more) array_pop($records);
?>
<p class="page-intro">Consulta constancias con aprobación final por fecha de registro de la solicitud. Deja el nombre o la cédula en blanco para consultar a todo el personal.</p>
<section class="panel form-panel">
    <form method="get" class="filter-form">
        <input type="hidden" name="page" value="labor_records">
        <label>Fecha de registro desde<input type="date" name="from" required value="<?=e($filters['from'])?>"></label>
        <label>Fecha de registro hasta<input type="date" name="to" required value="<?=e($filters['to'])?>"></label>
        <label>Nombre o cédula<input name="person" maxlength="180" value="<?=e($filters['person'])?>"></label>
        <button class="button">Consultar</button>
    </form>
</section>
<?php if($searched): ?>
<section class="panel form-panel">
    <h2>Constancias disponibles</h2>
    <?php if(!$records): ?>
        <p>No hay constancias en esta página para los filtros seleccionados.</p>
    <?php else: ?>
        <?php if($download): ?>
        <form method="post" action="<?=e($query)?>">
            <?=csrf()?><input type="hidden" name="action" value="labor_records_zip">
            <button class="button secondary">Descargar todos los resultados en ZIP</button>
        </form>
        <?php endif; ?>
        <div class="table-wrap"><table>
            <thead><tr><th>Radicado</th><th>Funcionario</th><th>Tipo</th><th>Fecha de registro</th><?php if($download): ?><th>Constancia</th><?php endif; ?></tr></thead>
            <tbody><?php foreach($records as $record): ?><tr>
                <td><?=sprintf('TH-%06d',$record['id'])?></td>
                <td><?=e($record['person_name'])?><small><?=e($record['identification'])?></small></td>
                <td><?=e(familyLabel($record['family']).' · '.$record['category'])?></td>
                <td><?=e($record['created_at'])?></td>
                <?php if($download): ?><td><form method="post" action="<?=e($query)?>">
                    <?=csrf()?><input type="hidden" name="action" value="labor_records_pdf">
                    <input type="hidden" name="id" value="<?=(int)$record['id']?>">
                    <button class="button secondary">PDF con soportes</button>
                </form></td><?php endif; ?>
            </tr><?php endforeach; ?></tbody>
        </table></div>
    <?php endif; ?>
    <nav class="stack-buttons" aria-label="Páginas de constancias">
        <?php if($pageNumber>1): ?><a class="button secondary" href="<?=e($query.'&records_page='.($pageNumber-1))?>">Anterior</a><?php endif; ?>
        <?php if($more): ?><a class="button secondary" href="<?=e($query.'&records_page='.($pageNumber+1))?>">Siguiente</a><?php endif; ?>
    </nav>
</section>
<?php endif; ?>
