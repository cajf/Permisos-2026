<?php authorizeAnalytics($u); ?>
<p class="page-intro">Totales de solicitudes aprobadas y de disfrute efectivo, con filtros independientes por tablero.</p>
<?php foreach(['approved','enjoyed'] as $metric):
$data=analyticsData(array_replace($_GET,['metric'=>$metric]));
$heading=$metric==='approved'?'Solicitudes aprobadas':'Solicitudes disfrutadas';
$description=$metric==='approved'?'Todas las solicitudes con aprobación final, incluidas las de disfrute futuro. Excluye canceladas, rechazadas y pendientes de aprobación. Una cancelación pendiente conserva su aprobación hasta que se autorice. La vigencia corresponde a las fechas autorizadas de disfrute.':'Solicitudes con aprobación final y tiempo efectivamente disfrutado. Incluye cancelaciones con disfrute parcial y excluye tiempo futuro y trámites pendientes.';
require ROOT.'/views/analytics-board.php';
if($metric==='approved') $approvedData=$data;
endforeach; ?>
<?php
$distributionYear=$_GET['distribution_year']??date('Y');
ensure(is_string($distributionYear),'Seleccione una vigencia válida.');
$distribution=$approvedData['year']===$distributionYear && $approvedData['department']==='all'
    ?$approvedData:analyticsData(['metric'=>'approved','year'=>$distributionYear,'department'=>'all']);
?>
<section class="panel form-panel" id="analytics-distribution" data-analytics-distribution aria-labelledby="analytics-distribution-title">
    <h2 id="analytics-distribution-title">Solicitudes aprobadas por dependencia</h2>
    <p>Cada barra muestra qué porcentaje aporta la dependencia al total de solicitudes aprobadas de ese tipo en la vigencia seleccionada. Al lado se indica la cantidad.</p>
    <form method="get" action="/#analytics-distribution" class="filter-form">
        <input type="hidden" name="page" value="analytics">
        <label>Vigencia <select name="distribution_year">
            <?php foreach($distribution['years'] as $year): ?>
                <option value="<?=$year?>" <?=$distribution['year']===(string)$year?'selected':''?>><?=$year?></option>
            <?php endforeach; ?>
            <option value="all" <?=$distribution['year']==='all'?'selected':''?>>Todas las vigencias</option>
        </select></label>
        <button class="button secondary">Consultar</button>
    </form>
    <p role="status">Actualizado: <?=e($distribution['generated_at'])?> · Bogotá. Actualización automática cada 30 segundos.</p>
    <div class="analytics-distribution-chart"></div>
    <noscript><p>Activa JavaScript para ver la gráfica. Puedes consultar los conteos en el tablero de solicitudes aprobadas.</p></noscript>
    <script type="application/json" class="analytics-initial"><?=json_encode($distribution,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE)?></script>
</section>
<section class="panel form-panel"><h2>Integración futura con Power BI</h2><p>Los tableros se consultan directamente en la aplicación. Power BI aún no está configurado. Las exportaciones no incluyen nombres, cédulas ni soportes.</p></section>
