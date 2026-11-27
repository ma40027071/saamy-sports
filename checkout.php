<?php
session_start();
require_once 'config/db.php';

if (empty($_SESSION['cart'])) {
    header('Location: cart.php');
    exit;
}

$cart = $_SESSION['cart'];
$subtotal = 0.0;
foreach ($cart as $item) {
    $subtotal += (float)$item['price'] * (int)$item['qty'];
}

$errors = [];
$old = [
    'customer_name'  => '',
    'phone'          => '',
    'address'        => '',
    'city'           => '',
    'pincode'        => '',
    'payment_method' => 'COD',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $old['customer_name']  = trim($_POST['customer_name'] ?? '');
    $old['phone']          = trim($_POST['phone'] ?? '');
    $old['address']        = trim($_POST['address'] ?? '');
    $old['city']           = trim($_POST['city'] ?? '');
    $old['pincode']        = trim($_POST['pincode'] ?? '');
    $old['payment_method'] = $_POST['payment_method'] ?? 'COD';

    if ($old['customer_name'] === '') {
        $errors[] = 'Customer name is required.';
    }
    if (!preg_match('/^[6-9][0-9]{9}$/', preg_replace('/\D/', '', $old['phone']))) {
        $errors[] = 'Please enter a valid phone number.';
    }
    if ($old['address'] === '' || $old['city'] === ''){
        $errors[] = 'Complete delivery address and city are required.';
    }elseif (!preg_match('/^[1-9][0-9]{5}$/', preg_replace('/\D/', '', $old['pincode']))) {
        $errors[] = 'Please enter a valid pincode.';
    }
    if (!in_array($old['payment_method'], ['COD', 'WHATSAPP'], true)) {
        $old['payment_method'] = 'COD';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $verifiedTotal = 0.0;
            $preparedItems = [];

            $prodStmt = $pdo->prepare("SELECT id, name, price, discount_price, stock_qty, is_active FROM products WHERE id = :id FOR UPDATE");

            foreach ($cart as $item) {
                $pid = (int)$item['product_id'];
                $qty = (int)$item['qty'];
                $size = (string)$item['size'];

                $prodStmt->execute([':id' => $pid]);
                $dbProd = $prodStmt->fetch();

                if (!$dbProd || (int)$dbProd['is_active'] !== 1) {
                    throw new RuntimeException("Product '{$item['name']}' is no longer available.");
                }
                if ((int)$dbProd['stock_qty'] < $qty) {
                    throw new RuntimeException("Insufficient stock for '{$dbProd['name']}' (Available: {$dbProd['stock_qty']}).");
                }

                $unitPrice = ($dbProd['discount_price'] !== null && (float)$dbProd['discount_price'] > 0)
                    ? (float)$dbProd['discount_price']
                    : (float)$dbProd['price'];

                $verifiedTotal += $unitPrice * $qty;
                $preparedItems[] = [
                    'product_id' => $pid,
                    'name'       => $dbProd['name'],
                    'size'       => $size,
                    'qty'        => $qty,
                    'unit_price' => $unitPrice,
                ];
            }

            $orderNumber = 'SS-' . date('Ymd') . '-' . random_int(1000, 9999);
            $paymentStatus = ($old['payment_method'] === 'COD') ? 'PENDING' : 'PENDING';

            $orderStmt = $pdo->prepare("
                INSERT INTO orders (
                    order_number, customer_name, phone, address, city, pincode,
                    total_amount, payment_method, payment_status, order_status
                ) VALUES (
                    :order_number, :customer_name, :phone, :address, :city, :pincode,
                    :total_amount, :payment_method, :payment_status, 'NEW'
                )
            ");

            $orderStmt->execute([
                ':order_number' => $orderNumber,
                ':customer_name'=> $old['customer_name'],
                ':phone'        =>preg_replace('/\D/', '', $old['phone']),
                ':address'      => $old['address'],
                ':city'         => $old['city'],
                ':pincode'      =>preg_replace('/\D/', '', $old['pincode']),
                ':total_amount' => $verifiedTotal,
                ':payment_method'=> $old['payment_method'],
                ':payment_status'=> $paymentStatus,
            ]);

            $orderId = (int)$pdo->lastInsertId();

            $itemStmt = $pdo->prepare("
                INSERT INTO order_items (order_id, product_id, selected_size, quantity, unit_price)
                VALUES (:order_id, :product_id, :selected_size, :quantity, :unit_price)
            ");

            $stockStmt = $pdo->prepare("
                UPDATE products SET stock_qty = stock_qty - :qty WHERE id = :id
            ");

            foreach ($preparedItems as $pItem) {
                $itemStmt->execute([
                    ':order_id'      => $orderId,
                    ':product_id'    => $pItem['product_id'],
                    ':selected_size' => $pItem['size'],
                    ':quantity'      => $pItem['qty'],
                    ':unit_price'    => $pItem['unit_price'],
                ]);

                $stockStmt->execute([
                    ':qty' => $pItem['qty'],
                    ':id'  => $pItem['product_id'],
                ]);
            }

            $pdo->commit();
            $_SESSION['cart'] = [];

            header('Location: order-success.php?order=' . urlencode($orderNumber));
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout | Samy Sports</title>
    <style>
        :root {
            --bg: #0f1115; --card: #181b22; --text: #f3f4f6;
            --muted: #9ca3af; --accent: #22c55e; --border: #262b36;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: system-ui, -apple-system, sans-serif; }
        body { background: var(--bg); color: var(--text); line-height: 1.5; }
        .container { max-width: 1020px; margin: 0 auto; padding: 16px; }
        header { background: var(--card); border-bottom: 1px solid var(--border); padding: 14px 0; margin-bottom: 24px; }
        .nav { display: flex; justify-content: space-between; align-items: center; }
        .brand { font-size: 1.3rem; font-weight: 800; color: var(--accent); text-decoration: none; }
        .btn-outline { background: transparent; color: var(--text); border: 1px solid var(--border); padding: 8px 12px; border-radius: 8px; text-decoration: none; }
        .btn { background: var(--accent); color: #07130b; border: none; padding: 12px 18px; border-radius: 8px; font-weight: 700; cursor: pointer; width: 100%; font-size: 1rem; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 22px; }
        .box { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 20px; }
        label { display: block; font-size: 0.9rem; font-weight: 600; margin-bottom: 5px; }
        input, textarea, select {
            width: 100%; padding: 10px; border-radius: 8px;
            border: 1px solid var(--border); background: var(--bg); color: var(--text); margin-bottom: 14px;
        }
        .alert { background: #3f1d1d; color: #fca5a5; padding: 12px 14px; border-radius: 8px; margin-bottom: 16px; }
        .summary-row { display: flex; justify-content: space-between; gap: 10px; padding: 8px 0; border-bottom: 1px solid var(--border); font-size: 0.92rem; }
        .total-row { display: flex; justify-content: space-between; padding-top: 14px; font-size: 1.25rem; font-weight: 800; color: var(--accent); }
    </style>
</head>
<body>
<header>
    <div class="container nav">
        <a href="index.php" class="brand">SAMY SPORTS</a>
        <a href="cart.php" class="btn-outline">← Back to Cart</a>
    </div>
</header>

<main class="container">
    <h1 style="margin-bottom:18px;">Checkout</h1>

    <?php if (!empty($errors)): ?>
        <div class="alert">
            <?php foreach ($errors as $err): ?>
                <div>• <?= htmlspecialchars($err) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="grid">
        <form method="POST" class="box">
            <h2 style="font-size:1.1rem; margin-bottom:14px;">Delivery Details</h2>

            <label for="customer_name">Full Name *</label>
            <input type="text" id="customer_name" name="customer_name" value="<?= htmlspecialchars($old['customer_name']) ?>" required>

            <label for="phone">Phone / WhatsApp Number *</label>
            <input type="tel" id="phone" name="phone" maxlength="10" pattern="[6-9][0-9]{9}" inputmode="numeric" placeholder="e.g. 9876543210" value="<?= htmlspecialchars($old['phone']) ?>" required>

            <label for="address">Street Address / Landmark *</label>
            <textarea id="address" name="address" rows="3" required><?= htmlspecialchars($old['address']) ?></textarea>

            <label for="city">City / Town *</label>
            <input type="text" id="city" name="city" value="<?= htmlspecialchars($old['city']) ?>" required>

            <label for="pincode">Pincode *</label>
            <input type="text" id="pincode" name="pincode" maxlength="6" pattern="[1-9][0-9]{5}" inputmode="numeric" placeholder="e.g. 123456" value="<?= htmlspecialchars($old['pincode']) ?>" required>

            <label for="payment_method">Order Mode</label>
            <select id="payment_method" name="payment_method">
                <option value="COD" <?= $old['payment_method'] === 'COD' ? 'selected' : '' ?>>Cash on Delivery / Store Pickup</option>
                <option value="WHATSAPP" <?= $old['payment_method'] === 'WHATSAPP' ? 'selected' : '' ?>>Confirm via WhatsApp</option>
            </select>

            <button type="submit" name="place_order" class="btn">Place Order</button>
        </form>

        <div class="box" style="align-self:start;">
            <h2 style="font-size:1.1rem; margin-bottom:14px;">Order Summary</h2>
            <?php foreach ($cart as $item): ?>
                <?php $line = (float)$item['price'] * (int)$item['qty']; ?>
                <div class="summary-row">
                    <div>
                        <strong><?= htmlspecialchars($item['name']) ?></strong><br>
                        <span style="color:var(--muted); font-size:0.82rem;">Size: <?= htmlspecialchars($item['size']) ?> × <?= (int)$item['qty'] ?></span>
                    </div>
                    <div>₹<?= number_format($line, 2) ?></div>
                </div>
            <?php endforeach; ?>

            <div class="total-row">
                <span>Total</span>
                <span>₹<?= number_format($subtotal, 2) ?></span>
            </div>
        </div>
    </div>
</main>
<script>
    const phoneInput = document.getElementById('phone');
    if (phoneInput) {
        phoneInput.addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '').slice(0, 10);
        });
    }

    // Optional: Add client-side validation or enhancements here

    const pincodeInput = document.getElementById('pincode');
    if (pincodeInput) {
        pincodeInput.addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '').slice(0, 6);
        });
    }
</script>
</body>
</html>