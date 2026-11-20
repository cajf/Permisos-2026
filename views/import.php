<?php requirePermission($u,'view.import'); ?>
<p class="page-intro">Importa hasta 1.000 funcionarios por archivo. Después de la carga, completa sus datos laborales desde Funcionarios.</p>
<section class="panel form-panel">
    <h2>Prepara el archivo</h2>
    <p>Diligencia la cédula, el nombre completo y el correo electrónico. La fecha de nacimiento es opcional y usa el formato AAAA-MM-DD. Escribe la cédula como texto para conservar los ceros iniciales.</p>
    <a class="button secondary small" href="/?page=template"><?=icon('download')?> Descargar plantilla CSV</a>
    <p class="fine">El archivo admite cédulas nuevas. Si alguna fila tiene errores, corrígela antes de confirmar la importación.</p>
    <form method="post" enctype="multipart/form-data">
        <?=csrf()?>
        <input type="hidden" name="action" value="import_preview">
        <label>Archivo XLSX o CSV de hasta 5 MB<input type="file" name="excel" accept=".xlsx,.csv" required></label>
        <button class="button">Revisar archivo</button>
    </form>
</section>
<?php if (!empty($_SESSION['person_import'])): ?>
<section class="panel">
    <div class="panel-header"><div><h2>Revisa y confirma</h2><p><?=count($_SESSION['person_import'])?> funcionarios · Vista previa de las primeras 50 filas</p></div></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Cédula</th><th>Nombre completo</th><th>Correo electrónico</th><th>Fecha de nacimiento</th></tr></thead>
            <tbody>
            <?php foreach (array_slice($_SESSION['person_import'],0,50) as $person): ?>
                <tr>
                    <td><?=e($person['identification'])?></td><td><?=e($person['name'])?></td>
                    <td><?=e($person['email'])?></td><td><?=e($person['birth_date']??'')?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <form method="post" class="form-panel">
        <?=csrf()?>
        <input type="hidden" name="action" value="import_commit">
        <button class="button">Confirmar importación</button>
    </form>
</section>
<?php endif; ?>
