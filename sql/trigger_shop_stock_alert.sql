-- Gift shop restock alerts: LOW_STOCK fires when StockQty is 3 or below (but above 0).
-- OUT_OF_STOCK still fires when StockQty hits 0.
-- Apply on Azure / local: run as a user with TRIGGER privilege.

DROP TRIGGER IF EXISTS trg_shop_item_stock_alert_after_update;

DELIMITER $$

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
END$$

DELIMITER ;
