<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_once __DIR__ . '/inc/wallet_defs.php';
require_auth();

$sqlPath = dirname(__DIR__) . '/migrations/viranaut_migrate.sql';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_post();
    $action = $_POST['action'] ?? '';

    if ($action === 'run_builtin') {
        try {
            $migrationFiles = [
                dirname(__DIR__) . '/migrations/viranaut_migrate.sql',
                dirname(__DIR__) . '/migrations/viranaut_migrate_2_1_0.sql',
                dirname(__DIR__) . '/migrations/viranaut_migrate_3_0_0.sql',
                dirname(__DIR__) . '/migrations/viranaut_migrate_3_1_0.sql',
                dirname(__DIR__) . '/migrations/viranaut_migrate_3_2_0_agent_panel.sql',
            ];
            foreach ($migrationFiles as $sqlPath) {
                if (!is_readable($sqlPath)) {
                    continue;
                }
                $sql = file_get_contents($sqlPath);
                foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
                    if ($stmt === '' || stripos($stmt, '--') === 0) {
                        continue;
                    }
                    try {
                        $pdo->exec($stmt);
                    } catch (PDOException $e) {
                        $msg = $e->getMessage();
                        if (strpos($msg, 'Duplicate') === false && strpos($msg, 'already exists') === false && strpos($msg, '1060') === false) {
                            throw $e;
                        }
                    }
                }
            }
            if (function_exists('vira_ensure_user_lang_column')) {
                vira_ensure_user_lang_column();
            }
            vira_seed_default_wallets($pdo);
            flash('success', 'مهاجرت داخلی ویرا (2.1 + 3.0 + 3.1 + 3.2) با موفقیت اجرا شد.');
        } catch (Throwable $e) {
            flash('error', 'خطا: ' . $e->getMessage());
        }
        header('Location: migration.php');
        exit;
    }

    if ($action === 'upload_sql' && !empty($_FILES['sql_file']['tmp_name'])) {
        try {
            $content = file_get_contents($_FILES['sql_file']['tmp_name']);
            if ($content === false || trim($content) === '') {
                throw new RuntimeException('فایل خالی است.');
            }
            foreach (array_filter(array_map('trim', explode(';', $content))) as $stmt) {
                if ($stmt === '' || stripos($stmt, '--') === 0) {
                    continue;
                }
                try {
                    $pdo->exec($stmt);
                } catch (PDOException $e) {
                    // skip non-critical duplicates
                }
            }
            if (is_readable($sqlPath)) {
                $builtin = file_get_contents($sqlPath);
                foreach (array_filter(array_map('trim', explode(';', $builtin))) as $stmt) {
                    if ($stmt === '' || stripos($stmt, '--') === 0) continue;
                    try { $pdo->exec($stmt); } catch (PDOException $e) { }
                }
            }
            vira_seed_default_wallets($pdo);
            flash('success', 'فایل SQL آپلود و مهاجرت ViraNaut اعمال شد.');
        } catch (Throwable $e) {
            flash('error', 'خطا: ' . $e->getMessage());
        }
        header('Location: migration.php');
        exit;
    }
}

$pageTitle = 'مهاجرت دیتابیس';
$pageLede = 'ارتقای دیتابیس نسخه‌های قدیمی به ویرا بدون از دست رفتن داده.';
$activeNav = 'migration';
include __DIR__ . '/inc/layout_head.php';
?>

<div class="fade-up">
    <div class="card">
        <div class="card-head"><div class="card-title">مهاجرت خودکار ViraNaut</div></div>
        <div class="card-body">
            <p class="field-hint">این عملیات کلیدهای جدید (ولت‌ها، دونیشن، نسخه) را به دیتابیس موجود اضافه می‌کند. جداول و داده‌های فعلی حذف نمی‌شوند.</p>
            <form method="post" style="margin-top:12px">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="action" value="run_builtin">
                <button type="submit" class="btn btn-primary">اجرای مهاجرت داخلی</button>
            </form>
        </div>
    </div>

    <div class="card" style="margin-top:16px">
        <div class="card-head"><div class="card-title">آپلود فایل .sql پشتیبان</div></div>
        <div class="card-body">
            <p class="field-hint">اگر بکاپ SQL از نسخهٔ قبلی دارید، ابتدا آن را import کنید؛ سپس مهاجرت ViraNaut اعمال می‌شود.</p>
            <form method="post" enctype="multipart/form-data" style="margin-top:12px;display:grid;gap:12px;max-width:420px">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="action" value="upload_sql">
                <input type="file" name="sql_file" accept=".sql,.txt" class="input" required>
                <button type="submit" class="btn btn-warn">آپلود و مهاجرت</button>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/inc/layout_foot.php';
