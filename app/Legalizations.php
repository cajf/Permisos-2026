<?php
declare(strict_types=1);

function legalizationNotice(string $slug):string {
    return match($slug) {
        'luto'=>'Su licencia por luto fue aprobada. No obstante, debe legalizarla allegando los soportes correspondientes a Talento Humano, dentro de los 30 días hábiles siguientes al suceso.',
        'matrimonio'=>'Su licencia por matrimonio fue aprobada. No obstante, debe acercarse a Talento Humano para legalizarla, allegando los soportes correspondientes.',
        default=>''
    };
}
function canManageLegalizations(array $actor):bool {
    return (hasRole($actor,'admin')||isSuperadmin($actor)) && can($actor,'request.legalize') && can($actor,'view.legalizations');
}
function saveLegalization(array $actor,int $id,bool $legalized,int $expectedVersion):void {
    $current=row('SELECT * FROM user_profiles WHERE id=? AND active=1',[$actor['id']]);
    ensure($current!==null && canManageLegalizations($current),'Solo Talento Humano autorizado puede registrar la legalización.');
    atomic(function()use($current,$id,$legalized,$expectedVersion){
        $request=row('SELECT r.*,c.slug FROM requests r JOIN categories c ON c.id=r.category_id WHERE r.id=?',[$id]);
        ensure($request && in_array($request['slug'],['luto','matrimonio'],true),'La legalización solo aplica a licencias por luto o matrimonio.');
        ensure(in_array($request['status'],['approved','cancel_pending'],true),'Solo se pueden legalizar licencias con aprobación final vigente.');
        $previous=row('SELECT * FROM request_legalizations WHERE request_id=?',[$id]);
        ensure((int)($previous['version']??0)===$expectedVersion,'La marcación cambió en otra sesión. Recargue antes de guardar.');
        if((bool)($previous['legalized']??false)===$legalized)return;
        run('INSERT INTO request_legalizations(request_id,legalized,updated_by,updated_at,version) VALUES(?,?,?,?,1) ON DUPLICATE KEY UPDATE legalized=?,updated_by=?,updated_at=?,version=version+1',[$id,(int)$legalized,$current['id'],now(),(int)$legalized,$current['id'],now()]);
        event((int)$current['id'],$legalized?'Licencia legalizada':'Legalización desmarcada','Marcación de recepción de soportes en Talento Humano. No modifica la aprobación de la licencia.',$id);
    });
}
