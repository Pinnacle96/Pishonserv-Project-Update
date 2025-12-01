<div class="mt-6 max-w-3xl mx-auto bg-white dark:bg-gray-800 p-6 rounded shadow-md">
    <h2 class="text-2xl font-bold mb-4 text-gray-800 dark:text-gray-200">Edit Product</h2>
    <form method="POST" enctype="multipart/form-data">
        <label class="block mb-2 font-semibold">Product Name</label>
        <input type="text" name="name" value="<?php echo htmlspecialchars($product['name']); ?>" required
            class="w-full p-2 border rounded mb-4">

        <label class="block mb-2 font-semibold">SKU</label>
        <input type="text" name="sku" value="<?php echo htmlspecialchars($product['sku']); ?>"
            class="w-full p-2 border rounded mb-4">

        <label class="block mb-2 font-semibold">Description</label>
        <textarea name="description" required class="w-full p-2 border rounded mb-4"
            rows="4"><?php echo htmlspecialchars($product['description']); ?></textarea>

        <label class="block mb-2 font-semibold">Regular Price (₦)</label>
        <input type="number" name="regular_price" step="0.01" value="<?php echo htmlspecialchars($product['regular_price']); ?>"
            class="w-full p-2 border rounded mb-4">

        <label class="block mb-2 font-semibold">Sale Price (₦)</label>
        <input type="number" name="sale_price" step="0.01" value="<?php echo htmlspecialchars($product['sale_price']); ?>"
            class="w-full p-2 border rounded mb-4">

        <label class="block mb-2 font-semibold">Category</label>
        <select name="category_path" class="w-full p-2 border rounded mb-4">
            <option value="" disabled>Select a category</option>
            <?php foreach ($all_categories as $category): ?>
                <option value="<?php echo htmlspecialchars($category['category_path']); ?>"
                    <?php echo ($category['category_path'] === $current_category_path) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($category['category_path']); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <div class="flex items-center mb-4">
            <input type="checkbox" name="published" id="published" value="1"
                <?php echo $product['published'] ? 'checked' : ''; ?>
                class="form-checkbox h-5 w-5 text-blue-600">
            <label for="published" class="ml-2 block text-sm text-gray-900 dark:text-gray-200">Published</label>
        </div>

        <div class="form-group mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2">Current Images</label>
            <div class="flex flex-wrap gap-4">
                <?php foreach ($product_images as $image): ?>
                    <?php
                        // Check if the URL is a full URL (http/https) or a local path
                        $image_path = (strpos($image['image_url'], 'http') === 0 || strpos($image['image_url'], 'https') === 0)
                                      ? htmlspecialchars($image['image_url'])
                                      : '../public/' . htmlspecialchars($image['image_url']);
                    ?>
                    <div class="relative w-40 h-40 group">
                        <img src="<?php echo $image_path; ?>" alt="Product Image" class="w-full h-full object-cover rounded-lg shadow-sm">
                        <button type="button" data-image-id="<?php echo htmlspecialchars($image['id']); ?>"
                                class="absolute top-1 right-1 bg-red-600 text-white rounded-full h-6 w-6 flex items-center justify-center text-xs opacity-75 hover:opacity-100 transition-opacity">
                            <i class="fas fa-times"></i>
                        </button>
                        <input type="hidden" name="delete_images[]" value="" data-delete-input="<?php echo htmlspecialchars($image['id']); ?>">
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="form-group mb-4">
            <label for="image_files" class="block text-gray-700 text-sm font-bold mb-2">Upload New Images</label>
            <input type="file" id="image_files" name="image_files[]" multiple
                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
        </div>

        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">Update Product</button>
    </form>
</div>

<script>
    document.querySelectorAll('[data-image-id]').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const imageId = this.getAttribute('data-image-id');
            const parent = this.closest('.group');
            parent.style.opacity = '0.5';
            parent.style.pointerEvents = 'none';
            document.querySelector(`[data-delete-input="${imageId}"]`).value = imageId;
        });
    });
</script>