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
  <title>Admin - Products</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@200;300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
  <link rel="stylesheet" href="nav&side.css">
  <link rel="stylesheet" href="admin-products.css">
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
					<li><a href="admin-orders.html">Orders</a></li>
					<li><a href="admin-payments.html">Payments</a></li>
					<li><a href="admin-reports.html">Reports</a></li>
					<li><a href="settings.html">Settings</a></li>
				</ul>
			</nav>
		</aside>

		<div class="main-panel">
			<header class="admin-header">
				<div class="right-header">
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
					<h1>All Products</h1>
					<div class="controls">
						<input type="text" placeholder="Search product" class="search-input" />
						<button class="filter-btn" id="openServiceModal"><i class="fas fa-plus"></i> Add Service</button>
						<button class="add-btn" id="openProductModal"><i class="fas fa-plus"></i> Add Product</button>
					</div>
				</div>

			<table class="product-table">
				<thead>
					<tr>
						<th>Service</th>
						<th>Image</th>
						<th>Product Name</th>
						<th>Price</th>
						<th>Product Details</th>
						<th>Actions</th>
					</tr>
				</thead>
			<tbody id="productsTbody"></tbody>
		</table>
	</section>

	<!-- Product Modal -->
	<div class="modal" id="productModal" style="display:none;">
		<div class="modal-content">
			<div class="modal-header">
				<h2 id="productModalTitle">Add Product</h2>
				<button type="button" class="close-modal" data-close>&times;</button>
			</div>
			<form id="productForm">
				<input type="hidden" name="product_id" id="product_id" />
				<div class="form-row">
					<label>Name</label>
					<input type="text" name="product_name" id="product_name" required />
				</div>
				<div class="form-row">
					<label>Service Type</label>
					<input type="text" name="service_type" id="service_type" list="serviceTypeList" />
					<datalist id="serviceTypeList"></datalist>
				</div>
				<div class="form-row">
					<label>Price (₱)</label>
					<input type="number" name="price" id="price" min="" step="0.01" required />
				</div>
				<div class="form-row">
					<label>Details</label>
					<textarea name="product_details" id="product_details" rows="3"></textarea>
				</div>
				<div class="form-row">
					<label>Images (comma or JSON array)</label>
					<input type="text" name="images" id="images" placeholder="img/a.jpg, img/b.jpg" />
				</div>
				<div class="form-actions">
					<button type="submit" class="add-btn" id="saveProductBtn">Save</button>
					<button type="button" class="filter-btn" data-close>Cancel</button>
				</div>
			</form>
		</div>
	</div>

	<!-- Service Modal -->
	<div class="modal" id="serviceModal" style="display:none;">
		<div class="modal-content" style="width:520px;">
			<div class="modal-header">
				<h2>Services</h2>
				<button type="button" class="close-modal" data-close>&times;</button>
			</div>
			<form id="serviceForm" style="margin-bottom:14px;">
				<div class="form-row" style="flex-direction:row; gap:10px; align-items:flex-end;">
					<div style="flex:1;">
						<label>Service Name</label>
						<input type="text" name="service_name" id="service_name" required />
					</div>
					<div style="flex:1;">
						<label>Description <span style="font-weight:400;color:#777;">(optional)</span></label>
						<input type="text" name="service_description" id="service_description" />
					</div>
					<div>
						<button type="submit" class="add-btn" style="margin-top:2px;">Add</button>
					</div>
				</div>
			</form>
			<div style="max-height:260px; overflow:auto; border:1px solid #eee; border-radius:8px;">
				<table style="width:100%; border-collapse:collapse; font-size:.8rem;">
					<thead>
						<tr style="background:#faf8f8;">
							<th style="text-align:left;padding:6px 10px;">Name</th>
							<th style="text-align:left;padding:6px 10px;">Description</th>
							<th style="padding:6px 10px;">Actions</th>
						</tr>
					</thead>
					<tbody id="servicesTbody"></tbody>
				</table>
			</div>
		</div>
	</div>

	<style>
	.modal { position:fixed; inset:0; background:rgba(0,0,0,.45); display:flex; align-items:center; justify-content:center; z-index:2000; }
	.modal-content { background:#fff; padding:20px 24px; border-radius:12px; width:420px; max-height:90vh; overflow:auto; box-shadow:0 10px 30px rgba(0,0,0,.25); animation:pop .25s ease; }
	@keyframes pop { from{ transform:translateY(10px); opacity:0;} to {transform:translateY(0); opacity:1;} }
	.modal-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:10px; }
	.modal-header h2 { font-size:1.2rem; margin:0; color:#752525; }
	.close-modal { background:none; border:none; font-size:1.5rem; cursor:pointer; color:#752525; }
	.form-row { display:flex; flex-direction:column; gap:6px; margin-bottom:12px; }
	.form-row label { font-size:.85rem; font-weight:600; color:#752525; }
	.form-row input[type=text], .form-row input[type=number], .form-row textarea { padding:8px 10px; font-size:.9rem; border:1px solid #ccc; border-radius:8px; resize:vertical; font-family:inherit; }
	.form-actions { display:flex; gap:10px; justify-content:flex-end; margin-top:4px; }
	.action-icon-btn { background:none; border:none; cursor:pointer; font-size:1.1rem; margin-right:6px; }
	.action-icon-btn.edit { color:#2e7d32; }
	.action-icon-btn.delete { color:#c62828; }
	.badge { display:inline-block; background:#752525; color:#fff; padding:2px 8px; border-radius:20px; font-size:.7rem; margin-left:6px; }
	.service-price { font-size:.75rem; color:#555; }
	.empty-row { text-align:center; padding:30px 10px; font-size:.9rem; color:#555; }
	#servicesTbody tr td { border-top:1px solid #f0e6e6; padding:6px 10px; vertical-align:middle; }
	#servicesTbody tr:first-child td { border-top:none; }
	.svc-del { background:none; border:none; cursor:pointer; color:#c62828; font-size:.9rem; }
	.svc-del:hover { color:#8e1d1d; }
	</style>
	</div>
	</div>

	<script src="admin.js"></script>
	<script src="admin-products.js?v=<?php echo time(); ?>"></script>
</body>
</html>
