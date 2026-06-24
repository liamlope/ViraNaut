<?php
/** @var array $emojiLibrary from vira_custom_emoji_list() */
/** @var bool $emojiPickerCompact */
/** @var string $emojiPickerId */
if (!isset($emojiLibrary) || $emojiLibrary === []) {
    return;
}
$emojiPickerCompact = !empty($emojiPickerCompact);
$emojiPickerId = (isset($emojiPickerId) && $emojiPickerId !== '') ? $emojiPickerId : 'productEmojiChips';
$barClass = $emojiPickerCompact ? 'bt-emoji-bar bt-emoji-bar-compact' : 'card bt-emoji-bar';
$chipClass = $emojiPickerCompact ? 'bt-emoji-chip bt-emoji-chip-sm' : 'bt-emoji-chip';
?>
<div class="<?= $barClass ?>" style="<?= $emojiPickerCompact ? '' : 'margin-bottom:14px' ?>">
    <div class="bt-emoji-bar-title">ایموجی Premium — کلیک یا بکشید روی فیلد نام</div>
    <?php if (!$emojiPickerCompact): ?>
    <div class="bt-emoji-bar-hint">در <b>نام محصول و دسته</b> حداکثر یک <code>{emoji:slug}</code> (مثل متن دکمه‌های منو)</div>
    <?php else: ?>
    <div class="bt-emoji-bar-hint">حداکثر یک <code>{emoji:slug}</code></div>
    <?php endif; ?>
    <div class="bt-emoji-chips" id="<?= htmlspecialchars($emojiPickerId) ?>">
        <?php foreach ($emojiLibrary as $emojiRow):
            $chip = vira_emoji_panel_chip_meta($emojiRow);
            ?>
            <div class="<?= $chipClass ?>" draggable="true" data-insert-emoji="<?= htmlspecialchars($chip['placeholder']) ?>"
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
