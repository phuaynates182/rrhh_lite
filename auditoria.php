<?php
require __DIR__ . '/includes/bootstrap.php';
require_admin();
$lista = rows('SELECT * FROM auditoria ORDER BY id DESC LIMIT 300');
page_head('Auditoría', 'auditoria');
?>
<div class="card"><div class="toolbar"><h2>Últimos 300 eventos</h2><input type="search" placeholder="Filtrar…" data-filter="#tab"></div>
<div class="tablewrap"><table id="tab"><thead><tr><th>Fecha</th><th>Usuario</th><th>Acción</th><th>Entidad</th><th>Detalle</th><th>IP</th></tr></thead><tbody>
<?php foreach ($lista as $a): ?><tr><td><?= date('d/m/Y H:i', strtotime($a['fecha'])) ?></td><td><?= e($a['usuario_nombre']) ?></td><td><span class="badge info"><?= e($a['accion']) ?></span></td>
<td><?= e($a['entidad']) ?> <?= e($a['entidad_id']) ?></td><td><?= e($a['detalle']) ?></td><td class="muted"><?= e($a['ip']) ?></td></tr><?php endforeach; ?>
</tbody></table></div></div>
<?php page_foot();
