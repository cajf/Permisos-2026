<?php
$listing=searchActivityHistory($u,$_GET);
$listingPage='audit';
$listingFields=[
    'q_from'=>['label'=>'Fecha desde','type'=>'date'],
    'q_to'=>['label'=>'Fecha hasta','type'=>'date'],
    'q_responsible'=>['label'=>'Nombre o apellido del responsable'],
];
?>
<p class="page-intro">Consulta las actuaciones registradas y su responsable. Las fechas del filtro son inclusivas y se interpretan en hora de Bogotá.</p>
<section class="panel">
    <?php require ROOT.'/views/listing-filters.php'; ?>
    <div class="table-wrap"><table>
        <thead><tr><th>Fecha y hora</th><th>Responsable</th><th>Acción</th><th>Detalle</th><th>Solicitud</th></tr></thead>
        <tbody>
        <?php foreach($listing['items'] as $event): ?>
            <tr>
                <td><?=e($event['created_at'])?></td>
                <td><?=e($event['name'])?></td>
                <td><?=e($event['action'])?></td>
                <td><?=e($event['detail'])?></td>
                <td><?php if($event['request_id']): ?><a href="/?page=request&amp;id=<?=(int)$event['request_id']?>">TH-<?=(int)$event['request_id']?></a><?php endif; ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if(!$listing['items']): ?><tr><td colspan="5">No hay actuaciones en esta página para los filtros seleccionados.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
    <?php if($listing['more'] || $listing['page']>1) require ROOT.'/views/listing-pagination.php'; ?>
</section>
