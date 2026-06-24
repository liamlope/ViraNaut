<?php
/** @var array $emojiLibrary from vira_custom_emoji_list() */
if (!isset($emojiLibrary) || $emojiLibrary === []) {
    return;
}
?>
<div class="card bt-emoji-bar" style="margin-bottom:14px">
    <div class="bt-emoji-bar-title">ایموجی Premium — کلیک یا بکشید روی فیلد نام</div>
    <div class="bt-emoji-bar-hint">در <b>نام محصول و دسته</b> حداکثر یک <code>{emoji:slug}</code> (مثل متن دکمه‌های منو)</div>
    <div class="bt-emoji-chips" id="productEmojiChips">
        <?php foreach ($emojiLibrary as $emojiRow):
            $chip = vira_emoji_panel_chip_meta($emojiRow);
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
