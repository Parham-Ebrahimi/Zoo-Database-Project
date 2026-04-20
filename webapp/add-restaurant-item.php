<?php
require_once __DIR__ . '/session_bootstrap.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit;
}
if (!in_array(($_SESSION['role'] ?? ''), ['admin', 'Restaurant Employee'], true)) {
    header('Location: dashboard.php');
    exit;
}
require_once __DIR__ . '/db.php';
$role = $_SESSION['role'] ?? '';

$uploadDir = __DIR__ . '/images/restaurant/uploads';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$stalls = $pdo->query('SELECT StallID, Name FROM foodstall ORDER BY Name')->fetchAll(PDO::FETCH_ASSOC);

$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stallId = (int) ($_POST['stall_id'] ?? 0);
    $name = trim((string) ($_POST['food_name'] ?? ''));
    $price = (float) ($_POST['price'] ?? -1);
    $stock = (int) ($_POST['stock_qty'] ?? -1);
    $file = $_FILES['item_image'] ?? null;
    $hasFile = $file && isset($file['error']) && $file['error'] !== UPLOAD_ERR_NO_FILE;

    if ($stallId <= 0) {
        $error = 'Please select a stall.';
    } elseif ($name === '') {
        $error = 'Please enter a food item name.';
    } elseif ($price < 0) {
        $error = 'Price cannot be negative.';
    } elseif ($stock < 0) {
        $error = 'Stock cannot be negative.';
    } elseif ($hasFile && (int) $file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Image upload failed.';
    } else {
        $mimeToExt = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/gif'  => 'gif',
        ];
        $ext = null;
        if ($hasFile) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']);
            if (!isset($mimeToExt[$mime])) {
                $error = 'Image must be JPEG, PNG, WebP, or GIF.';
            } else {
                $ext = $mimeToExt[$mime];
            }
        }
    }

    if ($error === '') {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('
                INSERT INTO fooditem (StallID, FoodName, Price, StockQty)
                VALUES (?, ?, ?, ?)
            ');
            $stmt->execute([$stallId, $name, round($price, 2), $stock]);
            $newId = (int) $pdo->lastInsertId();

            if ($hasFile && $ext !== null) {
                foreach (glob($uploadDir . DIRECTORY_SEPARATOR . 'item-' . $newId . '.*') ?: [] as $old) {
                    if (is_file($old)) {
                        @unlink($old);
                    }
                }
                $dest = $uploadDir . DIRECTORY_SEPARATOR . 'item-' . $newId . '.' . $ext;
                if (!move_uploaded_file($file['tmp_name'], $dest)) {
                    throw new RuntimeException('Could not save the image file.');
                }
            }

            $pdo->commit();
            $success = 'Restaurant item #' . $newId . ' was added'
                . ($hasFile ? ' with your photo' : ' (using a default image until you add a photo later)')
                . '.';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Could not add item: ' . $e->getMessage();
        }
    }
}
$firstname = htmlspecialchars($_SESSION['firstname'] ?? 'Admin');
$dashboardBackHref = $role === 'Restaurant Employee'
    ? 'dashboard.php#restaurant-staff'
    : 'dashboard.php#restaurant-shop-admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add restaurant item — Greenwood Zoo</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .gs-shell {
            box-sizing: border-box;
            min-height: 100vh;
            padding: clamp(18px, 3vw, 36px);
            background: linear-gradient(165deg, rgba(187, 223, 158, 0.55) 0%, rgba(187, 223, 158, 0.92) 42%, var(--base-color) 100%);
        }
        .gs-inner {
            max-width: 840px;
            margin: 0 auto;
        }
        .gs-header {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 22px;
            padding-bottom: 18px;
            border-bottom: 3px solid var(--accent-color);
        }
        .gs-header h1 {
            margin: 0 0 6px;
            font-size: clamp(1.35rem, 2.5vw, 1.75rem);
            font-weight: 800;
            color: var(--text-color);
            letter-spacing: -0.02em;
        }
        .gs-lead {
            margin: 0;
            font-size: 0.92rem;
            color: #4a5c42;
            line-height: 1.5;
            max-width: 34em;
        }
        .gs-meta {
            margin-top: 18px;
            font-size: 0.8rem;
            color: #888;
        }
        .gs-back {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 10px 20px;
            border-radius: 999px;
            background: #fff;
            border: 2px solid var(--accent-color);
            color: var(--text-color);
            font-weight: 700;
            font-size: 0.88rem;
            text-decoration: none;
            box-shadow: 0 2px 8px rgba(46, 90, 26, 0.1);
            transition: background 0.15s, transform 0.15s;
        }
        .gs-back:hover {
            background: var(--accent-color);
            color: #fff;
            text-decoration: none;
        }
        .gs-card {
            background: #fff;
            border-radius: 16px;
            padding: clamp(20px, 3vw, 28px);
            box-shadow: 0 8px 32px rgba(26, 61, 28, 0.1);
            border: 1px solid rgba(46, 90, 26, 0.12);
        }
        .gs-alert {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 14px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 0.92rem;
            line-height: 1.45;
        }
        .gs-alert .ico { font-size: 1.25rem; line-height: 1; flex-shrink: 0; }
        .gs-alert.ok {
            background: linear-gradient(135deg, #e8f8e9 0%, #d4edc9 100%);
            border: 1px solid #a3d49a;
            color: #1a4a1a;
        }
        .gs-alert.bad {
            background: #fff5f5;
            border: 1px solid #f0b4b4;
            color: #7a1e1e;
        }
        .gs-section-title {
            margin: 0 0 14px;
            font-size: 0.72rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #5a6b52;
        }
        .gs-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px 18px;
        }
        @media (max-width: 600px) {
            .gs-grid { grid-template-columns: 1fr; }
        }
        .gs-field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .gs-field--full {
            grid-column: 1 / -1;
        }
        .gs-field input,
        .gs-field select {
            width: 100%;
            box-sizing: border-box;
            padding: 11px 14px;
            border: 2px solid #d5e5cd;
            border-radius: 10px;
            font: inherit;
            font-size: 0.95rem;
            background: #fbfcfa;
            transition: border-color 0.15s, box-shadow 0.15s, background 0.15s;
        }
        .gs-field input:focus,
        .gs-field select:focus {
            outline: none;
            border-color: var(--accent-color);
            background: #fff;
            box-shadow: 0 0 0 3px rgba(76, 145, 65, 0.2);
        }
        .gs-upload-block {
            margin-top: 22px;
            padding-top: 22px;
            border-top: 1px solid #e8efe4;
        }
        .gs-file-wrap {
            position: relative;
            width: 100%;
        }
        .gs-file-wrap input[type="file"] {
            position: absolute;
            width: 0.1px;
            height: 0.1px;
            opacity: 0;
            overflow: hidden;
            z-index: -1;
        }
        .gs-file-label {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-sizing: border-box;
            width: 100%;
            min-height: 140px;
            padding: 20px 16px;
            border: 2px dashed #b8d4a8;
            border-radius: 14px;
            background: linear-gradient(180deg, #f7fbf4 0%, #f0f6ec 100%);
            cursor: pointer;
            text-align: center;
            transition: border-color 0.15s, background 0.15s;
        }
        .gs-file-label:hover,
        .gs-file-wrap input[type="file"]:focus + .gs-file-label {
            border-color: var(--accent-color);
            background: #eef6ea;
        }
        .gs-file-label .big {
            font-size: 2rem;
            line-height: 1;
        }
        .gs-file-label strong {
            font-size: 1rem;
            color: var(--text-color);
        }
        .gs-actions {
            margin-top: 26px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 12px;
        }
        .gs-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 26px;
            border-radius: 999px;
            font: inherit;
            font-weight: 700;
            font-size: 0.92rem;
            cursor: pointer;
            border: none;
            text-decoration: none;
            transition: background 0.15s, transform 0.1s;
        }
        .gs-btn:active { transform: scale(0.98); }
        .gs-btn--primary {
            background: var(--accent-color);
            color: #fff;
            box-shadow: 0 4px 14px rgba(46, 90, 26, 0.35);
        }
        .gs-btn--primary:hover {
            background: var(--text-color);
            color: #fff;
        }
        .gs-btn--ghost {
            background: #fff;
            color: var(--text-color);
            border: 2px solid #c5dcb8;
        }
        .gs-btn--ghost:hover {
            background: #f4f9f0;
            border-color: var(--accent-color);
        }
        .gs-empty {
            text-align: center;
            padding: 20px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="gs-shell">
        <div class="gs-inner">
            <header class="gs-header">
                <div>
                    <h1>Add restaurant item</h1>
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
                <?php if ($success !== ''): ?>
                    <div class="gs-alert ok" role="status">
                        <span class="ico" aria-hidden="true">✓</span>
                        <div><?= htmlspecialchars($success) ?></div>
                    </div>
                <?php endif; ?>
                <?php if ($error !== ''): ?>
                    <div class="gs-alert bad" role="alert">
                        <span class="ico" aria-hidden="true">!</span>
                        <div><?= htmlspecialchars($error) ?></div>
                    </div>
                <?php endif; ?>

                <?php if (count($stalls) === 0): ?>
                    <div class="gs-empty">
                        <p><strong>No stalls found.</strong> Add at least one row to the <code>foodstall</code> table before creating items.</p>
                    </div>
                <?php else: ?>
                    <form method="post" enctype="multipart/form-data" action="" id="restaurant-add-form">
                        <div>
                            <h2 class="gs-section-title">Menu details</h2>
                            <div class="gs-grid">
                                <div class="gs-field gs-field--full">
                                    <label for="stall_id">Stall</label>
                                    <select id="stall_id" name="stall_id" required>
                                        <option value="">Select stall</option>
                                        <?php
                                        $postStall = (string) ($_POST['stall_id'] ?? '');
                                        foreach ($stalls as $stall):
                                            $sid = (int) $stall['StallID'];
                                        ?>
                                            <option value="<?= $sid ?>" <?= $postStall !== '' && $postStall === (string) $sid ? 'selected' : '' ?>><?= htmlspecialchars($stall['Name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="gs-field gs-field--full">
                                    <label for="food_name">Item name</label>
                                    <input id="food_name" name="food_name" type="text" required maxlength="200"
                                           value="<?= htmlspecialchars($_POST['food_name'] ?? '') ?>"
                                           placeholder="e.g. Savanna Burger"
                                           autocomplete="off">
                                </div>
                                <div class="gs-field">
                                    <label for="price">Price (USD)</label>
                                    <input id="price" name="price" type="number" step="0.01" min="0" required
                                           value="<?= htmlspecialchars($_POST['price'] ?? '') ?>" placeholder="0.00">
                                </div>
                                <div class="gs-field">
                                    <label for="stock_qty">Stock quantity</label>
                                    <input id="stock_qty" name="stock_qty" type="number" min="0" required
                                           value="<?= htmlspecialchars($_POST['stock_qty'] ?? '0') ?>">
                                </div>
                            </div>
                        </div>

                        <div class="gs-upload-block">
                            <h2 class="gs-section-title">Item photo</h2>
                            <div class="gs-file-wrap">
                                <input id="item_image" name="item_image" type="file"
                                       accept="image/jpeg,image/png,image/webp,image/gif">
                                <label class="gs-file-label" for="item_image">
                                    <span class="big" aria-hidden="true">🖼️</span>
                                    <strong>Choose image file</strong>
                                </label>
                            </div>
                        </div>

                        <div class="gs-actions">
                            <button type="submit" class="gs-btn gs-btn--primary">Add to menu</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
