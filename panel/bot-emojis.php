<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_once __DIR__ . '/inc/bot_emojis.php';
require_auth();

$emojiLibrary = mirza_custom_emoji_list($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_post();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0 && mirza_custom_emoji_delete($id)) {
            flash('success', 'ایموجی حذف شد.');
        } else {
            flash('error', 'حذف انجام نشد.');
        }
        header('Location: bot-emojis.php');
        exit;
    }

    if ($action === 'rename') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim((string) ($_POST['emoji_name'] ?? ''));
        if ($id > 0 && $name !== '' && mirza_custom_emoji_rename($id, $name)) {
            flash('success', 'نام ایموجی به‌روز شد.');
        } else {
            flash('error', 'تغییر نام انجام نشد.');
        }
        header('Location: bot-emojis.php');
        exit;
    }
}

$pageTitle = 'کتابخانه ایموجی پرمیوم';
$pageLede = 'ایموجی‌های ذخیره‌شده با /savemoji — برای دکمه‌ها و متن‌های ربات';
$activeNav = 'bot-emojis';
include __DIR__ . '/inc/layout_head.php';
?>

<div class="fade-up" style="display:grid;gap:14px">
    <div class="card">
        <div class="card-head">
            <div>
                <div class="card-title">چطور ایموجی اضافه کنم؟</div>
                <div class="card-subtitle">فقط ادمین · نیاز به Telegram Premium روی مالک ربات</div>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <a href="keyboard.php" class="btn btn-ghost btn-sm">چیدمان منو</a>
                <a href="bot-texts.php" class="btn btn-ghost btn-sm">متن‌های ربات</a>
            </div>
        </div>
        <div class="card-body">
            <ol style="margin:0;padding-right:20px;line-height:1.8;color:var(--txt)">
                <li>در چت خصوسی ربات بنویسید: <code>/savemoji نام</code> (مثلاً <code>/savemoji کیف پول</code>)</li>
                <li>همان ایموجی پرمیوم (Custom Emoji) را در پیام بعد بفرستید — نه استیکر معمولی</li>
                <li>اینجا نام و شناسه ذخیره می‌شود؛ در «چیدمان منو» یا «متن‌های ربات» انتخابش کنید</li>
            </ol>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <div class="card-title">ایموجی‌های ذخیره‌شده (<?= count($emojiLibrary) ?>)</div>
        </div>
        <div class="card-body" style="padding:0">
            <?php if ($emojiLibrary === []): ?>
                <p class="field-hint" style="padding:18px">هنوز ایموجی ذخیره نشده. با <code>/savemoji</code> در ربات شروع کنید.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>نام (برای پنل)</th>
                                <th>نمایش</th>
                                <th>شناسه تلگرام</th>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($emojiLibrary as $row): ?>
                                <tr>
                                    <td>
                                        <form method="post" style="display:flex;gap:6px;align-items:center">
                                            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                                            <input type="hidden" name="action" value="rename">
                                            <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                            <input type="text" name="emoji_name" class="input input-sm"
                                                value="<?= htmlspecialchars($row['emoji_name']) ?>" required>
                                            <button type="submit" class="btn btn-ghost btn-sm">ذخیره نام</button>
                                        </form>
                                    </td>
                                    <td style="font-size:1.4rem"><?= htmlspecialchars($row['emoji_utf8'] ?? '—') ?></td>
                                    <td><code><?= htmlspecialchars($row['custom_emoji_id']) ?></code></td>
                                    <td>
                                        <form method="post" onsubmit="return confirm('این ایموجی از کتابخانه حذف شود؟');">
                                            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                            <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--no)">حذف</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/inc/layout_foot.php'; ?>
