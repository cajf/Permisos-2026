<?php
$ongoingFixture=json_decode(file_get_contents($root.'/.work/ongoing-http.json'),true);
$csrf=login('qa2');$requestId=(int)$ongoingFixture['id'];
$detail=http('page=request&id='.$requestId)['body'];
ok(str_contains($detail,'Esta aprobación quedará registrada como excepcional') && str_contains($detail,'name="approval_exception_reason"'),'Detalle advierte y solicita la justificación excepcional');
$noReason=http('page=request&id='.$requestId,['action'=>'review','csrf'=>$csrf,'id'=>$requestId,'decision'=>'approved','comment'=>'Revisión ordinaria'],true);
ok($noReason['code']===200 && str_contains($noReason['body'],'justificación de aprobación excepcional'),'POST individual rechaza aprobar en curso sin motivo específico');
$list=http('page=inbox')['body'];
ok(str_contains($list,'name="exception_reasons['.$requestId.']"'),'Cada solicitud en curso del lote tiene su propio campo de motivo');
$batch=['action'=>'approve_bulk','csrf'=>$csrf,'approval_ids[0]'=>$ongoingFixture['stage'],'exception_reasons['.$requestId.']'=>'Retraso por atención de una emergencia durante el disfrute'];
ok(http('page=inbox',$batch,true)['code']===302,'Formulario masivo transmite el motivo de la solicitud');
ok(str_contains(http('page=request&id='.$requestId)['body'],'Aprobación excepcional durante el disfrute. Motivo: Retraso'),'Detalle conserva el motivo y la marca excepcional de la aprobación masiva');
$csrf=login('qa3');
$result=http('page=request&id='.$requestId,['action'=>'review','csrf'=>$csrf,'id'=>$requestId,'decision'=>'approved','comment'=>'Verificado','approval_exception_reason'=>'Urgencia constatada por el responsable de la aprobación final'],true);
ok($result['code']===302 && str_contains(http('page=request&id='.$requestId)['body'],'badge badge-approved">Aprobada'),'Aprobación final individual admite el disfrute en curso con motivo propio');
