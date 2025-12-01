<link rel="stylesheet" href="https://cdn.datatables.net/1.13.5/css/jquery.dataTables.min.css">
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.5/js/jquery.dataTables.min.js"></script>

<div class="mt-6">
    <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-200">Manage Products</h2>

    <div class="bg-white dark:bg-gray-800 mt-6 p-6 rounded shadow-md overflow-x-auto">
        <h3 class="text-xl font-bold mb-4">All Product Listings</h3>

        <!--<div class="flex gap-4 mb-4">-->
        <!--    <a href="admin_add_product.php" class="bg-[#F4A124] text-white px-6 py-2 rounded hover:bg-[#d88b1c]">-->
        <!--        + Add New Product-->
        <!--    </a>-->
        <!--    <a href="admin_import_products.php" class="bg-green-600 text-white px-6 py-2 rounded hover:bg-green-700">-->
        <!--        ⇪ Import Products-->
        <!--    </a>-->
        <!--</div>-->

        <?php
        // Check if the query returned any results
        if ($result && $result->num_rows > 0) {
        ?>
        <table id="productsTable" class="min-w-[1000px] border-collapse border border-gray-300 dark:border-gray-700">
            <thead>
                <tr class="bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-gray-300">
                    <th class="p-3 border">Image</th>
                    <th class="p-3 border">Name</th>
                    <th class="p-3 border">Category</th>
                    <th class="p-3 border">Price</th>
                    <th class="p-3 border">Description</th>
                    <th class="p-3 border">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td class="p-3 border">
                       <img src="<?php echo htmlspecialchars($row['image_url'] ?? 'public/uploads/default.jpg'); ?>"
     alt="Product" class="w-32 h-20 object-cover rounded">
                    </td>
                    <td class="p-3 border">
                        <p class="font-semibold text-gray-900 dark:text-gray-200"><?php echo htmlspecialchars($row['name']); ?></p>
                        <small class="text-gray-500 dark:text-gray-400">SKU: <?php echo htmlspecialchars($row['sku']); ?></small>
                    </td>
                    <td class="p-3 border"><?php echo htmlspecialchars($row['categories'] ?? 'N/A'); ?></td>
                    <td class="p-3 border whitespace-nowrap">
                        <?php
                            // Display the price with proper formatting
                            $sale_price = $row['sale_price'];
                            $regular_price = $row['regular_price'];

                            // Check if a sale price exists
                            if (!empty($sale_price) && $sale_price < $regular_price) {
                                echo '<span class="font-bold text-green-600">₦' . number_format($sale_price, 2) . '</span><br>';
                                echo '<small class="line-through text-gray-500">₦' . number_format($regular_price, 2) . '</small>';
                            } else {
                                echo '<span class="font-bold text-gray-900 dark:text-gray-200">₦' . number_format($regular_price ?? 0, 2) . '</span>';
                            }
                        ?>
                    </td>
                    <td class="p-3 border">
                        <?php
                            // Trim the description to a more manageable length and remove HTML tags
                            $description = $row['description'] ?? '';
                            echo htmlspecialchars(mb_strimwidth(strip_tags($description), 0, 80, '...'));
                        ?>
                    </td>
                    <td class="p-3 border text-center whitespace-nowrap">
                        <a href="admin_edit_product.php?id=<?php echo htmlspecialchars($row['id']); ?>" class="text-blue-500 hover:underline">Edit</a>
                        <span class="text-gray-400"> | </span>
                        <a href="admin_delete_product.php?id=<?php echo htmlspecialchars($row['id']); ?>"
                           onclick="return confirm('Are you sure you want to delete this product? This action cannot be undone.')"
                           class="text-red-500 hover:underline">Delete</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php
        } else {
            // Display a message if no products are found
            echo '<p class="text-center text-gray-500 dark:text-gray-400 py-8">No products found. Add a new product to get started.</p>';
        }
        ?>

    </div>
</div>

<script>
    // Initialize DataTables with the specified options
    $(document).ready(function() {
        $('#productsTable').DataTable({
            paging: true,
            ordering: true,
            searching: true,
            autoWidth: false,
            columnDefs: [
                { targets: [0, 5], orderable: false } // Disable ordering on image and action columns
            ]
        });
    });
</script>