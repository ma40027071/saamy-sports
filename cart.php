<?php
session_start();

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_qty'], $_POST['key'], $_POST['qty'])) {
        $key = $_POST['key'];
        $qty = (int)$_POST['qty'];
        if (isset($_SESSION['cart'][$key])) {
            if ($qty <= 0) {
                unset($_SESSION['cart'][$key]);
            } else {
                $max = (int)$_SESSION['cart'][$key]['max_stock'];
                $_SESSION['cart'][$key]['qty'] = min($max, $qty);
            }
        }
    } elseif (isset($_POST['remove_item'], $_POST['key'])) {
        unset($_SESSION['cart'][$_POST['key']]);
    } elseif (isset($_POST['clear_cart'])) {
        $_SESSION['cart'] = [];
    }

    header('Location: cart.php');
    exit;
}

$cart = $_SESSION['cart'];
$subtotal = 0.0;
$waLines = ["*New Order - Samy Sports*"];

foreach ($cart as $item) {
    $lineTotal = (float)$item['price'] * (int)$item['qty'];
    $subtotal += $lineTotal;
    $waLines[] = "• {$item['name']} ({$item['size']}) x {$item['qty']} = ₹" . number_format($lineTotal, 2);
}

$waLines[] = "*Total: ₹" . number_format($subtotal, 2) . "*";
$shopWhatsApp = "919876543210"; // Replace with Samy Sports WhatsApp number
$waUrl = "https://wa.me/{$shopWhatsApp}?text=" . rawurlencode(implode("\n", $waLines));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Cart | Samy Sports</title>
    <style>
        :root {
            --bg: #0f1115; --card: #181b22; --text: #f3f4f6;
            --muted: #9ca3af; --accent: #22c55e; --border: #262b36;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: system-ui, -apple-system, sans-serif; }
        body { background: var(--bg); color: var(--text); line-height: 1.5; }
        .container { max-width: 960px; margin: 0 auto; padding: 16px; }
        header { background: var(--card); border-bottom: 1px solid var(--border); padding: 14px 0; margin-bottom: 24px; }
        .nav { display: flex; justify-content: space-between; align-items: center; }
        .brand { font-size: 1.3rem; font-weight: 800; color: var(--accent); text-decoration: none; }
        .btn { background: var(--accent); color: #07130b; border: none; padding: 10px 16px; border-radius: 8px; font-weight: 700; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-outline { background: transparent; color: var(--text); border: 1px solid var(--border); padding: 8px 12px; border-radius: 8px; cursor: pointer; text-decoration: none; }
        .btn-danger { background: #dc2626; color: #fff; border: none; padding: 7px 12px; border-radius: 8px; cursor: pointer; }
        .cart-item {
            display: flex; align-items: center; justify-content: space-between; gap: 14px; flex-wrap: wrap;
            background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 14px; margin-bottom: 12px;
        }
        .item-info { display: flex; align-items: center; gap: 14px; }
        .item-info img { width: 72px; height: 72px; object-fit: cover; border-radius: 8px; }
        .qty-input { width: 68px; padding: 7px; border-radius: 6px; border: 1px solid var(--border); background: var(--bg); color: var(--text); }
        .summary { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 20px; margin-top: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; }
        .empty { text-align: center; padding: 60px 16px; color: var(--muted); }
    </style>
</head>
<body>
<header>
    <div class="container nav">
        <a href="index.php" class="brand">SAMY SPORTS</a>
        <a href="index.php" class="btn-outline">← Continue Shopping</a>
    </div>
</header>

<main class="container">
    <h1 style="margin-bottom:18px;">Shopping Cart</h1>

    <?php if (empty($cart)): ?>
        <div class="empty">
            <p style="margin-bottom:14px;">Your cart is empty.</p>
            <a href="index.php" class="btn">Browse Sports Gear</a>
        </div>
    <?php else: ?>
        <?php foreach ($cart as $key => $item): ?>
            <div class="cart-item">
                <div class="item-info">
                    <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                    <div>
                        <h3 style="font-size:1rem;"><?= htmlspecialchars($item['name']) ?></h3>
                        <p style="font-size:0.85rem; color:var(--muted);">Size: <?= htmlspecialchars($item['size']) ?></p>
                        <p style="font-weight:700;">₹<?= number_format((float)$item['price'], 2) ?></p>
                    </div>
                </div>

                <div style="display:flex; align-items:center; gap:8px;">
                    <form method="POST" style="display:flex; align-items:center; gap:6px;">
                        <input type="hidden" name="key" value="<?= htmlspecialchars($key) ?>">
                        <input type="number" name="qty" class="qty-input" value="<?= (int)$item['qty'] ?>" min="1" max="<?= (int)$item['max_stock'] ?>">
                        <button type="submit" name="update_qty" class="btn-outline">Update</button>
                    </form>

                    <form method="POST">
                        <input type="hidden" name="key" value="<?= htmlspecialchars($key) ?>">
                        <button type="submit" name="remove_item" class="btn-danger">Remove</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="summary">
            <div>
                <p style="color:var(--muted); font-size:0.9rem;">Subtotal</p>
                <p style="font-size:1.5rem; font-weight:800;">₹<?= number_format($subtotal, 2) ?></p>
            </div>
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <form method="POST">
                    <button type="submit" name="clear_cart" class="btn-outline">Clear Cart</button>
                </form>
                <a href="<?= htmlspecialchars($waUrl) ?>" target="_blank" class="btn-outline">Order via WhatsApp</a>
                <a href="checkout.php" class="btn">Proceed to Checkout</a>
            </div>
        </div>
    <?php endif; ?>
</main>
</body>
</html>