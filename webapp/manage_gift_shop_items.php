<?php
require_once __DIR__ . '/session_bootstrap.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit;
}
$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['admin', 'Gift Shop Employee'], true)) {
    header('Location: dashboard.php');
    exit;
}
require_once __DIR__ . '/db.php';

$firstname = htmlspecialchars($_SESSION['firstname'] ?? 'Staff');
$dashboardBackHref = $role === 'Gift Shop Employee'
    ? 'dashboard.php#gift-shop'
    : 'dashboard.php#gift-shop-admin';

$items = $pdo->query("
    SELECT si.ShopItemID, si.ItemName, si.Price, si.StockQty, s.ShopName
    FROM shop_items si
    JOIN shops s ON s.ShopID = si.ShopID
    ORDER BY s.ShopName, si.ItemName
")->fetchAll(PDO::FETCH_ASSOC);

$flashOk = isset($_GET['deleted']);
$flashErr = isset($_GET['error']) ? (string) $_GET['error'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage gift shop items — Greenwood Zoo</title>
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
    </style>
</head>
<body>
    <div class="gs-shell">
        <div class="gs-inner">
            <header class="gs-header">
                <div>
                    <h1>Remove gift shop items</h1>
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
                    <div class="gs-alert ok" role="status">Item removed from the catalog.</div>
                <?php endif; ?>
                <?php if ($flashErr !== ''): ?>
                    <div class="gs-alert bad" role="alert"><?= htmlspecialchars($flashErr) ?></div>
                <?php endif; ?>

                <div class="manage-toolbar">
                    <a href="add-gift-shop-item.php">+ Add new item</a>
                </div>

                <?php if (count($items) === 0): ?>
                    <p class="empty-hint">No gift shop products yet. Use <strong>Add new item</strong> to create one.</p>
                <?php else: ?>
                    <div class="manage-table-wrap">
                        <table class="manage-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Shop</th>
                                    <th>Item</th>
                                    <th>Price</th>
                                    <th>Stock</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $row): ?>
                                <tr>
                                    <td><?= (int) $row['ShopItemID'] ?></td>
                                    <td><?= htmlspecialchars($row['ShopName']) ?></td>
                                    <td><?= htmlspecialchars($row['ItemName']) ?></td>
                                    <td>$<?= number_format((float) $row['Price'], 2) ?></td>
                                    <td><?= (int) $row['StockQty'] ?></td>
                                    <td>
                                        <form method="post" action="delete_gift_shop_item.php" class="js-del-gift-item" style="display:inline;margin:0">
                                            <input type="hidden" name="shop_item_id" value="<?= (int) $row['ShopItemID'] ?>">
                                            <button type="submit" class="btn-del">Remove</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="empty-hint" style="margin-top:14px;font-size:0.85rem">Items that have been sold at least once cannot be deleted (order history). Set stock to 0 to stop selling them.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script>
        document.querySelectorAll('.js-del-gift-item').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                if (!confirm('Remove this product from the gift shop catalog? This cannot be undone if the item has no sales yet.')) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>
