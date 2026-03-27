<?php
session_start();

if (!isset($_SESSION['customer_id'])) {
    header('Location: customer-login.html');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Dashboard — Greenwood Zoo</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .dashboard-wrapper {
            box-sizing: border-box;
            min-height: 100vh;
            padding: clamp(24px, 4vw, 48px);
            background-color: var(--base-color);
        }

        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 2rem;
            padding-bottom: 1.25rem;
            border-bottom: 3px solid var(--accent-color);
        }

        .dashboard-header h1 {
            margin: 0 0 0.35rem;
            font-size: clamp(1.5rem, 3vw, 1.85rem);
        }

        .dashboard-header .sub {
            margin: 0;
            color: var(--text-color);
            opacity: 0.9;
            font-size: 0.95rem;
        }

        .card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 1.25rem;
        }

        .card {
            background-color: white;
            border-radius: 15px;
            padding: 1.35rem 1.5rem;
            box-shadow: 0 4px 18px rgba(0, 0, 0, 0.06);
            border: 1px solid rgba(23, 103, 7, 0.08);
        }

        .card h2 {
            font-size: 1.05rem;
            margin: 0 0 0.85rem;
            color: var(--text-color);
            border-bottom: 2px solid var(--accent-color);
            padding-bottom: 0.5rem;
        }

        .card p.desc {
            font-size: 0.88rem;
            color: #3d5c38;
            margin: -0.25rem 0 1rem;
            line-height: 1.45;
        }

        .card a {
            display: block;
            padding: 10px 15px;
            margin-bottom: 8px;
            background-color: var(--base-color);
            border-radius: 8px;
            color: var(--text-color);
            font-weight: 600;
            text-decoration: none;
            transition: background 0.15s ease;
        }

        .card a:last-child {
            margin-bottom: 0;
        }

        .card a:hover {
            background-color: var(--accent-color);
        }

        .card a.primary {
            background-color: var(--accent-color);
            color: #fff;
        }

        .card a.primary:hover {
            background-color: var(--text-color);
            color: #fff;
        }

        .logout-btn {
            padding: 10px 25px;
            background-color: var(--accent-color);
            border: none;
            border-radius: 1000px;
            font: inherit;
            font-weight: 600;
            cursor: pointer;
            color: var(--text-color);
            text-decoration: none;
        }

        .logout-btn:hover {
            background-color: var(--text-color);
            color: white;
        }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <div class="dashboard-header">
            <div>
                <h1>Welcome back</h1>
                <p class="sub">Hello, <?php echo htmlspecialchars($_SESSION['firstname']); ?> — plan your visit or explore the zoo.</p>
            </div>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>

        <div class="card-grid">
            <div class="card">
                <h2>Tickets &amp; visits</h2>
                <p class="desc">Buy admission for a future date and review tickets on your account.</p>
                <a class="primary" href="purchase_ticket.php">Purchase tickets</a>
                <a href="customer_tickets_report.php">View my tickets</a>
            </div>

            <div class="card">
                <h2>Explore</h2>
                <p class="desc">See which animals are in our habitats and where to find them.</p>
                <a href="customer_animals_report.php">View animals</a>
            </div>

            <div class="card">
                <h2>Zoo website</h2>
                <p class="desc">Hours, gallery, and general information for guests.</p>
                <a href="index.html">Visit homepage</a>
            </div>
        </div>
    </div>
</body>
</html>
