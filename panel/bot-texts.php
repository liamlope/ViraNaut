<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_once __DIR__ . '/inc/bot_data.php';
require_once __DIR__ . '/inc/bot_emojis.php';
require_auth();

$catalog = vira_panel_textbot_catalog();
$globalVars = vira_panel_textbot_global_vars();
$emojiLibrary = vira_custom_emoji_list($pdo);
$allIds = [];
foreach ($catalog as $items) {
    foreach (array_keys($items) as $id) {
        $allIds[] = $id;
    }
}

$texts = [];
try {
    $rows = db_fetchAll($pdo, 'SELECT id_text, text FROM textbot');
    foreach ($rows as $row) {
        $texts[$row['id_text']] = $row['text'];
    }
} catch (Exception $e) {
    error_log('bot-texts.php load: ' . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_post();
    $updated = 0;
    $emojiTrimmed = 0;
    $keyboardBtnIds = vira_panel_keyboard_button_text_ids();
    foreach ($allIds as $id) {
        if (!array_key_exists('text_' . $id, $_POST)) {
            continue;
        }
        $val = (string) ($_POST['text_' . $id] ?? '');
        if (in_array($id, $keyboardBtnIds, true) && function_exists('vira_limit_button_emoji_placeholders')) {
            $limited = vira_limit_button_emoji_placeholders($val, 1);
            if ($limited['trimmed']) {
                $emojiTrimmed++;
            }
            $val = $limited['text'];
        }
        $exists = db_count($pdo, 'SELECT COUNT(*) FROM textbot WHERE id_text = ?', [$id]);
        if ($exists > 0) {
            db_query($pdo, 'UPDATE textbot SET text = ? WHERE id_text = ?', [$val, $id]);
        } else {
            db_query($pdo, 'INSERT INTO textbot (id_text, text) VALUES (?, ?)', [$id, $val]);
        }
        $texts[$id] = $val;
        $updated++;
    }
    $msg = $updated > 0 ? 'متن‌ها ذخیره و اعمال شدند (' . $updated . ' مورد).' : 'تغییری ذخیره نشد.';
    if ($emojiTrimmed > 0) {
        $msg .= ' برای ' . $emojiTrimmed . ' دکمه، emoji اضافی حذف شد (تلگرام فقط یک Premium per دکمه).';
    }
    flash('success', $msg);
    header('Location: bot-texts.php');
    exit;
}

$keyboardBtnIds = vira_panel_keyboard_button_text_ids();
$totalCount = count($allIds);
$filledCount = 0;
foreach ($allIds as $id) {
    if (trim((string) ($texts[$id] ?? '')) !== '') {
        $filledCount++;
    }
}

$groupKeys = array_keys($catalog);
$pageTitle = 'متن‌های ربات';
$pageLede = '';
$showPageHead = false;
$activeNav = 'bot-texts';
$extraCss = ['css/bot-texts.css'];
$extraJs = ['js/bot-texts.js'];
include __DIR__ . '/inc/layout_head.php';
?>

<form method="post" id="botTextsForm" class="bt-page fade-up">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">

    <div class="bt-actionbar card">
        <div class="bt-actionbar-start">
            <h1 class="bt-title">متن‌های ربات</h1>
            <span class="bt-meta"><?= (int) $filledCount ?> / <?= (int) $totalCount ?> پر شده · <?= count($groupKeys) ?> دسته</span>
        </div>
        <div class="bt-actionbar-end">
            <div class="search-box bt-search">
                <?= icon('search', 14) ?>
                <input type="search" id="botTextsSearch" placeholder="جستجو…" autocomplete="off">
                <button type="button" class="search-clear" id="botTextsSearchClear" aria-label="پاک کردن">✕</button>
            </div>
            <a href="bot.php" class="btn btn-ghost btn-sm"><?= icon('bot', 14) ?> مرکز ربات</a>
            <a href="bot-emojis.php" class="btn btn-ghost btn-sm">کتابخانه ایموجی</a>
            <a href="keyboard.php" class="btn btn-ghost btn-sm"><?= icon('menu', 14) ?> چیدمان منو</a>
            <button type="button" class="btn btn-ghost btn-sm" id="btVarsOpen">متغیرها</button>
            <button type="submit" class="btn btn-primary"><?= icon('check', 14) ?> ذخیره و اعمال</button>
        </div>
    </div>

    <?php if ($emojiLibrary !== []): ?>
        <div class="card bt-emoji-bar">
            <div class="bt-emoji-bar-title">ایموجی — کلیک یا بکشید روی متن</div>
            <div class="bt-emoji-bar-hint">در <b>دکمه‌های منو</b> فقط یک <code>{emoji:slug}</code> · در <b>پیام‌ها</b> نامحدود</div>
            <div class="bt-emoji-chips" id="btEmojiChips">
                <?php foreach ($emojiLibrary as $emojiRow):
                    $chip = vira_emoji_panel_chip_meta($emojiRow);
                    if ($chip['placeholder'] === '') continue;
                    ?>
                    <div class="bt-emoji-chip" draggable="true" data-insert-emoji="<?= htmlspecialchars($chip['placeholder']) ?>"
                        title="<?= htmlspecialchars($chip['title']) ?>">
                        <span class="bt-emoji-chip-text">
                            <span class="bt-emoji-chip-name"><?= htmlspecialchars($chip['label']) ?></span>
                            <?php if ($chip['slug'] !== ''): ?>
                                <code class="bt-emoji-chip-slug" dir="ltr"><?= htmlspecialchars($chip['slug']) ?></code>
                            <?php endif; ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="bt-layout">
        <aside class="bt-cats card">
            <div class="bt-cats-title">دسته‌بندی</div>
            <?php foreach ($groupKeys as $gi => $groupName): ?>
                <a href="#bt-sec-<?= (int) $gi ?>" class="bt-cat-link<?= $gi === 0 ? ' active' : '' ?>"
                    data-section="bt-sec-<?= (int) $gi ?>">
                    <?= htmlspecialchars($groupName) ?>
                    <span><?= count($catalog[$groupName]) ?></span>
                </a>
            <?php endforeach; ?>
        </aside>

        <div class="bt-content">
            <?php foreach ($groupKeys as $gi => $groupName):
                $items = $catalog[$groupName];
                ?>
                <section class="bt-section card" id="bt-sec-<?= (int) $gi ?>" data-section="<?= (int) $gi ?>">
                    <header class="bt-section-head">
                        <h2><?= htmlspecialchars($groupName) ?></h2>
                        <span class="tag"><?= count($items) ?> متن</span>
                    </header>
                    <div class="bt-items">
                        <?php foreach ($items as $id => $meta):
                            $val = (string) ($texts[$id] ?? '');
                            $label = $meta['label'] ?? $id;
                            $hint = $meta['hint'] ?? '';
                            $rows = (int) ($meta['rows'] ?? 3);
                            $vars = $meta['vars'] ?? [];
                            $isKeyboardBtn = in_array($id, $keyboardBtnIds, true);
                            $searchBlob = mb_strtolower($label . ' ' . $id . ' ' . $val . ' ' . $hint . ' ' . $groupName, 'UTF-8');
                            ?>
                            <article class="bt-item" data-search="<?= htmlspecialchars($searchBlob) ?>">
                                <div class="bt-item-head">
                                    <div>
                                        <div class="bt-item-title"><?= htmlspecialchars($label) ?></div>
                                        <?php if ($hint !== ''): ?>
                                            <div class="bt-item-hint"><?= htmlspecialchars($hint) ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <code class="bt-item-id"><?= htmlspecialchars($id) ?></code>
                                </div>
                                <?php if ($emojiLibrary !== []): ?>
                                    <div class="bt-item-emojis">
                                        <span class="bt-item-emojis-label">ایموجی →</span>
                                        <?php foreach ($emojiLibrary as $emojiRow):
                                            $chip = vira_emoji_panel_chip_meta($emojiRow);
                                            if ($chip['placeholder'] === '') continue;
                                            ?>
                                            <span class="bt-emoji-chip bt-emoji-chip-sm" draggable="true"
                                                data-insert-emoji="<?= htmlspecialchars($chip['placeholder']) ?>"
                                                title="<?= htmlspecialchars($chip['title']) ?>">
                                                <span class="bt-emoji-chip-name"><?= htmlspecialchars($chip['label']) ?></span>
                                                <?php if ($chip['slug'] !== ''): ?>
                                                    <code class="bt-emoji-chip-slug" dir="ltr"><?= htmlspecialchars($chip['slug']) ?></code>
                                                <?php endif; ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                <textarea name="text_<?= htmlspecialchars($id) ?>" rows="<?= max(2, min(12, $rows)) ?>"
                                    class="input bt-area bt-area-drop<?= $isKeyboardBtn ? ' bt-area-kbd' : '' ?>"
                                    data-id="<?= htmlspecialchars($id) ?>"
                                    <?= $isKeyboardBtn ? 'data-keyboard-btn="1"' : '' ?>
                                    placeholder="<?= $isKeyboardBtn ? 'متن دکمه — حداکثر یک {emoji:slug} در ابتدا' : 'متن نمایشی در ربات — {emoji:نام} هر جا که بخواهید' ?>"><?= htmlspecialchars($val) ?></textarea>
                                <?php if ($isKeyboardBtn): ?>
                                    <p class="bt-kbd-emoji-warn field-hint" hidden>تلگرام فقط یک ایموجی Premium per دکمه — emoji اضافی حذف می‌شود.</p>
                                <?php endif; ?>
                                <?php if ($vars !== []): ?>
                                    <div class="bt-item-vars">
                                        <?php foreach ($vars as $v): ?>
                                            <button type="button" class="bt-var" data-insert-var="<?= htmlspecialchars($v) ?>"><?= htmlspecialchars($v) ?></button>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
            <p id="botTextsNoMatch" class="bt-empty" hidden>موردی با این جستجو پیدا نشد.</p>
        </div>
    </div>
</form>

<div class="bt-drawer-veil" id="btVarsVeil" hidden></div>
<aside class="bt-drawer" id="btVarsDrawer" aria-label="متغیرها" hidden>
    <header class="bt-drawer-head">
        <h3>متغیرهای قابل استفاده</h3>
        <button type="button" class="icon-btn" id="btVarsClose" aria-label="بستن">✕</button>
    </header>
    <ul class="bt-drawer-list">
        <?php foreach ($globalVars as [$var, $desc]): ?>
            <li>
                <button type="button" class="bt-drawer-var" data-insert-var="<?= htmlspecialchars($var) ?>">
                    <code><?= htmlspecialchars($var) ?></code>
                    <span><?= htmlspecialchars($desc) ?></span>
                </button>
            </li>
        <?php endforeach; ?>
    </ul>
</aside>

<?php include __DIR__ . '/inc/layout_foot.php';
