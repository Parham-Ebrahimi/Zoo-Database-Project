<?php
/**
 * One-time: replace trg_shop_item_stock_alert_after_update (low-stock threshold 3).
 * Run: php apply_shop_stock_trigger.php   OR open in browser once.
 */
require_once __DIR__ . '/db.php';

$sql = <<<'SQL'
CREATE TRIGGER trg_shop_item_stock_alert_after_update
AFTER UPDATE ON shop_items
FOR EACH ROW
BEGIN
    IF NEW.StockQty <= 0 THEN
        INSERT INTO restock_alerts (ShopItemID, AlertType, Message)
        VALUES (NEW.ShopItemID, 'OUT_OF_STOCK', CONCAT(NEW.ItemName, ' is out of stock. Please restock immediately.'))
        ON DUPLICATE KEY UPDATE
            Message = VALUES(Message),
            CreatedAt = NOW(),
            IsResolved = 0,
            ResolvedAt = NULL;
    ELSEIF NEW.StockQty <= 3 THEN
        INSERT INTO restock_alerts (ShopItemID, AlertType, Message)
        VALUES (NEW.ShopItemID, 'LOW_STOCK', CONCAT(NEW.ItemName, ' is running low (', NEW.StockQty, ' left). Restock before it runs out.'))
        ON DUPLICATE KEY UPDATE
            Message = VALUES(Message),
            CreatedAt = NOW(),
            IsResolved = 0,
            ResolvedAt = NULL;
    END IF;

    IF NEW.StockQty > 0 AND OLD.StockQty <= 0 THEN
        UPDATE restock_alerts
        SET IsResolved = 1, ResolvedAt = NOW()
        WHERE ShopItemID = NEW.ShopItemID
          AND AlertType = 'OUT_OF_STOCK'
          AND IsResolved = 0;
    END IF;

    IF NEW.StockQty > 3 AND OLD.StockQty <= 3 THEN
        UPDATE restock_alerts
        SET IsResolved = 1, ResolvedAt = NOW()
        WHERE ShopItemID = NEW.ShopItemID
          AND AlertType = 'LOW_STOCK'
          AND IsResolved = 0;
    END IF;
END
SQL;

try {
    $pdo->exec('DROP TRIGGER IF EXISTS trg_shop_item_stock_alert_after_update');
    $pdo->exec($sql);
    echo "OK: trg_shop_item_stock_alert_after_update recreated (LOW_STOCK when StockQty <= 3).\n";
} catch (Throwable $e) {
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Error: ' . $e->getMessage();
    exit(1);
}
