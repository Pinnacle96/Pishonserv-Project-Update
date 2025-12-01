<?php
include 'db_connect.php';
include 'zoho_config.php';

// Zoho Access Token Management
function getZohoAccessToken()
{
    global $conn;

    $log_dir = __DIR__ . '/../logs';
    $log_file = $log_dir . '/zoho_debug.log';

    // Ensure log directory exists
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0777, true);
    }

    // Step 3: Fetch latest tokens
    $stmt = $conn->prepare("SELECT access_token, refresh_token FROM zoho_tokens ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    $result = $stmt->get_result();
    $token_data = $result->fetch_assoc();

    if (!$token_data) {
        error_log("🔴 No Zoho Access Token Found.\n", 3, $log_file);
        return false;
    }

    $access_token = $token_data['access_token'];
    $refresh_token = $token_data['refresh_token'];

    // Step 4: Test if token is still valid
    $headers = ["Authorization: Zoho-oauthtoken $access_token"];
    $test_url = "https://www.zohoapis.com/crm/v2/Leads?per_page=1";

    $ch = curl_init($test_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // for localhost SSL bypass
    $test_response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200 && !isset(json_decode($test_response, true)['code'])) {
        return $access_token;
    }

    // Step 5: Attempt token refresh
    $refresh_url = "https://accounts.zoho.com/oauth/v2/token";
    $refresh_data = [
        'client_id' => ZOHO_CLIENT_ID,
        'client_secret' => ZOHO_CLIENT_SECRET,
        'refresh_token' => $refresh_token,
        'grant_type' => 'refresh_token'
    ];

    $ch = curl_init($refresh_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($refresh_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/x-www-form-urlencoded"]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if (!$response) {
        error_log("🔴 CURL ERROR during token refresh: $curl_error\n", 3, $log_file);
        return false;
    }

    $new_token_data = json_decode($response, true);

    if (!isset($new_token_data['access_token'])) {
        error_log("🔴 Token refresh failed.\nResponse JSON:\n" . print_r($new_token_data, true) . "\nRaw Response:\n$response\n", 3, $log_file);
        return false;
    }

    // Step 6: Save new token to DB
    $new_access_token = $new_token_data['access_token'];
    $stmt = $conn->prepare("UPDATE zoho_tokens SET access_token = ? WHERE refresh_token = ?");
    $stmt->bind_param("ss", $new_access_token, $refresh_token);
    $stmt->execute();

    error_log("✅ Token refreshed successfully.\n", 3, $log_file);
    return $new_access_token;
}

function createZohoLead($name, $lname, $email, $phone, $role)
{
    global $conn;

    $access_token = getZohoAccessToken();
    $zoho_url = "https://www.zohoapis.com/crm/v2/Leads";

    $user_type_field = "User_Type"; // Replace if API name differs

    $lead_data = [
        "data" => [[
            "First_Name"   => $name,
            "Last_Name"    => $lname,
            "Email"        => $email,
            "Phone"        => $phone,
            "Lead_Source"  => "Pishonserv",
            $user_type_field => ucfirst($role),
            "Description"  => "New user registration – Role: " . ucfirst($role)
        ]]
    ];

    $headers = [
        "Authorization: Zoho-oauthtoken {$access_token}",
        "Content-Type: application/json"
    ];

    $ch = curl_init($zoho_url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_POSTFIELDS     => json_encode($lead_data)
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $log_path = __DIR__ . '/../logs/zoho_debug.log';

    $log_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'request'   => $lead_data,
        'http_code' => $http_code,
        'response'  => json_decode($response, true)
    ];
    file_put_contents($log_path, print_r($log_entry, true) . PHP_EOL, FILE_APPEND);

    if (!$response) {
        die("Error: No response from Zoho API. HTTP Code: {$http_code}");
    }

    $response_data = json_decode($response, true);

    if (
        isset($response_data['data'][0]['code']) &&
        $response_data['data'][0]['code'] === "SUCCESS"
    ) {
        $zoho_lead_id = $response_data['data'][0]['details']['id'];

        // ✅ Update zoho_lead_id
        $stmt = $conn->prepare("UPDATE users SET zoho_lead_id = ? WHERE email = ?");
        $stmt->bind_param("ss", $zoho_lead_id, $email);
        $stmt->execute();
        $stmt->close();

        // ✅ Immediately convert to contact
        $zoho_contact_id = convertZohoLeadToContact($zoho_lead_id, $email);

        if ($zoho_contact_id) {
            // Already updated in convertZohoLeadToContact()
            return $zoho_lead_id;
        } else {
            error_log("❌ Lead converted but contact creation failed for $email", 3, $log_path);
        }

        return $zoho_lead_id;
    }

    die("Zoho API Error: " . json_encode($response_data));
}

/* ===========================================================
   NEW HELPERS (Added for vendor/agent mapping to Contacts)
   =========================================================== */

function searchZohoContactByEmailOrPhone($email = null, $phone = null) {
    $access_token = getZohoAccessToken();
    $headers = [
        "Authorization: Zoho-oauthtoken $access_token",
        "Content-Type: application/json"
    ];

    // Try email first
    if (!empty($email)) {
        $url = "https://www.zohoapis.com/crm/v2/Contacts/search?email=" . urlencode($email);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        $res = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($res, true);
        if (!empty($data['data'][0]['id'])) return $data['data'][0]['id'];
    }

    // Fallback: by phone
    if (!empty($phone)) {
        $criteria = "(Phone:equals:" . preg_replace('/\s+/', '', $phone) . ")";
        $url = "https://www.zohoapis.com/crm/v2/Contacts/search?criteria=" . urlencode($criteria);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        $res = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($res, true);
        if (!empty($data['data'][0]['id'])) return $data['data'][0]['id'];
    }

    return null;
}

function createZohoContactFromUserRow($userRow) {
    $access_token = getZohoAccessToken();
    $headers = [
        "Authorization: Zoho-oauthtoken $access_token",
        "Content-Type: application/json"
    ];

    // name split best-effort
    $full = trim($userRow['name'] ?? '');
    $parts = preg_split('/\s+/', $full);
    $first = $parts[0] ?? 'Contact';
    $last  = $parts[count($parts)-1] ?? 'Contact';

    $payload = [
        "data" => [[
            "First_Name" => $first,
            "Last_Name"  => $last,
            "Email"      => $userRow['email'] ?? null,
            "Phone"      => $userRow['phone'] ?? null,
            "Description"=> "Auto-created from Pishonserv user"
        ]]
    ];

    $ch = curl_init("https://www.zohoapis.com/crm/v2/Contacts");
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($res, true);
    if ($code === 201 && isset($data['data'][0]['details']['id'])) {
        return $data['data'][0]['details']['id'];
    }
    error_log("❌ createZohoContactFromUserRow failed: $res");
    return null;
}

/**
 * Ensures we have a Zoho Contact ID for a given local user (vendor/agent).
 * 1) return users.zoho_contact_id if exists
 * 2) else if users.zoho_lead_id -> convert and store contact id
 * 3) else search Zoho by email/phone
 * 4) else create a new Contact and store id
 */
function getOrCreateZohoContactIdForUser($user_id) {
    global $conn;

    $stmt = $conn->prepare("SELECT id, name, email, phone, zoho_lead_id, zoho_contact_id FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
        error_log("❌ getOrCreateZohoContactIdForUser: user not found ($user_id)");
        return null;
    }

    if (!empty($user['zoho_contact_id'])) return $user['zoho_contact_id'];

    if (!empty($user['zoho_lead_id'])) {
        $contact_id = convertZohoLeadToContact($user['zoho_lead_id'], $user['email']);
        if (!empty($contact_id)) {
            $stmt = $conn->prepare("UPDATE users SET zoho_contact_id = ? WHERE id = ?");
            $stmt->bind_param("si", $contact_id, $user_id);
            $stmt->execute();
            $stmt->close();
            return $contact_id;
        }
    }

    $found = searchZohoContactByEmailOrPhone($user['email'] ?? null, $user['phone'] ?? null);
    if ($found) {
        $stmt = $conn->prepare("UPDATE users SET zoho_contact_id = ? WHERE id = ?");
        $stmt->bind_param("si", $found, $user_id);
        $stmt->execute();
        $stmt->close();
        return $found;
    }

    $created = createZohoContactFromUserRow($user);
    if ($created) {
        $stmt = $conn->prepare("UPDATE users SET zoho_contact_id = ? WHERE id = ?");
        $stmt->bind_param("si", $created, $user_id);
        $stmt->execute();
        $stmt->close();
        return $created;
    }

    return null;
}

/* ===========================================================
   UPDATED: createZohoProperty (maps Vendor_Contact_Name = Contact)
   =========================================================== */

function createZohoProperty($property_code, $title, $price, $location, $listing_type, $status, $type, $bedrooms, $bathrooms, $size, $description, $garage, $zoho_lead_id, $user_id)
{
    global $conn;
    $log_prefix = date('Y-m-d H:i:s') . " [Zoho Sync] ";

    if (empty($title) || !is_numeric($price) || $price < 0 || empty($location) || empty($listing_type) || empty($status) || empty($type)) {
        error_log($log_prefix . "Invalid property data: title=$title, price=$price, location=$location, listing_type=$listing_type, status=$status, type=$type\n", 3, __DIR__ . '/../logs/zoho_debug.log');
        throw new Exception("Required property fields are invalid.");
    }

    if (empty($user_id) || empty($property_code)) {
        error_log($log_prefix . "Missing IDs: user_id=$user_id, property_code=$property_code\n", 3, __DIR__ . '/../logs/zoho_debug.log');
        throw new Exception("Missing required identifiers.");
    }

    $access_token = getZohoAccessToken();
    if (!$access_token) {
        error_log($log_prefix . "❌ Failed to obtain Zoho access token\n", 3, __DIR__ . '/../logs/zoho_debug.log');
        throw new Exception("Access token retrieval failed.");
    }

    $headers = [
        "Authorization: Zoho-oauthtoken $access_token",
        "Content-Type: application/json"
    ];

    $offerTypeMap = [
        'for_sale' => 'Sale',
        'for_rent' => 'Rent',
        'short_let' => 'Short Let',
        'hotel' => 'Hotel'
    ];
    $offerType = $offerTypeMap[$listing_type] ?? 'Sale';
    $unique_title = "$title [$property_code]";
    error_log($log_prefix . "Mapped Offer Type: $offerType\n", 3, __DIR__ . '/../logs/zoho_debug.log');

    // 👇 NEW: resolve vendor agent as a CONTACT id
    $vendor_contact_id = getOrCreateZohoContactIdForUser((int)$user_id);
    if (empty($vendor_contact_id)) {
        error_log($log_prefix . "❌ Could not resolve vendor contact id for user_id=$user_id\n", 3, __DIR__ . '/../logs/zoho_debug.log');
        throw new Exception("Vendor contact lookup failed.");
    }

    // === Prepare product data (uses Vendor_Contact_Name CONTACT lookup) ===
    $product_data = [
        "data" => [[
            "Product_Name" => $unique_title,
            "Product_Code" => $property_code,
            "Unit_Price" => (float)$price,
            "Location" => $location,
            "Listing_Type" => $offerType,
            "Product_Active" => true,
            "Property_Type" => $type,
            "Bedrooms" => $bedrooms !== null ? (int)$bedrooms : null,
            "Bathrooms" => $bathrooms !== null ? (int)$bathrooms : null,
            "Size_e_g_sqft_acres" => $size !== null ? (string)$size : null,
            "Description" => $description ?: null,
            "Garage_Spaces" => $garage !== null ? (int)$garage : null,

            // ✅ Correct lookup to show vendor/agent on Product
            "Vendor_Contact_Name" => ["id" => $vendor_contact_id]

            // Optionally mirror:
            // "Property_Owner" => ["id" => $vendor_contact_id],
            // Optionally assign Zoho User owner:
            // "Owner" => ["id" => "ZOHO_INTERNAL_USER_ID"]
        ]]
    ];

    // === Check if product already exists ===
    $search_url = "https://www.zohoapis.com/crm/v2/Products/search?criteria=(Product_Code:equals:" . urlencode($property_code) . ")";
    $ch = curl_init($search_url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    $search_response = curl_exec($ch);
    curl_close($ch);
    $search_result = json_decode($search_response, true);

    $existing_product_id = null;
    if (!empty($search_result['data'][0]['id'])) {
        $existing_product_id = $search_result['data'][0]['id'];
        error_log($log_prefix . "🔄 Product exists. Updating ID: $existing_product_id\n", 3, __DIR__ . '/../logs/zoho_debug.log');
    }

    // === Send product to Zoho ===
    $product_url = $existing_product_id
        ? "https://www.zohoapis.com/crm/v2/Products/$existing_product_id"
        : "https://www.zohoapis.com/crm/v2/Products";

    $http_method = $existing_product_id ? "PUT" : "POST";

    $ch = curl_init($product_url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $http_method,
        CURLOPT_POSTFIELDS => json_encode($product_data),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    $product_response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    error_log($log_prefix . "📥 Zoho Product Response ($http_method $http_code): $product_response\n", 3, __DIR__ . '/../logs/zoho_debug.log');

    $product_result = json_decode($product_response, true);
    if (!isset($product_result['data'][0]['details']['id'])) {
        throw new Exception("Product $http_method failed.");
    }

    $zoho_product_id = $product_result['data'][0]['details']['id'];
    error_log($log_prefix . "✅ Product synced. Zoho ID: $zoho_product_id\n", 3, __DIR__ . '/../logs/zoho_debug.log');

    // === Create Deal (kept behavior, but now uses a Contact id) ===
    $deal_url = "https://www.zohoapis.com/crm/v2/Deals";
    $closing_date = in_array($listing_type, ['short_let', 'hotel']) ? date("Y-m-d", strtotime("+7 days")) : date("Y-m-d", strtotime("+30 days"));

    $deal_data = [
        "data" => [[
            "Deal_Name" => $unique_title,
            "Amount" => (float)$price,
            "Stage" => ucfirst($status),
            "Type" => ucfirst($type),

            // Using vendor contact as placeholder; your real client is set in createZohoBooking()
            "Contact_Name" => ["id" => $vendor_contact_id],

            // If you have a custom vendor lookup on Deals (you use "Vendor_Contact" elsewhere):
            "Vendor_Contact" => ["id" => $vendor_contact_id],

            "Closing_Date" => $closing_date
        ]]
    ];

    $ch = curl_init($deal_url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($deal_data),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true
    ]);
    $deal_response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $deal_result = json_decode($deal_response, true);
    $zoho_deal_id = null;

    if ($http_code === 201 && isset($deal_result['data'][0]['details']['id'])) {
        $zoho_deal_id = $deal_result['data'][0]['details']['id'];
        error_log($log_prefix . "✅ Deal created. Zoho Deal ID: $zoho_deal_id\n", 3, __DIR__ . '/../logs/zoho_debug.log');
    } else {
        error_log($log_prefix . "⚠️ Deal creation failed. Response: $deal_response\n", 3, __DIR__ . '/../logs/zoho_debug.log');
    }

    // === Update Local DB ===
    $stmt = $conn->prepare("UPDATE properties SET zoho_product_id = ?, zoho_deal_id = ? WHERE property_code = ?");
    if (!$stmt) {
        error_log($log_prefix . "❌ DB prepare error: " . $conn->error . "\n", 3, __DIR__ . '/../logs/zoho_debug.log');
        throw new Exception("DB Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("sss", $zoho_product_id, $zoho_deal_id, $property_code);
    if (!$stmt->execute()) {
        $stmt->close();
        error_log($log_prefix . "❌ DB update error: " . $stmt->error . "\n", 3, __DIR__ . '/../logs/zoho_debug.log');
        throw new Exception("DB update failed: " . $stmt->error);
    }

    $stmt->close();
    error_log($log_prefix . "✅ DB updated: property_code=$property_code, product_id=$zoho_product_id, deal_id=$zoho_deal_id\n", 3, __DIR__ . '/../logs/zoho_debug.log');

    return true;
}

/* ===============================
   (Your other functions unchanged)
   =============================== */

function createZohoBooking(
    $user_id,
    $property_id,
    $booking_id, // NEW: Added booking_id to uniquely update row
    $status,
    $check_in_date,
    $check_out_date,
    $days_booked,
    $total_amount
) {
    global $conn;

    $access_token = getZohoAccessToken();

    // Fetch Client, Property, and Owner Information
    $stmt = $conn->prepare("
        SELECT 
            u.name AS client_name, 
            u.email AS client_email, 
            u.phone AS client_phone, 
            u.zoho_lead_id AS client_zoho_lead_id,
            u.zoho_contact_id AS client_zoho_contact_id,
            p.title AS property_title, 
            p.price AS property_price, 
            p.location, 
            p.type AS property_type,
            p.listing_type,
            o.name AS owner_name,
            o.zoho_lead_id AS owner_zoho_lead_id,
            o.zoho_contact_id AS owner_zoho_contact_id
        FROM bookings b
        JOIN users u ON b.user_id = u.id
        JOIN properties p ON b.property_id = p.id
        JOIN users o ON p.owner_id = o.id
        WHERE b.id = ?
    ");
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$booking) {
        error_log("❌ Booking details not found for booking ID: $booking_id");
        return false;
    }

    // Convert lead to contact if necessary
    if (empty($booking['client_zoho_contact_id']) && !empty($booking['client_zoho_lead_id'])) {
        $booking['client_zoho_contact_id'] = convertZohoLeadToContact($booking['client_zoho_lead_id'], $booking['client_email']);
    }

    if (empty($booking['owner_zoho_contact_id']) && !empty($booking['owner_zoho_lead_id'])) {
        $booking['owner_zoho_contact_id'] = convertZohoLeadToContact($booking['owner_zoho_lead_id'], $booking['owner_name']);
    }

    if (empty($booking['client_zoho_contact_id']) || empty($booking['owner_zoho_contact_id'])) {
        error_log("❌ Zoho contact ID missing after lead conversion. Booking ID: $booking_id");
        return false;
    }

    $service = match ($booking['listing_type']) {
        'for_rent' => 'Rent',
        'for_sale' => 'Buy',
        default => 'Booking',
    };

    $deal_name = $booking['client_name'] . " (Service: $service)";
    $zoho_url = "https://www.zohoapis.com/crm/v2/Deals";

    $data = [
        "data" => [[
            "Deal_Name" => $deal_name,
            "Amount" => $total_amount,
            "Stage" => ucfirst($status),
            "Contact_Name" => ["id" => $booking['client_zoho_contact_id']],
            "Vendor_Contact" => ["id" => $booking['owner_zoho_contact_id']],
            "Property_Name" => $booking['property_title'],
            "Check_in_Date" => $check_in_date,
            "Check_out_Date" => $check_out_date,
            "Booking_Status" => ucfirst($status),
            "Booking_Date" => date("Y-m-d"),
            "Type" => ucfirst($booking['property_type']),
            "Property_Type" => ucfirst($booking['property_type']),
            "Closing_Date" => date("Y-m-d", strtotime("+30 days"))
        ]]
    ];

    // 📝 DEBUG LOGGING BEFORE API CALL
    error_log("✅ Zoho Deals API URL: " . $zoho_url);
    error_log("✅ Zoho Deals API DATA: " . json_encode($data, JSON_PRETTY_PRINT));

    $headers = [
        "Authorization: Zoho-oauthtoken $access_token",
        "Content-Type: application/json"
    ];

    // Log the data being sent
    error_log("📤 Sending to Zoho: " . json_encode($data));

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $zoho_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    error_log("📥 Zoho API Response ($http_code): $response");

    $response_data = json_decode($response, true);

    if (isset($response_data['data'][0]['code']) && $response_data['data'][0]['code'] === "SUCCESS") {
        $zoho_deal_id = $response_data['data'][0]['details']['id'];

        // ✅ Save Deal ID directly to the correct booking record
        $stmt = $conn->prepare("UPDATE bookings SET zoho_deal_id = ? WHERE id = ?");
        $stmt->bind_param("si", $zoho_deal_id, $booking_id);
        $stmt->execute();
        $stmt->close();

        error_log("✅ Zoho Deal Created. Deal ID: $zoho_deal_id for booking ID: $booking_id");
        return $zoho_deal_id;
    } else {
        error_log("❌ Zoho API Error: " . json_encode($response_data));
        return false;
    }
}

function convertZohoLeadToContact($lead_id, $email)
{
    global $conn;

    $access_token = getZohoAccessToken();
    $zoho_url = "https://www.zohoapis.com/crm/v2/Leads/$lead_id/actions/convert";

    $headers = [
        "Authorization: Zoho-oauthtoken " . $access_token,
        "Content-Type: application/json"
    ];

    $body = [
        "data" => [[
            "overwrite" => true,
            "notify_lead_owner" => true,
            "notify_new_entity_owner" => true
        ]]
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $zoho_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!$response) {
        error_log("❌ No response from Zoho API during lead conversion. HTTP Code: $http_code");
        return false;
    }

    $response_data = json_decode($response, true);

    // ✅ CASE 1: Conversion successful
    if (isset($response_data['data'][0]['Contacts'])) {
        $contact_id = $response_data['data'][0]['Contacts'];

        $stmt = $conn->prepare("UPDATE users SET zoho_contact_id = ? WHERE zoho_lead_id = ?");
        $stmt->bind_param("ss", $contact_id, $lead_id);
        $stmt->execute();
        $stmt->close();

        error_log("✅ Zoho Lead Converted. Contact ID: $contact_id");
        return $contact_id;
    }

    // ✅ CASE 2: Duplicate - contact already exists
    if (
        isset($response_data['data'][0]['code']) &&
        $response_data['data'][0]['code'] === "DUPLICATE_DATA" &&
        isset($response_data['data'][0]['details']['id'])
    ) {
        $contact_id = $response_data['data'][0]['details']['id'];

        $stmt = $conn->prepare("UPDATE users SET zoho_contact_id = ? WHERE email = ?");
        $stmt->bind_param("ss", $contact_id, $email);
        $stmt->execute();
        $stmt->close();

        error_log("⚠️ Zoho Contact Already Exists. Using Existing ID: $contact_id");
        return $contact_id;
    }

    error_log("❌ Zoho Lead Conversion Failed: " . json_encode($response_data));
    return false;
}

function createZohoPayment($user_id, $property_id, $amount, $transaction_id)
{
    global $conn;

    // Fetch User & Property Info (minimal + contact/lead ids)
    $stmt = $conn->prepare("
        SELECT 
            u.id            AS user_id,
            u.zoho_contact_id,
            u.zoho_lead_id,
            p.title         AS property_title
        FROM users u
        JOIN properties p ON p.id = ?
        WHERE u.id = ?
        LIMIT 1
    ");
    $stmt->bind_param("ii", $property_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();

    if (!$data) {
        error_log("❌ Error: createZohoPayment: user/property row not found (user_id=$user_id, property_id=$property_id)");
        return false;
    }

    $property_title = $data['property_title'] ?? null;

    // ✅ Resolve a Zoho CONTACT id for the vendor/agent (user)
    // Prefer existing contact id; else convert lead; else search/create (helper).
    $zoho_contact_id = $data['zoho_contact_id'] ?? null;

    if (empty($zoho_contact_id) && !empty($data['zoho_lead_id'])) {
        $zoho_contact_id = convertZohoLeadToContact($data['zoho_lead_id'], null);
    }
    if (empty($zoho_contact_id)) {
        if (!function_exists('getOrCreateZohoContactIdForUser')) {
            error_log("❌ Error: helper getOrCreateZohoContactIdForUser not available.");
            return false;
        }
        $zoho_contact_id = getOrCreateZohoContactIdForUser((int)$data['user_id']);
    }

    if (empty($zoho_contact_id)) {
        error_log("❌ Error: Could not resolve Zoho Contact ID for user_id=$user_id. Payment not created.");
        return false;
    }

    $access_token = getZohoAccessToken();
    if (!$access_token) {
        error_log("❌ Error: No Zoho Access Token.");
        return false;
    }

    $url = "https://www.zohoapis.com/crm/v2/Payments";

    // IMPORTANT: Vendor_Contact_Name must be a CONTACT lookup (not a Lead)
    $payment_data = [
        "data" => [[
            "Amount"              => (float)$amount,
            "Payment_Status"      => "Completed",
            "Transaction_ID"      => (string)$transaction_id,
            "Vendor_Contact_Name" => ["id" => $zoho_contact_id], // ✅ CONTACT ID
            "Property_Title"      => $property_title,
            "Description"         => "Payment for $property_title - Transaction ID: $transaction_id"
        ]]
    ];

    $headers = [
        "Authorization: Zoho-oauthtoken " . $access_token,
        "Content-Type: application/json"
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payment_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    error_log("🔄 Zoho Payment Response ($http_code): " . $response);

    if (!$response) {
        error_log("❌ Error: No response from Zoho API. HTTP Code: $http_code");
        return false;
    }

    $response_data = json_decode($response, true);
    if (isset($response_data['data'][0]['code']) && $response_data['data'][0]['code'] === "SUCCESS") {
        return $response_data['data'][0]['details']['id'];
    } else {
        error_log("❌ Zoho API Error (Payments): " . json_encode($response_data));
        return false;
    }
}


function updateZohoBookingStatus($booking_id, $status)
{
    global $conn;

    $access_token = getZohoAccessToken();
    if (!$access_token) {
        error_log("❌ Error: Unable to get Zoho Access Token.");
        return false;
    }

    // 🚀 Fetch zoho_deal_id safely
    $stmt = $conn->prepare("SELECT zoho_deal_id FROM bookings WHERE id = ? AND zoho_deal_id IS NOT NULL");
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();

    if (!$data || empty($data['zoho_deal_id'])) {
        error_log("❌ Error: Zoho Deal ID missing for booking ID: $booking_id.");
        return false;
    }

    $zoho_deal_id = $data['zoho_deal_id'];
    $zoho_url = "https://www.zohoapis.com/crm/v2/Deals/{$zoho_deal_id}";

    // ✅ Prepare update data
    $update_data = [
        "data" => [[
            "Booking_Status" => ucfirst($status)  // Example: 'Confirmed'
        ]]
    ];

    $headers = [
        "Authorization: Zoho-oauthtoken {$access_token}",
        "Content-Type: application/json"
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $zoho_url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($update_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // ✅ Debugging
    error_log("🔄 Zoho API HTTP Code: $http_code");
    error_log("🔄 Zoho Booking Update Response: " . $response);

    if (!$response) {
        error_log("❌ Error: No response from Zoho API. HTTP Code: $http_code");
        return false;
    }

    $response_data = json_decode($response, true);

    if (isset($response_data['data'][0]['code']) && $response_data['data'][0]['code'] === "SUCCESS") {
        error_log("✅ Zoho Deal successfully updated for booking ID: $booking_id (Deal ID: $zoho_deal_id)");
        return true;
    } else {
        error_log("❌ Zoho API Update Failed: " . json_encode($response_data));
        return false;
    }
}

/* ===========================================================
   NEW: One-time Backfill to set Vendor_Contact_Name on Products
   =========================================================== */

/**
 * Backfill existing Zoho Products so Vendor_Contact_Name is populated.
 * It uses properties.user_id as the listing agent (fallback to owner_id if needed),
 * resolves/creates a Zoho Contact, then updates the Zoho Product.
 */
function backfillPropertyVendors()
{
    global $conn;

    $log_prefix = date('Y-m-d H:i:s') . " [Zoho Backfill] ";
    $access_token = getZohoAccessToken();
    if (!$access_token) {
        error_log($log_prefix . "❌ Failed to obtain Zoho access token\n", 3, __DIR__ . '/../logs/zoho_debug.log');
        return false;
    }

    // Get properties that already have a Zoho Product ID
    $sql = "
        SELECT id, property_code, zoho_product_id, user_id, owner_id
        FROM properties
        WHERE zoho_product_id IS NOT NULL
          AND zoho_product_id <> ''
    ";
    $rs = $conn->query($sql);
    if (!$rs) {
        error_log($log_prefix . "❌ DB query failed: " . $conn->error . "\n", 3, __DIR__ . '/../logs/zoho_debug.log');
        return false;
    }

    $headers = [
        "Authorization: Zoho-oauthtoken $access_token",
        "Content-Type: application/json"
    ];

    while ($row = $rs->fetch_assoc()) {
        $productId = $row['zoho_product_id'];
        if (!$productId) continue;

        // prefer listing user; fallback to owner
        $source_user_id = !empty($row['user_id']) ? (int)$row['user_id'] : (!empty($row['owner_id']) ? (int)$row['owner_id'] : null);
        if (!$source_user_id) {
            error_log($log_prefix . "⚠️ Skipping property {$row['property_code']} (no user/owner id)\n", 3, __DIR__ . '/../logs/zoho_debug.log');
            continue;
        }

        $vendor_contact_id = getOrCreateZohoContactIdForUser($source_user_id);
        if (!$vendor_contact_id) {
            error_log($log_prefix . "⚠️ Could not resolve contact for property {$row['property_code']} (user_id=$source_user_id)\n", 3, __DIR__ . '/../logs/zoho_debug.log');
            continue;
        }

        // Update product with Vendor_Contact_Name
        $payload = [
            "data" => [[
                "id" => $productId,
                "Vendor_Contact_Name" => ["id" => $vendor_contact_id]
            ]]
        ];

        $ch = curl_init("https://www.zohoapis.com/crm/v2/Products");
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => "PUT",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        error_log($log_prefix . "🔧 Backfill product {$row['property_code']} (ID: $productId) → contact $vendor_contact_id | HTTP $code | Res: $res\n", 3, __DIR__ . '/../logs/zoho_debug.log');
    }

    return true;
}
