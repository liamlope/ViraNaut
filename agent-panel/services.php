<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/bootstrap.php';
$user = agent_panel_require_auth($pdo);
$filters = [
    'status' => $_GET['status'] ?? '',
    'location' => $_GET['location'] ?? '',
    'q' => trim($_GET['q'] ?? ''),
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
];
$rows = agent_services_query($pdo, (string) $user['id'], $filters, 100, 0);
$panels = agent_panel_list($pdo, $user);
$pageTitle = 'سرویس‌ها';
$activeNav = 'services';
require __DIR__ . '/inc/layout_head.php';
?>
<form method="get" class="agent-filter-bar card card-body">
<input type="search" name="q" class="input" placeholder="جستجو یوزرنیم" value="<?= htmlspecialchars($filters['q']) ?>">
<select name="status" class="input"><option value="">همه وضعیت</option>
<?php foreach (['active','expired','unpaid'] as $st): ?>
<option value="<?= $st ?>" <?= $filters['status'] === $st ? 'selected' : '' ?>><?= $st ?></option>
<?php endforeach; ?>
</select>
<select name="location" class="input"><option value="">همه پنل</option>
<?php foreach ($panels as $p): ?>
<option value="<?= htmlspecialchars($p['name_panel']) ?>" <?= $filters['location'] === $p['name_panel'] ? 'selected' : '' ?>><?= htmlspecialchars($p['name_panel']) ?></option>
<?php endforeach; ?>
</select>
<input type="date" name="date_from" class="input" value="<?= htmlspecialchars($filters['date_from']) ?>">
<input type="date" name="date_to" class="input" value="<?= htmlspecialchars($filters['date_to']) ?>">
<button class="btn btn-primary btn-sm" type="submit">فیلتر</button>
</form>

<div class="agent-bulk-bar" id="bulk-bar">
<span>عملیات گروهی:</span>
<button class="btn btn-sm btn-primary" type="button" id="bulk-renew">تمدید انتخاب‌شده‌ها</button>
</div>

<div class="tbl-wrap card" style="margin-top:12px">
<table class="tbl-lg">
<thead><tr><th><input type="checkbox" id="select-all-services"></th><th>کاربر</th><th>محصول</th><th>پنل</th><th>قیمت</th><th>وضعیت</th><th>تاریخ</th><th>عملیات</th></tr></thead>
<tbody>
<?php foreach ($rows as $r): ?>
<tr>
<td><input type="checkbox" class="svc-check" value="<?= htmlspecialchars($r['username']) ?>"></td>
<td><a href="service.php?u=<?= urlencode($r['username']) ?>"><?= htmlspecialchars($r['username']) ?></a></td>
<td><?= htmlspecialchars($r['name_product']) ?></td>
<td><?= htmlspecialchars($r['Location']) ?></td>
<td><?= number_format((int) $r['price_product']) ?></td>
<td><?= htmlspecialchars($r['Status']) ?></td>
<td><?= htmlspecialchars($r['time_sell']) ?></td>
<td class="agent-action-grid" style="grid-template-columns:repeat(3,auto);gap:4px">
<button class="btn btn-sm btn-ghost" data-agent-action="renew" data-username="<?= htmlspecialchars($r['username']) ?>">تمدید</button>
<button class="btn btn-sm btn-ghost" data-agent-action="add_volume" data-username="<?= htmlspecialchars($r['username']) ?>" data-gb="10">+۱۰GB</button>
<button class="btn btn-sm btn-ghost" data-agent-action="revoke" data-username="<?= htmlspecialchars($r['username']) ?>">لینک جدید</button>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php require __DIR__ . '/inc/layout_foot.php'; ?>
