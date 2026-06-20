<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';
$user = agent_panel_require_auth($pdo);
$token = agent_panel_ensure_token($pdo, (string) $user['id']);
$domain = $domainhosts ?? '';
$pageTitle = 'API نماینده';
$activeNav = 'api';
require __DIR__ . '/inc/layout_head.php';
?>
<h1>مستندات API</h1>
<div class="agent-card">
<p>Header: <code>Authorization: Bearer YOUR_TOKEN</code></p>
<pre style="white-space:pre-wrap;background:#0f172a;padding:12px;border-radius:8px">POST https://<?= htmlspecialchars($domain) ?>/api/agent.php
Content-Type: application/json

{"action":"dashboard"}
{"action":"services","limit":50}
{"action":"service_detail","username":"user123"}
{"action":"renew","username":"user123"}
{"action":"add_volume","username":"user123","gb":10}
{"action":"revoke","username":"user123"}</pre>
<p>توکن شما: <code><?= htmlspecialchars($token) ?></code></p>
</div>
<?php require __DIR__ . '/inc/layout_foot.php'; ?>
