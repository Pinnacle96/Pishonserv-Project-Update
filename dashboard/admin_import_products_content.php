<div class="mt-6 max-w-4xl mx-auto bg-white dark:bg-gray-800 p-6 rounded shadow-md">
    <h1 class="text-2xl font-bold mb-4">Import Products from WooCommerce CSV</h1>

    <?php if ($success): ?>
        <div class="bg-green-100 text-green-800 p-4 rounded mb-4">
            <?php foreach ($success as $msg) echo "<p>$msg</p>"; ?>
        </div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="bg-red-100 text-red-800 p-4 rounded mb-4">
            <?php foreach ($errors as $err) echo "<p>$err</p>"; ?>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <label class="block mb-2 font-semibold">Upload WooCommerce Export (.csv):</label>
        <input type="file" name="excel_file" accept=".csv,.xls,.xlsx" required class="mb-4 block w-full p-2 border rounded">
        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">Import Products</button>
    </form>

    <div class="mt-6 text-sm text-gray-600">
        <p><strong>Columns Supported:</strong> ID, Name, SKU, Description, Price, Categories, Tags, Images, Meta Fields, and Dimensions</p>
        <p>🧠 Extended metadata and attributes are stored in a separate table for flexibility.</p>
    </div>
</div>
