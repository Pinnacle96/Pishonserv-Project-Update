<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '../logs/php_errors.log');

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '../logs/php_errors.log');

include '../includes/db_connect.php';
include '../includes/zoho_functions.php';

$log_prefix = date('Y-m-d H:i:s') . " [Zoho Sync Script] ";
error_log($log_prefix . "Script started for session: " . session_id());
include '../includes/zoho_functions.php';

$log_prefix = date('Y-m-d H:i:s') . " [Zoho Sync Script] ";
error_log($log_prefix . "Script started for session: " . session_id());

// Ensure superadmin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'superadmin') {
    error_log($log_prefix . "Unauthorized access: user_id=" . ($_SESSION['user_id'] ?? 'unset') . ", role=" . ($_SESSION['role'] ?? 'unset'));
    die("Error: Unauthorized access.");
}
error_log($log_prefix . "Superadmin {$_SESSION['user_id']} authorized");

// Get action (approve or reject) and property ID
$action = $_GET['action'] ?? null;
$property_id_to_sync = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$action || $property_id_to_sync <= 0) {
    error_log($log_prefix . "Invalid or missing action/property ID");
    die("Error: Invalid request.");
}

$sync_success = true;
$error_messages = [];

// Check if zoho_property_id or zoho_deal_id exists
$columns_query = "SHOW COLUMNS FROM properties";
$columns_result = $conn->query($columns_query);
$has_zoho_property_id = false;
$has_zoho_deal_id = false;
while ($column = $columns_result->fetch_assoc()) {
    if ($column['Field'] === 'zoho_property_id') $has_zoho_property_id = true;
    if ($column['Field'] === 'zoho_deal_id') $has_zoho_deal_id = true;
}

// Fetch the property
$propertyQuery = "SELECT p.id, p.title, p.price, p.location, p.listing_type, p.status, p.type, 
                         p.bedrooms, p.bathrooms, p.size, p.description, p.garage, p.owner_id, 
                         u.zoho_lead_id, p.zoho_product_id" .
    ($has_zoho_deal_id ? ", p.zoho_deal_id" : ($has_zoho_property_id ? ", p.zoho_property_id" : "")) . "
                  FROM properties p
                  JOIN users u ON p.owner_id = u.id
                  WHERE p.id = $property_id_to_sync";

$propertyResult = $conn->query($propertyQuery);

if ($propertyResult->num_rows == 0) {
    die("Error: Property not found.");
}

$property = $propertyResult->fetch_assoc();
$property_id = $property['id'];
$title = $property['title'];
error_log($log_prefix . "Processing property: $title (ID: $property_id)");

$conn->begin_transaction();

try {
    if ($action === 'approve') {
        // Approve the property
        $stmt = $conn->prepare("UPDATE properties SET admin_approved = 1, status = 'available' WHERE id = ?");
        $stmt->bind_param("i", $property_id);
        $stmt->execute();
        $stmt->close();

        error_log($log_prefix . "Property approved: $title (ID: $property_id)");

        $ownStmt = $conn->prepare('SELECT owner_id FROM properties WHERE id = ?');
        $ownStmt->bind_param('i', $property_id);
        $ownStmt->execute();
        $ownRes = $ownStmt->get_result()->fetch_assoc();
        $ownStmt->close();
        $ownerId = (int)($ownRes['owner_id'] ?? 0);
        if ($ownerId > 0) {
            $refCols = $conn->query("SHOW COLUMNS FROM users");
            $hasReferrer = false;
            while ($rc = $refCols->fetch_assoc()) { if ($rc['Field'] === 'referrer_user_id') { $hasReferrer = true; break; } }
            if ($hasReferrer) {
                $ru = $conn->prepare('SELECT referrer_user_id FROM users WHERE id = ?');
                $ru->bind_param('i', $ownerId);
                $ru->execute();
                $refRow = $ru->get_result()->fetch_assoc();
                $ru->close();
                $referrerId = (int)($refRow['referrer_user_id'] ?? 0);
                if ($referrerId > 0) {
                    $cnt = $conn->prepare('SELECT COUNT(*) AS c FROM properties WHERE owner_id = ? AND admin_approved = 1');
                    $cnt->bind_param('i', $ownerId);
                    $cnt->execute();
                    $cRow = $cnt->get_result()->fetch_assoc();
                    $cnt->close();
                    if ((int)($cRow['c'] ?? 0) === 1) {
                        $conn->query("CREATE TABLE IF NOT EXISTS referrals (\n                            id INT AUTO_INCREMENT PRIMARY KEY,\n                            referrer_user_id INT NOT NULL,\n                            referred_user_id INT NOT NULL,\n                            context ENUM('customer','agent') NOT NULL,\n                            status ENUM('pending','qualified','rejected') NOT NULL DEFAULT 'pending',\n                            reward_amount DECIMAL(12,2) DEFAULT 0,\n                            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,\n                            qualified_at DATETIME NULL,\n                            UNIQUE KEY unique_ref (referrer_user_id, referred_user_id, context),\n                            INDEX idx_referred (referred_user_id)\n                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                        $sel = $conn->prepare("SELECT id, status FROM referrals WHERE referrer_user_id=? AND referred_user_id=? AND context='agent' LIMIT 1");
                        $sel->bind_param('ii', $referrerId, $ownerId);
                        $sel->execute();
                        $ref = $sel->get_result()->fetch_assoc();
                        $sel->close();
                        if (!$ref) {
                            $ins = $conn->prepare("INSERT INTO referrals (referrer_user_id, referred_user_id, context, status) VALUES (?,?, 'agent', 'pending')");
                            $ins->bind_param('ii', $referrerId, $ownerId);
                            $ins->execute();
                            $ins->close();
                        }
                        $upd = $conn->prepare("UPDATE referrals SET status='qualified', reward_amount=?, qualified_at=NOW() WHERE referrer_user_id=? AND referred_user_id=? AND context='agent' AND status!='qualified'");
                        $reward = REFERRAL_AGENT_SIGNUP_REWARD_NGN;
                        $upd->bind_param('dii', $reward, $referrerId, $ownerId);
                        $upd->execute();
                        $upd->close();
                        $w = $conn->prepare('SELECT id FROM wallets WHERE user_id = ?');
                        $w->bind_param('i', $referrerId);
                        $w->execute();
                        $wx = $w->get_result()->num_rows > 0; $w->close();
                        if ($wx) { $wu = $conn->prepare('UPDATE wallets SET balance = balance + ? WHERE user_id = ?'); $wu->bind_param('di', $reward, $referrerId); $wu->execute(); $wu->close(); }
                        else { $wi = $conn->prepare('INSERT INTO wallets (user_id, balance) VALUES (?, ?)'); $wi->bind_param('id', $referrerId, $reward); $wi->execute(); $wi->close(); }
                        $td = $conn->prepare("INSERT INTO transactions (user_id, amount, transaction_type, type, status, description) VALUES (?, ?, 'referral_bonus', 'credit', 'completed', ?) ");
                        $desc = 'Agent referral bonus for first approved property';
                        $td->bind_param('ids', $referrerId, $reward, $desc);
                        $td->execute();
                        $td->close();
                    }
                }
            }
        }

        // Sync to Zoho
        $deal_id = $has_zoho_deal_id ? $property['zoho_deal_id'] : ($has_zoho_property_id ? $property['zoho_property_id'] : null);
        if (!$property['zoho_product_id'] || !$deal_id) {
            if (!$property['zoho_lead_id']) {
                throw new Exception("Owner not synced to Zoho (no zoho_lead_id)");
            }

            $success = createZohoProperty(
                $property['title'],
                $property['price'],
                $property['location'],
                $property['listing_type'],
                $property['status'],
                $property['type'],
                $property['bedrooms'],
                $property['bathrooms'],
                $property['size'],
                $property['description'],
                $property['garage'],
                $property['zoho_lead_id'],
                $property['owner_id'],
                $property['id'],
                empty($property['zoho_product_id']), // create_product flag
                empty($deal_id)                      // create_deal flag
            );


            if (!$success) {
                throw new Exception("Zoho sync failed for property $title (ID: $property_id)");
            }
            error_log($log_prefix . "Zoho sync successful for $title (ID: $property_id)");
        } else {
            error_log($log_prefix . "Property already synced to Zoho: Product and Deal exist.");
        }
    } elseif ($action === 'reject') {
        // Reject the property
        $stmt = $conn->prepare("UPDATE properties SET admin_approved = 2 WHERE id = ?");
        $stmt->bind_param("i", $property_id);
        $stmt->execute();
        $stmt->close();

        error_log($log_prefix . "Property rejected: $title (ID: $property_id)");
        // No Zoho syncing when rejected
    } else {
        throw new Exception("Invalid action provided.");
    }

    $conn->commit();
} catch (Exception $e) {
    $conn->rollback();
    $sync_success = false;
    $error_messages[] = "❌ ERROR: " . $e->getMessage();
    error_log($log_prefix . "Transaction error: " . $e->getMessage());
}

$conn->close();
error_log($log_prefix . "Script completed. Success=" . ($sync_success ? 'true' : 'false'));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Zoho Property Process</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>
    <script>
    <?php if ($sync_success): ?>
    Swal.fire({
        title: "Success ✅",
        text: "Property <?php echo htmlspecialchars(ucfirst($action)); ?> completed successfully.",
        icon: "success",
        timer: 3000,
        showConfirmButton: false
    }).then(() => {
        window.location.href = "admin_properties.php"; // Redirect to property list
    });
    <?php else: ?>
    Swal.fire({
        title: "Completed with Errors ⚠️",
        html: "<?php echo implode('<br>', array_map('htmlspecialchars', $error_messages)); ?>",
        icon: "warning",
        timer: 5000,
        showConfirmButton: true
    }).then(() => {
        window.location.href = "admin_properties.php"; // Redirect back
    });
    <?php endif; ?>
    </script>
</body>

</html>
