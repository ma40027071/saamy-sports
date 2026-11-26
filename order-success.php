<?php
require_once 'config/db.php';

$orderNumber = isset($_GET['order']) ? trim($_GET['order']) : '';
if ($orderNumber === '') {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM orders WHERE order_number = :order_number");
$stmt->execute([':order_number' => $orderNumber]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: index.php');
    exit;
}

$itemStmt = $pdo->prepare("
    SELECT oi.*, p.name
    FROM order_items oi
    JOIN products p ON p.id = oi.product_id
    WHERE oi.order_id = :order_id
");
$itemStmt->execute([':order_id' => (int)$order['id']]);
$items = $itemStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Placed | Samy Sports</title>
    <style>
        :root {
            --bg: #0f1115; --card: #181b22; --text: #f3f4f6;
            --muted: #9ca3af; --accent: #22c55e; --border: #262b36;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: system-ui, -apple-system, sans-serif; }
        body { background: var(--bg); color: var(--text); line-height: 1.5; }
        .container { max-width: 720px; margin: 32px auto; padding: 16px; }
        .box { background: var(--card); border: 1px solid var(--border); border-radius: 14px; padding: 24px; }
        .badge { display: inline-block; background: rgba(34,197,94,0.15); color: var(--accent); padding: 4px 10px; border-radius: 999px; font-weight: 700; font-size: 0.85rem; margin-bottom: 10px; }
        .btn { display: inline-block; margin-top: 18px; background: var(--accent); color: #07130b; text-decoration: none; padding: 10px 18px; border-radius: 8px; font-weight: 700; }
        .item { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid var(--border); font-size: 0.92rem; }
    </style>
</head>
<body>
<div class="container">
    <div class="box">
        <span class="badge">ORDER CONFIRMED</span>
        <h1>Thank you, <?= htmlspecialchars($order['customer_name']) ?>!</h1>
        <p style="color:var(--muted); margin-top:4px;">Order ID: <strong><?= htmlspecialchars($order['order_number']) ?></strong></p>

        <div style="margin-top:16px; font-size:0.92rem; color:var(--muted);">
            <div><strong>Phone:</strong> <?= htmlspecialchars($order['phone']) ?></div>
            <div><strong>Address:</strong> <?= htmlspecialchars($order['address']) ?>, <?= htmlspecialchars($order['city']) ?> - <?= htmlspecialchars($order['pincode']) ?></div>
            <div><strong>Payment Mode:</strong> <?= htmlspecialchars($order['payment_method']) ?></div>
        </div>

        <div style="margin-top:18px;">
            <?php foreach ($items as $it): ?>
                <div class="item">
                    <span><?= htmlspecialchars($it['name']) ?> (<?= htmlspecialchars($it['selected_size']) ?> × <?= (int)$it['quantity'] ?>)</span>
                    <span>₹<?= number_format((float)$it['unit_price'] * (int)$it['quantity'], 2) ?></span>
                </div>
            <?php endforeach; ?>
            <div style="display:flex; justify-content:space-between; padding-top:12px; font-size:1.15rem; font-weight:800; color:var(--accent);">
                <span>Total Paid / Payable</span>
                <span>₹<?= number_format((float)$order['total_amount'], 2) ?></span>
            </div>
        </div>

        <a href="index.php" class="btn">Back to Store</a>
    </div>
</div>
</body>
</html>
