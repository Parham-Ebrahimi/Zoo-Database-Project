<?php
require_once __DIR__ . '/session_bootstrap.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit;
}
$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['admin', 'Restaurant Employee'], true)) {
    header('Location: dashboard.php');
    exit;
}
require_once __DIR__ . '/db.php';

$firstname = htmlspecialchars($_SESSION['firstname'] ?? 'Staff');
$dashboardBackHref = $role === 'Restaurant Employee'
    ? 'dashboard.php#restaurant-staff'
    : 'dashboard.php#restaurant-shop-admin';

$items = $pdo->query("
    SELECT fi.FoodID, fi.FoodName, fi.Price, fi.StockQty, fs.Name AS StallName
    FROM fooditem fi
    JOIN foodstall fs ON fs.StallID = fi.StallID
    ORDER BY fs.Name, fi.FoodName
")->fetchAll(PDO::FETCH_ASSOC);

$flashOk = isset($_GET['deleted']);
$flashErr = isset($_GET['error']) ? (string) $_GET['error'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage restaurant items — Greenwood Zoo</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .gs-shell { box-sizing: border-box; min-height: 100vh; padding: clamp(18px, 3vw, 36px); background: linear-gradient(165deg, rgba(187, 223, 158, 0.55) 0%, rgba(187, 223, 158, 0.92) 42%, var(--base-color) 100%); }
        .gs-inner { max-width: 960px; margin: 0 auto; width: 100%; box-sizing: border-box; }
        .gs-header { display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 22px; padding-bottom: 18px; border-bottom: 3px solid var(--accent-color); }
        .gs-header h1 { margin: 0 0 6px; font-size: clamp(1.35rem, 2.5vw, 1.75rem); font-weight: 800; color: var(--text-color); }
        .gs-meta { margin-top: 8px; font-size: 0.8rem; color: #888; }
        .gs-back { display: inline-flex; align-items: center; gap: 6px; padding: 10px 20px; border-radius: 999px; background: #fff; border: 2px solid var(--accent-color); color: var(--text-color); font-weight: 700; font-size: 0.88rem; text-decoration: none; }
        .gs-back:hover { background: var(--accent-color); color: #fff; text-decoration: none; }
        .gs-card { background: #fff; border-radius: 16px; padding: clamp(20px, 3vw, 28px); box-shadow: 0 8px 32px rgba(26, 61, 28, 0.1); border: 1px solid rgba(46, 90, 26, 0.12); text-align: left; }
        .gs-alert { padding: 14px 16px; border-radius: 12px; margin-bottom: 18px; font-size: 0.92rem; }
        .gs-alert.ok { background: linear-gradient(135deg, #e8f8e9 0%, #d4edc9 100%); border: 1px solid #a3d49a; color: #1a4a1a; }
        .gs-alert.bad { background: #fff5f5; border: 1px solid #f0b4b4; color: #7a1e1e; }
        .manage-toolbar { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin-bottom: 16px; }
        .manage-toolbar a { font-weight: 700; color: var(--text-color); }
        .manage-table-wrap { overflow-x: auto; border-radius: 12px; border: 1px solid rgba(46, 90, 26, 0.15); }
        .manage-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        .manage-table th, .manage-table td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #e8efe4; }
        .manage-table th { background: #f4faf1; font-weight: 700; color: #2d4a28; }
        .manage-table tr:last-child td { border-bottom: none; }
        .btn-del { padding: 6px 12px; border-radius: 8px; border: none; background: #c0392b; color: #fff; font: inherit; font-weight: 700; font-size: 0.82rem; cursor: pointer; }
        .btn-del:hover { background: #962d22; }
        .gs-header-actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .empty-hint { color: #666; margin: 0; }
        .ui-modal[hidden] { display: none !important; }
        .ui-modal { position: fixed; inset: 0; z-index: 1200; display: grid; place-items: center; padding: 16px; }
        .ui-modal__backdrop { position: absolute; inset: 0; background: rgba(15, 29, 11, 0.45); backdrop-filter: blur(2px); }
        .ui-modal__card { position: relative; width: min(460px, 100%); background: #fff; border: 1px solid rgba(46, 90, 26, 0.18); border-radius: 14px; box-shadow: 0 16px 45px rgba(26, 61, 28, 0.28); padding: 18px 18px 14px; text-align: left; }
        .ui-modal__title { margin: 0; font-size: 1.02rem; color: #163a1a; font-weight: 800; }
        .ui-modal__text { margin: 10px 0 0; color: #4a5d45; font-size: 0.9rem; line-height: 1.45; }
        .ui-modal__text strong { color: #1a3d1c; }
        .ui-modal__actions { margin-top: 16px; display: flex; justify-content: flex-end; gap: 10px; }
        .ui-btn { border: none; border-radius: 9px; padding: 8px 14px; font: inherit; font-size: 0.86rem; font-weight: 700; cursor: pointer; }
        .ui-btn--cancel { background: #eef3ec; color: #2f472f; }
        .ui-btn--confirm { background: #b9322a; color: #fff; }
        .ui-btn--confirm:hover { background: #932720; }
    </style>
</head>
<body>
    <div class="gs-shell">
        <div class="gs-inner">
            <header class="gs-header">
                <div>
                    <h1>Remove restaurant menu items</h1>
                    <p class="gs-meta">Signed in as <?= $firstname ?></p>
                </div>
                <div class="gs-header-actions">
                    <?php include __DIR__ . '/admin_header_cart_profile.inc.php'; ?>
                    <?php if ($role === 'admin'): ?>
                    <a href="logout.php" class="gs-back">Logout</a>
                    <?php endif; ?>
                    <a class="gs-back" href="<?= htmlspecialchars($dashboardBackHref) ?>">← Back to dashboard</a>
                </div>
            </header>

            <div class="gs-card">
                <?php if ($flashOk): ?>
                    <div class="gs-alert ok" role="status">Menu item removed.</div>
                <?php endif; ?>
                <?php if ($flashErr !== ''): ?>
                    <div class="gs-alert bad" role="alert"><?= htmlspecialchars($flashErr) ?></div>
                <?php endif; ?>

                <div class="manage-toolbar">
                    <a href="add-restaurant-item.php">+ Add new item</a>
                </div>

                <?php if (count($items) === 0): ?>
                    <p class="empty-hint">No restaurant menu items yet. Use <strong>Add new item</strong> to create one.</p>
                <?php else: ?>
                    <div class="manage-table-wrap">
                        <table class="manage-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Stall</th>
                                    <th>Item</th>
                                    <th>Price</th>
                                    <th>Stock</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $row): ?>
                                <tr>
                                    <td><?= (int) $row['FoodID'] ?></td>
                                    <td><?= htmlspecialchars($row['StallName']) ?></td>
                                    <td><?= htmlspecialchars($row['FoodName']) ?></td>
                                    <td>$<?= number_format((float) $row['Price'], 2) ?></td>
                                    <td><?= (int) $row['StockQty'] ?></td>
                                    <td>
                                        <form method="post" action="delete_restaurant_item.php" class="js-del-food-item" data-item-name="<?= htmlspecialchars($row['FoodName']) ?>" style="display:inline;margin:0">
                                            <input type="hidden" name="food_id" value="<?= (int) $row['FoodID'] ?>">
                                            <button type="submit" class="btn-del">Remove</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="empty-hint" style="margin-top:14px;font-size:0.85rem">Items that have been ordered at least once cannot be deleted. Set stock to 0 to stop selling them.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div id="delete-food-modal" class="ui-modal" hidden role="dialog" aria-modal="true" aria-labelledby="delete-food-modal-title">
        <div class="ui-modal__backdrop" data-close-delete-modal></div>
        <div class="ui-modal__card">
            <h2 id="delete-food-modal-title" class="ui-modal__title">Remove menu item?</h2>
            <p id="delete-food-modal-text" class="ui-modal__text"></p>
            <div class="ui-modal__actions">
                <button type="button" class="ui-btn ui-btn--cancel" data-close-delete-modal>Cancel</button>
                <button type="button" class="ui-btn ui-btn--confirm" id="delete-food-modal-confirm">Remove item</button>
            </div>
        </div>
    </div>
    <script>
        (function () {
            var modal = document.getElementById('delete-food-modal');
            var textEl = document.getElementById('delete-food-modal-text');
            var confirmBtn = document.getElementById('delete-food-modal-confirm');
            if (!modal || !textEl || !confirmBtn) return;
            var activeForm = null;

            function openModal(form) {
                activeForm = form;
                var name = form.getAttribute('data-item-name') || 'this item';
                textEl.innerHTML = 'You are about to remove <strong>' + name.replace(/</g, '&lt;') + '</strong> from the restaurant menu. This cannot be undone.';
                modal.hidden = false;
                document.body.style.overflow = 'hidden';
                confirmBtn.focus();
            }

            function closeModal() {
                modal.hidden = true;
                document.body.style.overflow = '';
                activeForm = null;
            }

            document.querySelectorAll('.js-del-food-item').forEach(function (form) {
                form.addEventListener('submit', function (e) {
                    e.preventDefault();
                    openModal(form);
                });
            });

            modal.querySelectorAll('[data-close-delete-modal]').forEach(function (el) {
                el.addEventListener('click', closeModal);
            });

            confirmBtn.addEventListener('click', function () {
                if (activeForm) activeForm.submit();
            });

            document.addEventListener('keydown', function (e) {
                if (!modal.hidden && e.key === 'Escape') closeModal();
            });
        })();
    </script>
</body>
</html>
