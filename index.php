<?php
session_start();
require_once 'config/db.php';

$category = isset($_GET['category']) ? trim($_GET['category']) : 'All';
$search   = isset($_GET['q']) ? trim($_GET['q']) : '';

$categories = ['All', 'Cricket', 'Jersey', 'Football', 'Footwear'];
if (!in_array($category, $categories, true)) {
    $category = 'All';
}

$sql = "SELECT * FROM products WHERE is_active = 1";
$params = [];

if ($category !== 'All') {
    $sql .= " AND category = :category";
    $params[':category'] = $category;
}

if ($search !== '') {
    $likeSearch = '%' . $search . '%';
    $sql .= " AND (name LIKE :search_name OR description LIKE :search_desc OR category LIKE :search_category)";
    $params[':search_name'] = $likeSearch;
    $params[':search_desc'] = $likeSearch;
    $params[':search_category'] = $likeSearch;
}

$sql .= " ORDER BY id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

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
    <title>Samy Sports | Gear Up & Dominate</title>
    <style>
        :root {
            --bg: #0f1115;
            --card: #181b22;
            --text: #f3f4f6;
            --muted: #9ca3af;
            --accent: #22c55e;
            --accent-hover: #16a34a;
            --border: #262b36;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: system-ui, -apple-system, sans-serif; }
        body { background: var(--bg); color: var(--text); line-height: 1.5; }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 16px; }
        header {
            background: var(--card);
            border-bottom: 1px solid var(--border);
            position: sticky; top: 0; z-index: 20;
        }
        .nav {
            display: flex; align-items: center; justify-content: space-between;
            gap: 12px; padding: 14px 0; flex-wrap: wrap;
        }
        .brand { font-size: 1.35rem; font-weight: 800; color: var(--accent); text-decoration: none; letter-spacing: 0.5px; }
        .search-form { display: flex; gap: 8px; flex: 1; max-width: 420px; }
        .search-form input {
            flex: 1; padding: 9px 12px; border-radius: 8px;
            border: 1px solid var(--border); background: var(--bg); color: var(--text);
        }
        .btn {
            background: var(--accent); color: #07130b; border: none;
            padding: 9px 14px; border-radius: 8px; font-weight: 700;
            cursor: pointer; text-decoration: none; display: inline-block;
        }
        .btn:hover { background: var(--accent-hover); }
        .btn-outline {
            background: transparent; color: var(--text); border: 1px solid var(--border);
        }
        .btn-outline.active, .btn-outline:hover { border-color: var(--accent); color: var(--accent); }
        .filters { display: flex; gap: 8px; flex-wrap: wrap; margin: 20px 0; }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 18px; margin-bottom: 40px;
        }
        .product-card {
            background: var(--card); border: 1px solid var(--border);
            border-radius: 12px; overflow: hidden; display: flex; flex-direction: column;
        }
        .product-card img { width: 100%; height: 190px; object-fit: cover; }
        .product-body { padding: 14px; display: flex; flex-direction: column; gap: 8px; flex: 1; }
        .cat { font-size: 0.75rem; text-transform: uppercase; color: var(--accent); font-weight: 700; }
        .title { font-size: 1.05rem; font-weight: 700; }
        .desc { font-size: 0.88rem; color: var(--muted); flex: 1; }
        .price-row { display: flex; align-items: center; gap: 8px; font-weight: 700; }
        .price-now { font-size: 1.1rem; color: #fff; }
        .price-old { font-size: 0.9rem; color: var(--muted); text-decoration: line-through; }
        .stock { font-size: 0.8rem; color: var(--muted); }
        .empty { text-align: center; padding: 48px 16px; color: var(--muted); }
    </style>
</head>
<body>
<header>
    <div class="container nav">
        <a href="index.php" class="brand">SAMY SPORTS</a>
        <form class="search-form" method="GET" action="index.php">
            <?php if ($category !== 'All'): ?>
                <input type="hidden" name="category" value="<?= htmlspecialchars($category) ?>">
            <?php endif; ?>
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search bats, jerseys, shoes...">
            <button type="submit" class="btn">Search</button>
        </form>
        <a href="cart.php" class="btn btn-outline">Cart (<?= $cartCount ?>)</a>
    </div>
</header>

<main class="container">
    <div class="filters">
        <?php foreach ($categories as $cat): ?>
            <?php
                $url = 'index.php?category=' . urlencode($cat);
              
            ?>
            <a href="<?= htmlspecialchars($url) ?>"
               class="btn btn-outline <?= $category === $cat ? 'active' : '' ?>">
                <?= htmlspecialchars($cat) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if (empty($products)): ?>
        <div class="empty">No products found for your filter.</div>
    <?php else: ?>
        <div class="grid">
            <?php foreach ($products as $p): ?>
                <?php $finalPrice = ($p['discount_price'] !== null && $p['discount_price'] > 0) ? $p['discount_price'] : $p['price']; ?>
                <div class="product-card">
                    <img src="<?= htmlspecialchars($p['image_url']) ?>" alt="<?= htmlspecialchars($p['name']) ?>">
                    <div class="product-body">
                        <span class="cat"><?= htmlspecialchars($p['category']) ?></span>
                        <h2 class="title"><?= htmlspecialchars($p['name']) ?></h2>
                        <p class="desc"><?= htmlspecialchars($p['description']) ?></p>
                        <div class="price-row">
                            <span class="price-now">₹<?= number_format((float)$finalPrice, 2) ?></span>
                            <?php if ($p['discount_price'] !== null && $p['discount_price'] > 0): ?>
                                <span class="price-old">₹<?= number_format((float)$p['price'], 2) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="stock">
                            <?= (int)$p['stock_qty'] > 0 ? "In Stock (" . (int)$p['stock_qty'] . " left)" : "Out of Stock" ?>
                        </div>
                        <a href="product.php?id=<?= (int)$p['id'] ?>" class="btn" style="text-align:center; margin-top:6px;">
                            View & Select Size
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>
</body>
</html>