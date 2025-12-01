<?php
// ----------------------------------------------------
// !!! WARNING: BACK UP YOUR DATABASE BEFORE RUNNING THIS SCRIPT !!!
// This script will modify your 'products' table.
// ----------------------------------------------------

// Include your database connection file
include 'includes/db_connect.php'; // Adjust path if necessary

// Set script to run indefinitely for large datasets
set_time_limit(0);

echo "<h1>Description Normalization Script</h1>";
echo "<p>Starting the process of converting HTML descriptions and cleaning whitespace to plain text.</p>";
echo "<pre>"; // Use <pre> tag for monospaced, formatted output

$updated_count = 0;
$skipped_count = 0;
$error_count = 0;

// Select products that might have descriptions that need cleaning
$query = "SELECT id, description FROM products WHERE description IS NOT NULL AND description != ''";
$result = $conn->query($query);

if (!$result) {
    die("Error selecting products: " . $conn->error);
}

echo "Found " . $result->num_rows . " products to check.\n\n";

if ($result->num_rows > 0) {
    while ($product = $result->fetch_assoc()) {
        $product_id = $product['id'];
        $original_description = $product['description'];

        // Use a powerful regex to replace all whitespace with a single space
        $cleaned_description = preg_replace('/\s+/', ' ', $original_description);
        
        // Strip any remaining HTML tags (if any were missed) and trim the text
        $cleaned_description = trim(strip_tags($cleaned_description));

        // Check if the description was actually changed before updating
        if ($cleaned_description !== trim(strip_tags($original_description))) {
            $update_stmt = $conn->prepare("UPDATE products SET description = ? WHERE id = ?");
            if ($update_stmt === false) {
                echo "  -> <span style='color: red;'>ERROR: Statement preparation failed for product ID {$product_id}: " . $conn->error . "</span>\n";
                $error_count++;
                continue;
            }
            $update_stmt->bind_param("si", $cleaned_description, $product_id);

            if ($update_stmt->execute()) {
                echo "  -> Product ID {$product_id}: Description updated.\n";
                $updated_count++;
            } else {
                echo "  -> <span style='color: red;'>ERROR: Update failed for product ID {$product_id}: " . $update_stmt->error . "</span>\n";
                $error_count++;
            }
            $update_stmt->close();
        } else {
            echo "  -> Product ID {$product_id}: No significant changes, skipping.\n";
            $skipped_count++;
        }
    }
} else {
    echo "No products with descriptions found to process.\n";
}

echo "</pre>";
echo "<hr>";
echo "<h3>Normalization Summary</h3>";
echo "<p>Products with descriptions updated: <strong>" . $updated_count . "</strong></p>";
echo "<p>Products skipped (already clean): <strong>" . $skipped_count . "</strong></p>";
echo "<p>Errors encountered: <strong style='color: red;'>" . $error_count . "</strong></p>";

$conn->close();
?>