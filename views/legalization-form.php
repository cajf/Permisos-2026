<form method="post" action="/?page=<?=e($legalizationReturn??'legalizations')?><?=($legalizationReturn??'')==='request'?'&amp;id='.(int)$r['id']:''?>">
<?=csrf()?>
<input type="hidden" name="action" value="legalization_save">
<input type="hidden" name="id" value="<?=(int)$r['id']?>">
<input type="hidden" name="version" value="<?=(int)($legalization['version']??0)?>">
<input type="hidden" name="return_to" value="<?=e($legalizationReturn??'legalizations')?>">
<label><input type="checkbox" name="legalized" value="1" <?=!empty($legalization['legalized'])?'checked':''?>> Legalizada</label>
<button class="button secondary small">Guardar</button>
</form>
