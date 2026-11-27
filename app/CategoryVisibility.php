<?php
declare(strict_types=1);

const HIDDEN_LICENSE_SLUGS=['deportes','estudios','no_remunerada'];
function visibleCategorySql(string $alias='c'):string {
    if($alias!=='' && !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D',$alias))throw new InvalidArgumentException('Alias no válido.');
    return ($alias!==''?$alias.'.':'')."slug NOT IN ('deportes','estudios','no_remunerada')";
}
function visibleRequestSql(string $alias='r'):string {
    if(!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D',$alias))throw new InvalidArgumentException('Alias no válido.');
    return $alias.'.category_id IN (SELECT id FROM categories WHERE '.visibleCategorySql('').')';
}
function ensureVisibleRequest(array $request):void {
    $category=row('SELECT slug FROM categories WHERE id=?',[$request['category_id']]);
    ensure($category!==null && !in_array($category['slug'],HIDDEN_LICENSE_SLUGS,true),'Este tipo de solicitud no está disponible.');
}
