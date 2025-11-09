<?php 
session_start(); 
require_once '../database.php'; 
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate" />
    <meta http-equiv="Pragma" content="no-cache" />
    <meta http-equiv="Expires" content="0" />
    <title>Admin - Orders</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@200;300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
  <link rel="stylesheet" href="nav&side.css">
  <link rel="stylesheet" href="admin-orders.css">
</head>
<body>
    <div class="admin-wrapper">
        <aside class="sidebar">
            <div class="logo-box">
                <img src="img/logo.png" alt="ILovePrintshoppe Logo" class="admin-logo">
            </div>
            <nav class="admin-nav">
                <ul class="nav-links">
                    <li><a href="admin.html">Dashboard</a></li>
                    <li><a href="admin-products.php">Products</a></li>
                    <li><a href="admin-orders.php">Orders</a></li>
                    <li><a href="admin-payments.php">Payments</a></li>
                    <li><a href="admin-reports.php">Reports</a></li>
                    <li><a href="settings.html">Settings</a></li>
                </ul>
            </nav>
        </aside>
        <div class="main-panel">
            <header class="admin-header">
                <div class="right-header">
                    <!-- <div class="search-box">
                        <input type="text" placeholder="Search" />
                        <a href="#" class="auth-link"><i class="fas fa-search"></i></a>
                    </div> -->
                    <div class="notifications">
                        <div class="icon-wrapper">
                            <a href="#" class="auth-link"><i class="fas fa-bell"></i></a>
                        </div>
                    </div>
                    <div class="user-profile">
                        <a href="" class="auth-link1"><i class="fa-solid fa-user"></i></a>
                    </div>
                </div>
            </header>

            <section class="product-dashboard">
                <div class="dashboard-header">
                    <h1>Orders</h1>
                    <div class="controls">
                        <div class="controls-row">
                            <input type="text" placeholder="Search Order" class="search-input" />
                            <button class="add-btn"><i class="fas fa-plus"></i> Add Order</button>
                        </div>
                        <span class="last-updated" id="lastUpdated" aria-live="polite"></span>
                    </div>
                </div>
                <div id="ordersFilterPanel" class="filter-panel" hidden>
                    <form id="ordersFilterForm" autocomplete="off">
                        <div class="fp-row">
                            <label>Status
                                <select name="order_status" class="fp-input">
                                    <option value="">Any</option>
                                    <option value="Pending">Pending</option>
                                    <option value="Processing">Processing</option>
                                    <option value="Ready for Pickup">Ready for Pickup</option>
                                    <option value="Ready to Ship">Ready to Ship</option>
                                    <option value="Cancelled">Cancelled</option>
                                    <option value="Completed">Completed</option>
                                </select>
                            </label>
                            <label>Delivery
                                <select name="delivery_status" class="fp-input">
                                    <option value="">Any</option>
                                    <option value="Pending">Pending</option>
                                    <option value="Shipped">Shipped</option>
                                    <option value="Delivered">Delivered</option>
                                    <option value="Picked up">Picked up</option>
                                    <option value="Failed">Failed</option>
                                </select>
                            </label>
                            <label>Min Paid
                                <input type="number" step="0.01" name="min_paid" class="fp-input" placeholder="0" />
                            </label>
                            <label>Max Paid
                                <input type="number" step="0.01" name="max_paid" class="fp-input" placeholder="" />
                            </label>
                            <label>Customer
                                <input type="text" name="customer" class="fp-input" placeholder="Name" />
                            </label>
                        </div>
                        <div class="fp-actions">
                            <button type="submit" class="fp-apply"><i class="fas fa-check"></i> Apply</button>
                            <button type="reset" class="fp-reset"><i class="fas fa-undo"></i> Reset</button>
                        </div>
                    </form>
                </div>
                <div class="table-scroll">
                <table class="product-table" id="ordersTable">
                    <colgroup>
                        <col class="col-id"> 
                            <col class="col-customer"> 
                            <col class="col-design"> 
                            <col class="col-size">
                            <col class="col-qty">  
                            <col class="col-paid"> 
                            <col class="col-total">  
                            <col class="col-order-status">  
                            <col class="col-address"> 
                            <col class="col-delivery"> 
                    </colgroup>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Customer</th>
                            <th>Design</th>
                            <th>Size</th>
                            <th>Quantity</th>
                            <th>Amount Paid</th>
                            <th>Total Amount</th>
                            <th>Order Status</th>
                            <th>Delivery Address</th>
                            <th>Delivery Status</th>
                        </tr>
                    </thead>
                    <tbody id="ordersTbody"></tbody>
                </table>
                </div>

    <script src="admin-orders.js?v=12"></script>
</body>
</html>