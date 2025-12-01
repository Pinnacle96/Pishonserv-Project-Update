<?php
include 'includes/db_connect.php';

$products_per_page = 12;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $products_per_page;

$search = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? '');
$min_price = $_GET['min_price'] ?? '';
$max_price = $_GET['max_price'] ?? '';
$sort = $_GET['sort'] ?? '';

$where = ["p.published = 1", "EXISTS (SELECT 1 FROM product_images pi WHERE pi.product_id = p.id)"];
$params = [];
$types = "";

// Search
if ($search) {
    $where[] = "p.name LIKE ?";
    $params[] = "%$search%";
    $types .= "s";
}

// Category
if ($category) {
    $where[] = "EXISTS (SELECT 1 FROM product_categories pc WHERE pc.product_id = p.id AND pc.category_path = ?)";
    $params[] = $category;
    $types .= "s";
}

// Price filter
if ($min_price !== '') {
    $where[] = "p.sale_price >= ?";
    $params[] = $min_price;
    $types .= "d";
}
if ($max_price !== '') {
    $where[] = "p.sale_price <= ?";
    $params[] = $max_price;
    $types .= "d";
}

// Count total
$sql_count = "SELECT COUNT(DISTINCT p.id) AS total FROM products p WHERE " . implode(" AND ", $where);
$stmt = $conn->prepare($sql_count);
if($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$total_products = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();
$total_pages = ceil($total_products / $products_per_page);

// Sorting
$order = "p.name ASC";
if($sort == "price_asc") $order = "p.sale_price ASC";
elseif($sort == "price_desc") $order = "p.sale_price DESC";
elseif($sort == "newest") $order = "p.id DESC";

// Fetch products
$sql = "
    SELECT p.id, p.name, p.sale_price, p.regular_price,
           (SELECT image_url FROM product_images WHERE product_id = p.id ORDER BY id ASC LIMIT 1) AS image_url
    FROM products p
    WHERE " . implode(" AND ", $where) . "
    ORDER BY $order
    LIMIT ? OFFSET ?
";
$params2 = $params;
$types2 = $types . "ii";
$params2[] = $products_per_page;
$params2[] = $offset;

$stmt = $conn->prepare($sql);
$stmt->bind_param($types2, ...$params2);
$stmt->execute();
$result = $stmt->get_result();
?>

<?php if($total_products > 0): ?>
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
<?php while($p = $result->fetch_assoc()): 
    $image_url = $p['image_url'] ?: 'https://placehold.co/400x300';
    $discount = 0;
    if ($p['sale_price'] && $p['regular_price'] > $p['sale_price']) {
        $discount = round((($p['regular_price'] - $p['sale_price']) / $p['regular_price']) * 100);
    }
?>
<div class="relative bg-white rounded-xl shadow-sm p-4 product-card hover:shadow-lg">
    <?php if($discount): ?><div class="discount-badge">-<?= $discount ?>%</div><?php endif; ?>
    <div class="relative h-48 overflow-hidden rounded-lg mb-4">
        <img src="<?= htmlspecialchars($image_url) ?>" class="w-full h-full object-cover transition duration-300 hover:scale-105">
    </div>
    <h3 class="text-lg font-semibold text-gray-900 mb-2 truncate"><?= htmlspecialchars($p['name']) ?></h3>
    <div class="flex items-center mb-4">
        <?php if ($p['sale_price'] && $p['sale_price'] < $p['regular_price']): ?>
            <span class="text-blue-600 text-lg font-semibold">₦<?= number_format($p['sale_price']) ?></span>
            <span class="line-through text-gray-500 text-sm ml-2">₦<?= number_format($p['regular_price']) ?></span>
        <?php elseif($p['regular_price']): ?>
            <span class="text-blue-600 text-lg font-semibold">₦<?= number_format($p['regular_price']) ?></span>
        <?php else: ?>
            <span class="text-sm text-gray-500">Price on request</span>
        <?php endif; ?>
    </div>
    <div class="flex space-x-3">
        <a href="product_detail.php?id=<?= $p['id'] ?>" class="flex-1 text-center bg-blue-600 text-white py-2.5 rounded-lg hover:bg-blue-700 transition font-medium text-sm">View Details</a>
        <button class="w-11 h-11 flex items-center justify-center bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200 transition">
            <i class="far fa-heart text-lg"></i>
        </button>
    </div>
</div>
<?php endwhile; ?>
</div>

<!-- Pagination -->
<?php if($total_pages > 1): ?>
<nav class="flex justify-center mt-12 mb-8" aria-label="Pagination">
    <ul class="flex flex-wrap justify-center items-center gap-1 sm:gap-2">
        <!-- Previous Button -->
        <li>
            <a href="#" data-page="<?= max(1, $page-1) ?>" 
               class="flex items-center px-3 py-2 rounded-lg border bg-white text-gray-700 hover:bg-gray-100 pagination-link <?= ($page <= 1) ? 'opacity-50 cursor-not-allowed pointer-events-none' : '' ?>">
                <i class="fas fa-chevron-left mr-1 text-sm"></i> 
                <span class="hidden sm:inline">Previous</span>
            </a>
        </li>

        <!-- First Page -->
        <?php if($page > 3): ?>
        <li>
            <a href="#" data-page="1" class="px-3 py-2 rounded-lg border bg-white text-gray-700 hover:bg-gray-100 pagination-link">1</a>
        </li>
        <?php if($page > 4): ?>
        <li class="px-2 py-2 text-gray-500">...</li>
        <?php endif; ?>
        <?php endif; ?>

        <!-- Page Numbers -->
        <?php 
        $start = max(1, $page - 2);
        $end = min($total_pages, $page + 2);
        
        for($i = $start; $i <= $end; $i++): 
        ?>
        <li>
            <a href="#" data-page="<?= $i ?>" 
               class="px-3 py-2 rounded-lg border pagination-link min-w-[40px] text-center <?= ($i==$page)?'bg-blue-600 text-white font-semibold border-blue-600':'bg-white text-gray-700 hover:bg-gray-100' ?>">
                <?= $i ?>
            </a>
        </li>
        <?php endfor; ?>

        <!-- Last Page -->
        <?php if($page < $total_pages - 2): ?>
        <?php if($page < $total_pages - 3): ?>
        <li class="px-2 py-2 text-gray-500">...</li>
        <?php endif; ?>
        <li>
            <a href="#" data-page="<?= $total_pages ?>" class="px-3 py-2 rounded-lg border bg-white text-gray-700 hover:bg-gray-100 pagination-link"><?= $total_pages ?></a>
        </li>
        <?php endif; ?>

        <!-- Next Button -->
        <li>
            <a href="#" data-page="<?= min($total_pages, $page+1) ?>" 
               class="flex items-center px-3 py-2 rounded-lg border bg-white text-gray-700 hover:bg-gray-100 pagination-link <?= ($page >= $total_pages) ? 'opacity-50 cursor-not-allowed pointer-events-none' : '' ?>">
                <span class="hidden sm:inline">Next</span>
                <i class="fas fa-chevron-right ml-1 text-sm"></i>
            </a>
        </li>
    </ul>
</nav>
<?php endif; ?>

<?php else: ?>
<div class="bg-white rounded-xl shadow-sm p-8 text-center">
    <i class="fas fa-box-open text-5xl text-gray-300 mb-5"></i>
    <h3 class="text-xl font-medium text-gray-700 mb-3">No products found</h3>
    <p class="text-gray-500 mb-5">We couldn't find any products matching your selection.</p>
    <a href="furniture.php" class="inline-block bg-blue-600 text-white px-7 py-2.5 rounded-lg hover:bg-blue-700 transition font-medium">
        Browse All Products
    </a>
</div>
<?php endif; ?>
