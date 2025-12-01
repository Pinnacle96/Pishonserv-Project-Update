<?php
// PHP Error Debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include '../includes/db_connect.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'superadmin')) {
    header("Location: ../auth/login.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: admin_products.php");
    exit();
}

$id = intval($_GET['id']);

// Fetch product details
$product_stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
$product_stmt->bind_param("i", $id);
$product_stmt->execute();
$product = $product_stmt->get_result()->fetch_assoc();
$product_stmt->close();

if (!$product) {
    header("Location: admin_products.php");
    exit();
}

// Fetch existing images for the product
$images_stmt = $conn->prepare("SELECT id, image_url FROM product_images WHERE product_id = ? ORDER BY id ASC");
$images_stmt->bind_param("i", $id);
$images_stmt->execute();
$product_images = $images_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$images_stmt->close();

// Fetch ALL unique categories from the new product_categories table
$categories_stmt = $conn->prepare("SELECT DISTINCT category_path FROM product_categories ORDER BY category_path ASC");
$categories_stmt->execute();
$all_categories = $categories_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$categories_stmt->close();

// Fetch the current category for the product
$current_category_stmt = $conn->prepare("SELECT category_path FROM product_categories WHERE product_id = ? LIMIT 1");
$current_category_stmt->bind_param("i", $id);
$current_category_stmt->execute();
$current_category_result = $current_category_stmt->get_result();
$current_category = $current_category_result->fetch_assoc();
$current_category_path = $current_category ? $current_category['category_path'] : '';
$current_category_stmt->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $regular_price = floatval($_POST['regular_price']);
    $sale_price = floatval($_POST['sale_price']);
    $sku = trim($_POST['sku']);
    $published = isset($_POST['published']) ? 1 : 0;
    $new_category_path = trim($_POST['category_path']);

    // Update product details
    $update_product_stmt = $conn->prepare("UPDATE products SET name = ?, description = ?, regular_price = ?, sale_price = ?, sku = ?, published = ? WHERE id = ?");
    $update_product_stmt->bind_param("ssddssi", $name, $description, $regular_price, $sale_price, $sku, $published, $id);
    $update_product_stmt->execute();
    $update_product_stmt->close();

    // Update the product's category if it has changed
    if ($new_category_path !== $current_category_path) {
        // First, delete the old category entry
        $delete_category_stmt = $conn->prepare("DELETE FROM product_categories WHERE product_id = ?");
        $delete_category_stmt->bind_param("i", $id);
        $delete_category_stmt->execute();
        $delete_category_stmt->close();
        
        // Then, insert the new category entry
        $insert_category_stmt = $conn->prepare("INSERT INTO product_categories (product_id, category_path) VALUES (?, ?)");
        $insert_category_stmt->bind_param("is", $id, $new_category_path);
        $insert_category_stmt->execute();
        $insert_category_stmt->close();
    }
    
    // Handle new image uploads
    if (!empty($_FILES['image_files']['name'][0])) {
        $target_dir = '../public/uploads/';
        foreach ($_FILES['image_files']['name'] as $key => $file_name) {
            $temp_name = $_FILES['image_files']['tmp_name'][$key];
            if ($temp_name) {
                $new_file_name = time() . '_' . basename($file_name);
                $new_file_path = $target_dir . $new_file_name;
                if (move_uploaded_file($temp_name, $new_file_path)) {
                    $insert_image_stmt = $conn->prepare("INSERT INTO product_images (product_id, image_url) VALUES (?, ?)");
                    $image_url_db = 'uploads/' . $new_file_name;
                    $insert_image_stmt->bind_param("is", $id, $image_url_db);
                    $insert_image_stmt->execute();
                    $insert_image_stmt->close();
                }
            }
        }
    }
    
    // Handle image deletions
    if(isset($_POST['delete_images']) && is_array($_POST['delete_images'])) {
        $placeholders = implode(',', array_fill(0, count($_POST['delete_images']), '?'));
        $delete_sql = "DELETE FROM product_images WHERE id IN ($placeholders) AND product_id = ?";
        
        $delete_stmt = $conn->prepare($delete_sql);
        $types = str_repeat('i', count($_POST['delete_images'])) . 'i';
        $params = array_merge($_POST['delete_images'], [$id]);
        
        $delete_stmt->bind_param($types, ...$params);
        $delete_stmt->execute();
        $delete_stmt->close();
    }
    
    header("Location: admin_products.php?updated=1");
    exit();
}

$page_content = __DIR__ . "/admin_edit_product_content.php";
include 'dashboard_layout.php';
?>