<?php authorizeAnalytics($u); ?>
<p class="page-intro">Totales de solicitudes aprobadas y de disfrute efectivo, con filtros independientes por tablero.</p>
<?php foreach(['approved','enjoyed'] as $metric):
$data=analyticsData(array_replace($_GET,['metric'=>$metric]));
$heading=$metric==='approved'?'Solicitudes aprobadas':'Solicitudes disfrutadas';
$description=$metric==='approved'?'Todas las solicitudes con aprobación final, incluidas las de disfrute futuro. Excluye canceladas, rechazadas y pendientes de aprobación. Una cancelación pendiente conserva su aprobación hasta que se autorice. La vigencia corresponde a las fechas autorizadas de disfrute.':'Solicitudes con aprobación final y tiempo efectivamente disfrutado. Incluye cancelaciones con disfrute parcial y excluye tiempo futuro y trámites pendientes.';
require ROOT.'/views/analytics-board.php';
endforeach; ?>
<section class="panel form-panel"><h2>Integración futura con Power BI</h2><p>Los dos tableros se consultan directamente en la aplicación. Power BI aún no está configurado. Cada exportación contiene datos del tablero seleccionado sin nombres, cédulas ni soportes.</p></section>
