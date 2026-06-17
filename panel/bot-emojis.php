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

    if ($action === 'save_all') {
        $names = $_POST['names'] ?? [];
        $saved = 0;
        if (is_array($names)) {
            foreach ($names as $id => $name) {
                $id = (int) $id;
                $name = trim((string) $name);
                if ($id > 0 && $name !== '' && mirza_custom_emoji_rename($id, $name)) {
                    $saved++;
                }
            }
        }
        flash('success', $saved > 0 ? "نام {$saved} ایموجی ذخیره شد." : 'تغییری ذخیره نشد.');
        header('Location: bot-emojis.php');
        exit;
    }
}

$pageTitle = 'کتابخانه ایموجی پرمیوم';
$pageLede = 'نام + کد {emoji:…} — در هر نقطهٔ متن یا دکمه';
$activeNav = 'bot-emojis';
$extraCss = ['css/bot-emojis.css'];
$extraJs = ['js/bot-emojis.js'];
include __DIR__ . '/inc/layout_head.php';
?>

<div class="fade-up be-page" style="display:grid;gap:14px">
    <div class="card">
        <div class="card-head">
            <div>
                <div class="card-title">چطور استفاده کنم؟</div>
                <div class="card-subtitle">ایموجی را هر جای متن یا دکمه بگذارید — چپ، وسط، راست</div>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <a href="keyboard.php" class="btn btn-ghost btn-sm">چیدمان منو</a>
                <a href="bot-texts.php" class="btn btn-ghost btn-sm">متن‌های ربات</a>
            </div>
        </div>
        <div class="card-body">
            <ol class="be-steps">
                <li>در ربات: <code>/savemoji کیف پول</code> → ایموجی پرمیوم را بفرستید</li>
                <li>اینجا نام را ویرایش کنید (همان نامی که در کد استفاده می‌شود)</li>
                <li>در متن ربات یا متن دکمه بنویسید: <code>{emoji:کیف پول}</code> — دقیقاً همان نام</li>
                <li>برای آیکون چپ دکمه (Premium API): «چیدمان منو» → انتخاب ایموجی از لیست</li>
            </ol>
            <p class="field-hint" style="margin:12px 0 0">مثال متن: <code>سلام {emoji:کیف پول} به فروشگاه خوش آمدید</code></p>
        </div>
    </div>

    <form method="post" class="card">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
        <input type="hidden" name="action" value="save_all">
        <div class="card-head">
            <div class="card-title">ایموجی‌های ذخیره‌شده (<?= count($emojiLibrary) ?>)</div>
            <?php if ($emojiLibrary !== []): ?>
                <button type="submit" class="btn btn-primary btn-sm">ذخیره همهٔ نام‌ها</button>
            <?php endif; ?>
        </div>
        <div class="card-body" style="padding:0">
            <?php if ($emojiLibrary === []): ?>
                <p class="field-hint" style="padding:18px">هنوز ایموجی ذخیره نشده. با <code>/savemoji</code> در ربات شروع کنید.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table be-table">
                        <thead>
                            <tr>
                                <th>نام (قابل ویرایش)</th>
                                <th>پیش‌نمایش</th>
                                <th>کد در متن / دکمه</th>
                                <th>شناسه تلگرام</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($emojiLibrary as $row):
                                $placeholder = mirza_emoji_placeholder($row);
                                ?>
                                <tr>
                                    <td>
                                        <input type="text" name="names[<?= (int) $row['id'] ?>]" class="input input-sm be-name-input"
                                            value="<?= htmlspecialchars($row['emoji_name']) ?>" required
                                            aria-label="نام ایموجی">
                                    </td>
                                    <td class="be-preview"><?= htmlspecialchars($row['emoji_utf8'] ?? '—') ?></td>
                                    <td>
                                        <?php if ($placeholder !== ''): ?>
                                            <div class="be-code-wrap">
                                                <code class="be-code" dir="ltr"><?= htmlspecialchars($placeholder) ?></code>
                                                <button type="button" class="btn btn-ghost btn-sm be-copy" data-copy="<?= htmlspecialchars($placeholder) ?>">کپی</button>
                                            </div>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                    <td><code class="be-tg-id"><?= htmlspecialchars($row['custom_emoji_id']) ?></code></td>
                                    <td>
                                        <button type="button" class="btn btn-ghost btn-sm be-insert" data-insert="<?= htmlspecialchars($placeholder) ?>">درج در متن</button>
                                        <form method="post" style="display:inline" onsubmit="return confirm('حذف شود؟');">
                                            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                            <button type="submit" class="btn btn-ghost btn-sm be-del">حذف</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </form>
</div>

<?php include __DIR__ . '/inc/layout_foot.php'; ?>
