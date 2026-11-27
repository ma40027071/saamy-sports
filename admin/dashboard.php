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

$totalProducts = 0;
$lowStockCount = 0;
$totalOrders   = 0;
$totalRevenue  = 0.0;
$recentOrders  = [];
$dashboardNote = '';

try {
    $totalProducts = (int)$pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
    $lowStockCount = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE stock_qty <= 5")->fetchColumn();
} catch (Throwable $e) {
    $dashboardNote = 'Could not load product metrics: ' . $e->getMessage();
}

try {
    $totalOrders = (int)$pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();

    // Try common total column names safely
    $colsStmt = $pdo->query("SHOW COLUMNS FROM orders");
    $orderCols = array_column($colsStmt->fetchAll(PDO::FETCH_ASSOC), 'Field');

    $amountCol = null;
    foreach (['total_amount', 'total_price', 'grand_total', 'amount', 'total'] as $candidate) {
        if (in_array($candidate, $orderCols, true)) {
            $amountCol = $candidate;
            break;
        }
    }

    if ($amountCol !== null) {
        $totalRevenue = (float)$pdo->query("SELECT COALESCE(SUM(`{$amountCol}`), 0) FROM orders")->fetchColumn();
    }

    $recentOrders = $pdo->query("SELECT * FROM orders ORDER BY id DESC LIMIT 6")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    // Orders table may not exist yet or have a different structure
}

$adminName = $_SESSION['admin_username'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Samy Sports</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: Arial, sans-serif; }
        body { background: #0f172a; color: #f8fafc; min-height: 100vh; }
        .topbar {
            background: #1e293b;
            padding: 16px 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #334155;
            flex-wrap: wrap;
            gap: 12px;
        }
        .brand { font-size: 20px; font-weight: bold; color: #22c55e; }
        .nav { display: flex; gap: 18px; align-items: center; }
        .nav a {
            color: #e2e8f0;
            text-decoration: none;
            font-size: 14px;
            padding: 8px 12px;
            border-radius: 6px;
        }
        .nav a:hover, .nav a.active { background: #334155; color: #fff; }
        .nav a.logout { background: #7f1d1d; color: #fecaca; }
        .container { max-width: 1150px; margin: 28px auto; padding: 0 20px; }
        .welcome { margin-bottom: 22px; }
        .welcome h1 { font-size: 24px; margin-bottom: 4px; }
        .welcome p { color: #94a3b8; font-size: 14px; }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 18px;
            margin-bottom: 28px;
        }
        .card {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 10px;
            padding: 20px;
        }
        .card .label { color: #94a3b8; font-size: 13px; margin-bottom: 8px; }
        .card .value { font-size: 28px; font-weight: bold; color: #fff; }
        .card.warn .value { color: #facc15; }
        .card.accent .value { color: #22c55e; }
        .panel {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 10px;
            padding: 20px;
        }
        .panel-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }
        .panel-head h2 { font-size: 18px; }
        .panel-head a { color: #38bdf8; text-decoration: none; font-size: 14px; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th, td { text-align: left; padding: 12px 10px; border-bottom: 1px solid #334155; }
        th { color: #94a3b8; font-weight: 600; }
        .empty { color: #94a3b8; padding: 16px 0; font-size: 14px; }
        .notice {
            background: #451a03;
            border: 1px solid #b45309;
            color: #fde68a;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="brand">Samy Sports Admin</div>
        <nav class="nav">
            <a href="dashboard.php" class="active">Dashboard</a>
            <a href="products.php">Products</a>
            <a href="orders.php">Orders</a>
            <a href="dashboard.php?logout=1" class="logout">Logout</a>
        </nav>
    </header>

    <main class="container">
        <div class="welcome">
            <h1>Welcome, <?= htmlspecialchars($adminName) ?></h1>
            <p>Store stock, orders, and quick admin actions overview</p>
        </div>

        <?php if ($dashboardNote !== ''): ?>
            <div class="notice"><?= htmlspecialchars($dashboardNote) ?></div>
        <?php endif; ?>

        <section class="grid">
            <div class="card accent">
                <div class="label">Total Products</div>
                <div class="value"><?= $totalProducts ?></div>
            </div>
            <div class="card warn">
                <div class="label">Low Stock (&le; 5)</div>
                <div class="value"><?= $lowStockCount ?></div>
            </div>
            <div class="card">
                <div class="label">Total Orders</div>
                <div class="value"><?= $totalOrders ?></div>
            </div>
            <div class="card accent">
                <div class="label">Total Revenue</div>
                <div class="value">&#8377;<?= number_format($totalRevenue, 2) ?></div>
            </div>
        </section>

        <section class="panel">
            <div class="panel-head">
                <h2>Recent Orders</h2>
                <a href="orders.php">View All Orders &rarr;</a>
            </div>

            <?php if (empty($recentOrders)): ?>
                <p class="empty">No orders found yet.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Status</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentOrders as $ord): ?>
                            <?php
                                $customer = $ord['customer_name'] ?? $ord['name'] ?? $ord['phone'] ?? '-';
                                $status   = $ord['status'] ?? $ord['order_status'] ?? 'Pending';
                                $created  = $ord['created_at'] ?? '-';
                            ?>
                            <tr>
                                <td>#<?= htmlspecialchars((string)($ord['id'] ?? '')) ?></td>
                                <td><?= htmlspecialchars((string)$customer) ?></td>
                                <td><?= htmlspecialchars((string)$status) ?></td>
                                <td><?= htmlspecialchars((string)$created) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>