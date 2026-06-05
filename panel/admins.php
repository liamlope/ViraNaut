<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_auth();
$pageTitle = 'مدیریت ادمین‌ها';
$activeNav = 'admins';
$extraJs = ['js/bot_tools.js', 'js/admins.js'];
include __DIR__ . '/inc/layout_head.php';
?>
<div class="fade-up" data-csrf="<?= htmlspecialchars(csrf_token()) ?>">
    <div class="card">
        <div class="card-body">
            <form id="adminForm" class="form-grid" style="grid-template-columns:repeat(auto-fill,minmax(140px,1fr))">
                <input type="text" name="id_admin" class="input" placeholder="آیدی عددی تلگرام" required>
                <input type="text" name="username" class="input" placeholder="نام کاربری پنل" required>
                <input type="password" name="password" class="input" placeholder="رمز" required>
                <select name="rule" class="select">
                    <option value="administrator">مدیر کل</option>
                    <option value="Seller">فروشنده</option>
                    <option value="support">پشتیبانی</option>
                </select>
                <button type="submit" class="btn btn-primary btn-sm">افزودن</button>
            </form>
        </div>
    </div>
    <div class="card" style="margin-top:16px">
        <div class="card-body" id="adminsList"></div>
    </div>
</div>
<?php include __DIR__ . '/inc/layout_foot.php';
