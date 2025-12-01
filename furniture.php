<?php
session_start();
include 'includes/db_connect.php';
include 'includes/navbar.php';

// Selected category
$selectedCategoryPath = isset($_GET['category']) ? trim($_GET['category']) : '';

// Count all products
$count_sql = "
    SELECT COUNT(*) AS total_products 
    FROM products
    WHERE published = 1
    AND EXISTS (SELECT 1 FROM product_images pi WHERE pi.product_id = products.id)
";
$total_products = $conn->query($count_sql)->fetch_assoc()['total_products'];

// Get category paths with product counts
$categoryPathsWithCounts = [];
$stmt = $conn->prepare("
    SELECT pc.category_path, COUNT(p.id) AS product_count
    FROM product_categories pc
    JOIN products p ON pc.product_id = p.id
    WHERE p.published = 1
    AND EXISTS (SELECT 1 FROM product_images pi WHERE pi.product_id = p.id)
    GROUP BY pc.category_path
    ORDER BY pc.category_path ASC
");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $categoryPathsWithCounts[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Furniture Collection</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" crossorigin="anonymous" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
        .discount-badge { position: absolute; top: 0.75rem; right: 0.75rem; background-color: #ef4444; color: white; padding: 0.35rem 0.7rem; border-radius: 9999px; font-weight: 700; font-size: 0.8rem; z-index: 10; line-height: 1; box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
        .product-card { transition: all 0.3s ease; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05); }
        .product-card:hover { transform: translateY(-5px); box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1); }
        .active-category { background-color: #f0f9ff; border-left: 3px solid #3b82f6; }
        .pagination-link { transition: all 0.2s ease; }
        .pagination-link:hover:not(.active) { background-color: #f1f5f9; }
    </style>
</head>
<body class="pt-20">

<div class="container mx-auto px-4 py-8 md:py-12">
    <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl p-6 md:p-8 mb-10">
        <h1 class="text-3xl md:text-4xl font-bold text-gray-900 mb-3">Discover Our Furniture Collection</h1>
        <p class="text-gray-600 max-w-2xl">Explore our curated selection of high-quality furniture for every room in your home.</p>
    </div>

    <div class="flex flex-col md:flex-row gap-8">
        <!-- Sidebar -->
        <aside class="w-full md:w-1/4 bg-white p-6 rounded-xl shadow-sm md:sticky md:top-8 md:h-fit">
            <h2 class="text-lg font-bold mb-4 text-gray-800">Categories</h2>
            <div id="category-list-wrapper">
                <ul class="space-y-2">
                    <li>
                        <a href="#" data-category="" 
                           class="block px-4 py-2 rounded-lg category-link <?= !$selectedCategoryPath ? 'active-category font-semibold text-blue-600' : 'text-gray-700 hover:bg-gray-50' ?>">
                            <i class="fas fa-border-all mr-2 text-gray-400"></i> All Products
                            <span class="float-right bg-gray-100 text-gray-600 px-2 py-1 rounded-full text-xs"><?= $total_products ?></span>
                        </a>
                    </li>
                    <?php
                    $category_display_limit = 3;
                    $category_counter = 0;
                    foreach ($categoryPathsWithCounts as $cat):
                        $is_active = ($selectedCategoryPath === $cat['category_path']);
                        $should_hide = ($category_counter >= $category_display_limit) && !$is_active;
                        $visibility_class = $should_hide ? 'category-item-togglable hidden' : 'category-item-togglable';
                    ?>
                        <li class="<?= $visibility_class ?>">
                            <a href="#" data-category="<?= htmlspecialchars($cat['category_path']) ?>" 
                               class="block px-4 py-2 rounded-lg category-link <?= $is_active ? 'active-category font-semibold text-blue-600' : 'text-gray-700 hover:bg-gray-50' ?>">
                                <i class="fas fa-folder mr-2 text-gray-400"></i> <?= htmlspecialchars($cat['category_path']) ?>
                                <span class="float-right bg-gray-100 text-gray-600 px-2 py-1 rounded-full text-xs"><?= $cat['product_count'] ?></span>
                            </a>
                        </li>
                    <?php $category_counter++; endforeach; ?>
                </ul>
                <?php if (count($categoryPathsWithCounts) > $category_display_limit): ?>
                    <button id="toggle-categories-btn" class="mt-4 w-full text-blue-600 hover:text-blue-800 text-sm font-medium py-2 rounded-lg transition-colors duration-200">
                        Show More <i class="fas fa-chevron-down ml-1 text-xs"></i>
                    </button>
                <?php endif; ?>
            </div>

            <div class="mt-8 pt-6 border-t border-gray-100">
                <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-3">Filters</h3>
                <div class="space-y-3">
                    <div>
                        <label for="min-price" class="block text-sm font-medium text-gray-700 mb-1">Price Range</label>
                        <div class="flex items-center space-x-3">
                            <input type="number" id="min-price" placeholder="Min" class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500">
                            <span class="text-gray-400">-</span>
                            <input type="number" id="max-price" placeholder="Max" class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>
                    <button id="apply-filters" class="w-full mt-2 bg-blue-600 text-white py-2 rounded-md text-sm font-medium hover:bg-blue-700 transition">
                        Apply Filters
                    </button>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1">
            <div class="flex flex-col sm:flex-row sm:justify-between items-start sm:items-center mb-6 gap-3">
                <input type="text" id="search" placeholder="Search products..." class="w-full sm:w-1/3 px-4 py-2 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-blue-500" />
                <div class="relative w-full sm:w-auto">
                    <select id="sort" class="block appearance-none w-full bg-white border border-gray-300 text-gray-700 py-2 px-4 pr-8 rounded-md leading-tight focus:outline-none focus:ring-1 focus:ring-blue-500">
                        <option value="">Sort by: Featured</option>
                        <option value="price_asc">Price: Low to High</option>
                        <option value="price_desc">Price: High to Low</option>
                        <option value="newest">Newest Arrivals</option>
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700">
                        <i class="fas fa-chevron-down text-sm"></i>
                    </div>
                </div>
            </div>

            <div id="product-list" class="min-h-[200px] text-center py-10 text-gray-500">
                Loading...
            </div>
        </main>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

// <script>
// function loadProducts(page = 1) {
//     let query = $('#search').val();
//     let category = $('.category-link.active-category').data('category') || '';
//     let minPrice = $('#min-price').val();
//     let maxPrice = $('#max-price').val();
//     let sort = $('#sort').val();

//     $.ajax({
//         url: 'fetch_products.php',
//         type: 'GET',
//         data: { page, search: query, category, min_price: minPrice, max_price: maxPrice, sort },
//         beforeSend: function() {
//             $('#product-list').html('<div class="text-center py-10 text-gray-500">Loading...</div>');
//         },
//         success: function(data) {
//             $('#product-list').html(data);
//         }
//     });
// }

// loadProducts();

// $('#search').on('keyup', function(){ loadProducts(1); });
// $('#apply-filters').on('click', function(){ loadProducts(1); });
// $('#sort').on('change', function(){ loadProducts(1); });

// $(document).on('click', '.category-link', function(e){
//     e.preventDefault();
//     $('.category-link').removeClass('active-category font-semibold text-blue-600');
//     $(this).addClass('active-category font-semibold text-blue-600');
//     loadProducts(1);
// });

// $(document).on('click', '.pagination-link', function(e){
//     e.preventDefault();
//     if(!$(this).hasClass('opacity-50')){
//         loadProducts($(this).data('page'));
//     }
// });

// // Show More / Show Less Categories
// $('#toggle-categories-btn').on('click', function(){
//     let hiddenItems = $('.category-item-togglable.hidden');
//     if(hiddenItems.length){
//         hiddenItems.removeClass('hidden');
//         $(this).html('Show Less <i class="fas fa-chevron-up ml-1 text-xs"></i>');
//     } else {
//         $('.category-item-togglable').slice(5).addClass('hidden');
//         $(this).html('Show More <i class="fas fa-chevron-down ml-1 text-xs"></i>');
//     }
// });
// </script>
<script>
function loadProducts(page = 1) {
  let query = $('#search').val();
  let category = $('.category-link.active-category').data('category') || '';
  let minPrice = $('#min-price').val();
  let maxPrice = $('#max-price').val();
  let sort = $('#sort').val();

  $.ajax({
    url: 'fetch_products.php',
    type: 'GET',
    data: { page, search: query, category, min_price: minPrice, max_price: maxPrice, sort },
    beforeSend: function() {
      $('#product-list').html('<div class="text-center py-10 text-gray-500">Loading...</div>');
    },
    success: function(data) {
      $('#product-list').html(data);
    }
  });
}

loadProducts();

// ====== Category list helpers ======
const LIMIT = 3; // show only 3 initially
const isMobile = () => window.matchMedia('(max-width: 767px)').matches;

let expanded = false; // track state explicitly

function setButton(collapsed) {
  $('#toggle-categories-btn').html(
    collapsed
      ? 'Show More <i class="fas fa-chevron-down ml-1 text-xs"></i>'
      : 'Show Less <i class="fas fa-chevron-up ml-1 text-xs"></i>'
  );
}

function setCollapsed() {
  const $items = $('#category-list-wrapper li.category-item-togglable');
  const $activeLi = $('#category-list-wrapper .category-link.active-category').closest('li.category-item-togglable');

  // Hide everything after LIMIT…
  $items.each(function(idx) {
    if (idx >= LIMIT) $(this).addClass('hidden');
    else $(this).removeClass('hidden');
  });

  // …but always keep the active item visible
  if ($activeLi.length) $activeLi.removeClass('hidden');

  expanded = false;
  setButton(true);
}

function setExpanded() {
  $('#category-list-wrapper li.category-item-togglable').removeClass('hidden');
  expanded = true;
  setButton(false);
}

// ====== Search/filters/sort ======
$('#search').on('keyup', function(){ loadProducts(1); });
$('#apply-filters').on('click', function(){ loadProducts(1); });
$('#sort').on('change', function(){ loadProducts(1); });

// ====== Category click ======
$(document).on('click', '.category-link', function(e){
  e.preventDefault();
  $('.category-link').removeClass('active-category font-semibold text-blue-600');
  $(this).addClass('active-category font-semibold text-blue-600');

  // Keep URL in sync (optional)
  const cat = $(this).data('category') || '';
  const url = new URL(window.location.href);
  if (cat) url.searchParams.set('category', cat); else url.searchParams.delete('category');
  history.replaceState({}, '', url.toString());

  loadProducts(1);

  // Auto-collapse on mobile so list isn't too long
  if (isMobile()) setCollapsed();
});

// ====== Pagination ======
$(document).on('click', '.pagination-link', function(e){
  e.preventDefault();
  if(!$(this).hasClass('opacity-50')){
    loadProducts($(this).data('page'));
  }
});

// ====== Show More / Show Less ======
$('#toggle-categories-btn').on('click', function(){
  if (expanded) setCollapsed(); else setExpanded();
});

// ====== Initial state ======
(function initCategoryList(){
  // Ensure active (if below the fold) is visible and we start collapsed
  const $activeLi = $('#category-list-wrapper .category-link.active-category').closest('li.category-item-togglable');
  if ($activeLi.length) $activeLi.removeClass('hidden');
  setCollapsed();
})();

// Keep logic sane on resize (prefer collapsed on mobile)
$(window).on('resize', function() {
  if (isMobile() && expanded === false) {
    setCollapsed();
  }
});
</script>



</body>
</html>
