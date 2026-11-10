<?php authorizeAnalytics($u); ?>
<p class="page-intro">Selecciona el título de un tablero para abrirlo o cerrarlo. Cada tablero tiene sus propios filtros.</p>
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
<details class="panel analytics-panel" id="analytics-distribution" data-analytics-distribution<?=isset($_GET['distribution_year'])?' open':''?>>
    <summary>Solicitudes aprobadas por dependencia</summary>
    <div class="form-panel analytics-panel-content">
    <p>En cada tipo, las dependencias se ordenan de mayor a menor participación. Cada barra muestra la cantidad aprobada y su porcentaje del total de ese tipo en la vigencia seleccionada.</p>
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
    </div>
</details>
<?php
$medicalYear=$_GET['medical_year']??date('Y');
ensure(is_string($medicalYear),'Seleccione una vigencia válida.');
$medical=$distribution['year']===$medicalYear?$distribution:analyticsData(['metric'=>'approved','year'=>$medicalYear,'department'=>'all']);
?>
<details class="panel analytics-panel" id="analytics-medical" data-analytics-medical<?=isset($_GET['medical_year'])?' open':''?>>
    <summary>Permisos por cita médica</summary>
    <div class="form-panel analytics-panel-content">
        <p>Porcentaje de permisos marcados como cita médica sobre el total de Ordinario-Remunerado con aprobación final. La vigencia corresponde a las fechas autorizadas de disfrute.</p>
        <form method="get" action="/#analytics-medical" class="filter-form">
            <input type="hidden" name="page" value="analytics">
            <label>Vigencia <select name="medical_year">
                <?php foreach($medical['years'] as $year): ?>
                    <option value="<?=$year?>" <?=$medical['year']===(string)$year?'selected':''?>><?=$year?></option>
                <?php endforeach; ?>
                <option value="all" <?=$medical['year']==='all'?'selected':''?>>Todas las vigencias</option>
            </select></label>
            <button class="button secondary">Consultar</button>
        </form>
        <p role="status">Actualizado: <?=e($medical['generated_at'])?> · Bogotá.</p>
        <?php $total=$medical['medical']['total']; $appointments=$medical['medical']['appointments']; ?>
        <p class="analytics-medical-result"><?=$total?e($appointments.' de '.$total.' permisos · '.number_format(100*$appointments/$total,1,',','.').' %'):'No hay permisos Ordinario-Remunerado aprobados para esta selección.'?></p>
        <div class="analytics-medical-chart" <?=$total?'':'hidden'?>><div class="analytics-distribution-row">
            <span>Cita médica</span><progress max="100" value="<?=$total?100*$appointments/$total:0?>" class="analytics-progress-permiso" aria-label="Porcentaje de permisos por cita médica"></progress>
        </div></div>
        <script type="application/json" class="analytics-initial"><?=json_encode($medical,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE)?></script>
    </div>
</details>
<section class="panel form-panel"><h2>Integración futura con Power BI</h2><p>Los tableros se consultan directamente en la aplicación. Power BI aún no está configurado. Las exportaciones no incluyen nombres, cédulas ni soportes.</p></section>
