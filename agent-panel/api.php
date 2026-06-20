<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';
$user = agent_panel_require_auth($pdo);
$token = agent_panel_ensure_token($pdo, (string) $user['id']);
$domain = $domainhosts ?? 'localhost';
$logs = db_fetchAll($pdo, 'SELECT action, ip, created_at FROM agent_api_log WHERE id_user = ? ORDER BY created_at DESC LIMIT 30', [(string) $user['id']]);
$pageTitle = 'API';
$activeNav = 'api';
require __DIR__ . '/inc/layout_head.php';
?>
<div class="card"><div class="card-body agent-api-form">
<p><code>POST https://<?= htmlspecialchars($domain) ?>/api/agent.php</code></p>
<p>Authorization: <code>Bearer YOUR_TOKEN</code></p>
<label>Token</label>
<input id="api-test-token" class="input" value="<?= htmlspecialchars($token) ?>" dir="ltr">
<label>Action</label>
<select id="api-test-action" class="input">
<option value="dashboard">dashboard</option>
<option value="services">services</option>
<option value="service_detail">service_detail</option>
<option value="buy">buy</option>
<option value="renew">renew</option>
<option value="add_volume">add_volume</option>
<option value="revoke">revoke</option>
<option value="affiliates">affiliates</option>
<option value="transactions">transactions</option>
</select>
<label>Body (JSON)</label>
<textarea id="api-test-body" class="input">{"username":"testuser","limit":10}</textarea>
<button type="button" class="btn btn-primary" id="api-test-btn">تست</button>
<pre id="api-test-result" style="margin-top:12px;background:var(--sf);padding:12px;border-radius:8px;overflow:auto"></pre>
<h3>نمونه cURL</h3>
<pre>curl -X POST https://<?= htmlspecialchars($domain) ?>/api/agent.php \
  -H "Authorization: Bearer <?= htmlspecialchars($token) ?>" \
  -H "Content-Type: application/json" \
  -d '{"action":"dashboard"}'</pre>
</div></div>
<div class="card" style="margin-top:12px"><div class="card-head"><h3>لاگ API (۳۰ اخیر)</h3><span class="lede">Rate limit: 60/min</span></div>
<div class="tbl-wrap"><table class="tbl-lg"><thead><tr><th>action</th><th>ip</th><th>time</th></tr></thead><tbody>
<?php foreach ($logs as $l): ?><tr><td><?= htmlspecialchars($l['action']) ?></td><td><?= htmlspecialchars($l['ip']??'') ?></td><td><?= htmlspecialchars($l['created_at']) ?></td></tr><?php endforeach; ?>
</tbody></table></div></div>
<?php require __DIR__ . '/inc/layout_foot.php'; ?>
