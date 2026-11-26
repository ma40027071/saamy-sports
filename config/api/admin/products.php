<?php
session_start();
require_once '../config/db.php';

if (empty($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_product'])) {
        $name          = trim($_POST['name'] ?? '');
        $category      = trim($_POST['category'] ?? 'Cricket');
        $description   = trim($_POST['description'] ?? '');
        $price         = (float)($_POST['price'] ?? 0);
        $discountPrice = ($_POST['discount_price'] !== '') ? (float)$_POST['discount_price'] : null;
        $stockQty      = max(0, (int)($_POST['stock_qty'] ?? 0));
        $sizes         = trim($_POST['sizes'] ?? 'Free Size');
        $imageUrl      = trim($_POST['image_url'] ?? '');

        if ($name === '' || $price <= 0 || $imageUrl === '') {
            $error = 'Product name, valid price, and image URL are required.';
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO products (name, category, description, price, discount_price, stock_qty, sizes, image_url, is_active)
                VALUES (:name, :category, :description, :price, :discount_price, :stock_qty, :sizes, :image_url, 1)
            ");
            $stmt->execute([
                ':name'           => $name,
                ':category'       => $category,
                ':description'    => $description,
                ':price'          => $price,
                ':discount_price' => $discountPrice,
                ':stock_qty'      => $stockQty,
                ':sizes'          => $sizes !== '' ? $sizes : 'Free Size',
                ':image_url'      => $imageUrl,
            ]);
            $message = 'New product added successfully!';
        }
    } elseif (isset($_POST['update_stock'], $_POST['product_id'], $_POST['stock_qty'])) {
        $pid = (int)$_POST['product_id'];
        $qty = max(0, (int)$_POST['stock_qty']);
        $stmt = $pdo->prepare("UPDATE products SET stock_qty = :qty WHERE id = :id");
        $stmt->execute([':qty' => $qty, ':id' => $pid]);
        $message = 'Stock updated.';
    } elseif (isset($_POST['toggle_active'], $_POST['product_id'])) {
        $pid = (int)$_POST['product_id'];
        $stmt = $pdo->prepare("UPDATE products SET is_active = IF(is_active = 1, 0, 1) WHERE id = :id");
        $stmt->execute([':id' => $pid]);
        $message = 'Product status updated.';
    }
}

$products = $pdo->query("SELECT * FROM products ORDER BY id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Products | Samy Sports Admin</title>
    <style>
        :root {
            --bg: #0f1115; --card: #181b22; --text: #f3f4f6;
            --muted: #9ca3af; --accent: #22c55e; --border: #262b36;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: system-ui, -apple-system, sans-serif; }
        body { background: var(--bg); color: var(--text); line-height: 1.5; }
        .container { max-width: 1140px; margin: 0 auto; padding: 16px; }
        header { background: var(--card); border-bottom: 1px solid var(--border); padding: 14px 0; margin-bottom: 24px; }
        .nav { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .brand { font-size: 1.25rem; font-weight: 800; color: var(--accent); text-decoration: none; }
        .btn { background: var(--accent); color: #07130b; border: none; padding: 9px 14px; border-radius: 8px; font-weight: 700; cursor: pointer; text-decoration: none; font-size: 0.88rem; }
        .btn-outline { background: transparent; color: var(--text); border: 1px solid var(--border); padding: 7px 11px; border-radius: 8px; cursor: pointer; text-decoration: none; font-size: 0.85rem; }
        .box { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 20px; margin-bottom: 24px; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; }
        label { display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 4px; }
        input, select, textarea { width: 100%; padding: 9px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); color: var(--text); }
        .msg { background: rgba(34,197,94,0.15); color: #86efac; padding: 10px 14px; border-radius: 8px; margin-bottom: 16px; }
        .err { background: #3f1d1d; color: #fca5a5; padding: 10px 14px; border-radius: 8px; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        th, td { padding: 10px; border-bottom: 1px solid var(--border); text-align: left; }
        th { color: var(--muted); font-size: 0.8rem; text-transform: uppercase; }
        .stock-inp { width: 72px; padding: 6px; border-radius: 6px; border: 1px solid var(--border); background: var(--bg); color: var(--text); }
        .table-wrap { overflow-x: auto; }
    </style>
</head>
<body>
<header>
    <div class="container nav">
        <a href="products.php" class="brand">SAMY SPORTS — ADMIN</a>
        <div style="display:flex; gap:10px; align-items:center;">
            <a href="../index.php" target="_blank" class="btn-outline">View Storefront</a>
            <a href="products.php?logout=1" class="btn-outline">Logout</a>
        </div>
    </div>
</header>

<main class="container">
    <?php if ($message !== ''): ?>
        <div class="msg"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="err"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" class="box">
        <h2 style="font-size:1.1rem; margin-bottom:14px;">Add New Product</h2>
        <div class="form-grid">
            <div>
                <label for="name">Product Name *</label>
                <input type="text" id="name" name="name" placeholder="e.g. English Willow Bat" required>
            </div>
            <div>
                <label for="category">Category *</label>
                <select id="category" name="category">
                    <option value="Cricket">Cricket</option>
                    <option value="Jersey">Jersey</option>
                    <option value="Football">Football</option>
                    <option value="Footwear">Footwear</option>
                </select>
            </div>
            <div>
                <label for="price">Price (₹) *</label>
                <input type="number" step="0.01" id="price" name="price" required>
            </div>
            <div>
                <label for="discount_price">Offer Price (₹) (Optional)</label>
                <input type="number" step="0.01" id="discount_price" name="discount_price">
            </div>
            <div>
                <label for="stock_qty">Stock Qty *</label>
                <input type="number" id="stock_qty" name="stock_qty" value="10" min="0" required>
            </div>
            <div>
                <label for="sizes">Sizes (comma separated)</label>
                <input type="text" id="sizes" name="sizes" value="S,M,L,XL" placeholder="e.g. SH,H or UK 7,8,9">
            </div>
            <div style="grid-column: 1 / -1;">
                <label for="image_url">Image URL or Relative Path *</label>
                <input type="text" id="image_url" name="image_url" placeholder="https://... or assets/uploads/bat.jpg" required>
            </div>
            <div style="grid-column: 1 / -1;">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="2"></textarea>
            </div>
        </div>
        <button type="submit" name="add_product" class="btn" style="margin-top:14px;">+ Add Product</button>
    </form>

    <div class="box">
        <h2 style="font-size:1.1rem; margin-bottom:14px;">All Products (<?= count($products) ?>)</h2>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Product</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Stock</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($products as $p): ?>
                    <tr>
                        <td>#<?= (int)$p['id'] ?></td>
                        <td><strong><?= htmlspecialchars($p['name']) ?></strong><br><span style="color:var(--muted); font-size:0.8rem;"><?= htmlspecialchars($p['sizes']) ?></span></td>
                        <td><?= htmlspecialchars($p['category']) ?></td>
                        <td>
                            ₹<?= number_format((float)$p['price'], 2) ?>
                            <?php if ($p['discount_price'] !== null && (float)$p['discount_price'] > 0): ?>
                                <br><span style="color:var(--accent); font-size:0.8rem;">Offer: ₹<?= number_format((float)$p['discount_price'], 2) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="POST" style="display:flex; gap:6px; align-items:center;">
                                <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                                <input type="number" name="stock_qty" class="stock-inp" value="<?= (int)$p['stock_qty'] ?>" min="0">
                                <button type="submit" name="update_stock" class="btn-outline">Save</button>
                            </form>
                        </td>
                        <td><?= (int)$p['is_active'] === 1 ? '<span style="color:#86efac;">Active</span>' : '<span style="color:#fca5a5;">Hidden</span>' ?></td>
                        <td>
                            <form method="POST">
                                <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                                <button type="submit" name="toggle_active" class="btn-outline">
                                    <?= (int)$p['is_active'] === 1 ? 'Hide' : 'Show' ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
</body>
</html>