<?php
session_start();
require_once '../database.php';
// Basic (optional) admin check placeholder - extend later
// if(!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) { header('Location: ../home.php'); exit; }
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
						<th>Description</th>
						<th>Actions</th>
					</tr>
				</thead>
			<tbody id="productsTbody"></tbody>
		</table>


	</section>

	<!-- Product Modal -->
	<div class="modal" id="productModal">
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
					<input type="number" name="price" id="price" min="0" step="0.01" required />
				</div>
				<div class="form-row">
					<label>Description</label>
					<textarea name="product_details" id="product_details" rows="3"></textarea>
				</div>
				<div class="form-row">
					<label>Add image/s</label>
					<div class="file-chooser">
						<label class="file-btn">Add image/s
							<input type="file" name="images_files[]" id="images_files" accept="image/*" multiple />
						</label>
						<div class="file-info" id="fileInfo" aria-live="polite"></div>
					</div>
					<div id="imagePreview" class="image-preview"></div>
				</div>
				<div class="form-actions">
					<button type="submit" class="add-btn" id="saveProductBtn">Save</button>
				</div>
			</form>
		</div>
	</div>

	<!-- Service Modal -->
	<div class="modal" id="serviceModal">
		<div class="modal-content">
			<div class="modal-header">
				<h2>Add Service</h2>
				<button type="button" class="close-modal" data-close>&times;</button>
			</div>
			<form id="serviceForm">
				<input type="hidden" name="service_id" id="service_id" />
				<div class="form-row">
					<label>Service Name</label>
					<input type="text" name="service_name" id="service_name" required />
				</div>
							<div class="form-row">
								<label>Upload Image</label>
								<div class="file-chooser">
									<label class="file-btn">Choose File
										<input type="file" name="service_image" id="service_image" accept="image/*" />
									</label>
									<div class="file-info" id="serviceFileInfo" aria-live="polite"></div>
								</div>
								<div id="serviceImagePreview" class="image-preview"></div>
							</div>
							<div class="form-actions">
								<button type="submit" class="add-btn">Add</button>
							</div>
							<hr />
							<div id="servicesList" class="services-list">
								<!-- service cards rendered here by admin-products.js -->
							</div>
			</form>
		</div>
	</div>

	<script src="admin.js"></script>
	<script src="admin-products.js?v=<?php echo time(); ?>"></script>
</body>
</html>
