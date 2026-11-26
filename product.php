<?php
session_start();
require_once 'config/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM products WHERE id = :id AND is_active = 1");
$stmt->execute([':id' => $id]);
$product = $stmt->fetch();

if (!$product) {
    header('Location: index.php');
    exit;
}

$sizes = array_filter(array_map('trim', explode(',', $product['sizes'] ?? 'Free Size')));
if (empty($sizes)) {
    $sizes = ['Free Size'];
}

$finalPrice = ($product['discount_price'] !== null && $product['discount_price'] > 0)
    ? (float)$product['discount_price']
    : (float)$product['price'];

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $selectedSize = isset($_POST['size']) ? trim($_POST['size']) : $sizes[0];
    if (!in_array($selectedSize, $sizes, true)) {
        $selectedSize = $sizes[0];
    }

    $qty = isset($_POST['qty']) ? max(1, (int)$_POST['qty']) : 1;
    $maxStock = (int)$product['stock_qty'];

    if ($maxStock <= 0) {
        $message = 'Sorry, this item is currently out of stock.';
    } else {
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }

        $cartKey = $product['id'] . '_' . $selectedSize;
        $existingQty = isset($_SESSION['cart'][$cartKey]) ? (int)$_SESSION['cart'][$cartKey]['qty'] : 0;
        $newQty = min($maxStock, $existingQty + $qty);

        $_SESSION['cart'][$cartKey] = [
            'product_id' => (int)$product['id'],
            'name'       => $product['name'],
            'size'       => $selectedSize,
            'price'      => $finalPrice,
            'image_url'  => $product['image_url'],
            'qty'        => $newQty,
            'max_stock'  => $maxStock,
        ];

        header('Location: cart.php');
        exit;
    }
}

$cartCount = 0;
if (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cartCount += (int)$item['qty'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($product['name']) ?> | Samy Sports</title>
    <style>
        :root {
            --bg: #0f1115; --card: #181b22; --text: #f3f4f6;
            --muted: #9ca3af; --accent: #22c55e; --border: #262b36;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: system-ui, -apple-system, sans-serif; }
        body { background: var(--bg); color: var(--text); line-height: 1.5; }
        .container { max-width: 1000px; margin: 0 auto; padding: 16px; }
        header { background: var(--card); border-bottom: 1px solid var(--border); padding: 14px 0; margin-bottom: 24px; }
        .nav { display: flex; justify-content: space-between; align-items: center; }
        .brand { font-size: 1.3rem; font-weight: 800; color: var(--accent); text-decoration: none; }
        .btn { background: var(--accent); color: #07130b; border: none; padding: 11px 18px; border-radius: 8px; font-weight: 700; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-outline { background: transparent; color: var(--text); border: 1px solid var(--border); padding: 8px 14px; border-radius: 8px; text-decoration: none; }
        .product-wrap { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 28px; background: var(--card); border: 1px solid var(--border); border-radius: 14px; padding: 20px; }
        .product-wrap img { width: 100%; max-height: 380px; object-fit: cover; border-radius: 10px; }
        .cat { color: var(--accent); font-size: 0.8rem; font-weight: 700; text-transform: uppercase; }
        h1 { font-size: 1.6rem; margin: 6px 0 10px; }
        .price { font-size: 1.4rem; font-weight: 800; margin-bottom: 12px; }
        .old { font-size: 1rem; color: var(--muted); text-decoration: line-through; margin-left: 8px; }
        .desc { color: var(--muted); margin-bottom: 18px; }
        label { display: block; font-size: 0.9rem; font-weight: 600; margin-bottom: 6px; }
        select, input[type="number"] {
            width: 100%; max-width: 220px; padding: 10px; border-radius: 8px;
            border: 1px solid var(--border); background: var(--bg); color: var(--text); margin-bottom: 14px;
        }
        .alert { background: #3f1d1d; color: #fca5a5; padding: 10px 14px; border-radius: 8px; margin-bottom: 14px; }
    </style>
</head>
<body>
<header>
    <div class="container nav">
        <a href="index.php" class="brand">SAMY SPORTS</a>
        <div style="display:flex; gap:10px;">
            <a href="index.php" class="btn-outline">← Back</a>
            <a href="cart.php" class="btn-outline">Cart (<?= $cartCount ?>)</a>
        </div>
    </div>
</header>

<main class="container">
    <?php if ($message !== ''): ?>
        <div class="alert"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="product-wrap">
        <img src="<?= htmlspecialchars($product['image_url']) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
        <div>
            <span class="cat"><?= htmlspecialchars($product['category']) ?></span>
            <h1><?= htmlspecialchars($product['name']) ?></h1>
            <div class="price">
                ₹<?= number_format($finalPrice, 2) ?>
                <?php if ($product['discount_price'] !== null && $product['discount_price'] > 0): ?>
                    <span class="old">₹<?= number_format((float)$product['price'], 2) ?></span>
                <?php endif; ?>
            </div>
            <p class="desc"><?= nl2br(htmlspecialchars($product['description'])) ?></p>

            <?php if ((int)$product['stock_qty'] > 0): ?>
                <form method="POST">
                    <label for="size">Select Size</label>
                    <select name="size" id="size" required>
                        <?php foreach ($sizes as $s): ?>
                            <option value="<?= htmlspecialchars($s) ?>"><?= htmlspecialchars($s) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label for="qty">Quantity (Max: <?= (int)$product['stock_qty'] ?>)</label>
                    <input type="number" name="qty" id="qty" value="1" min="1" max="<?= (int)$product['stock_qty'] ?>" required>

                    <div>
                        <button type="submit" name="add_to_cart" class="btn">Add to Cart</button>
                    </div>
                </form>
            <?php else: ?>
                <p style="color:#f87171; font-weight:700;">Out of Stock</p>
            <?php endif; ?>
        </div>
    </div>
</main>
</body>
</html>
