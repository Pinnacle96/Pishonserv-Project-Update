<?php
// ✅ CSRF token setup
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
if (!function_exists('csrf_token_input')) {
    function csrf_token_input() {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token']) . '">';
    }
}

// ✅ Property types array
$property_type = [ 
    'buy' => ['apartment','office','event_center','hotel','house','villa','condo','townhouse','duplex','penthouse','studio','bungalow','commercial','warehouse','retail','land','farmhouse','mixed_use'],
    'rent' => ['apartment', 'office', 'house', 'duplex', 'studio', 'bungalow'],
    'shortlet' => ['apartment', 'villa', 'studio', 'duplex', 'short_stay'], 
    'hotel' => [
        'hotel', 'resort', 'motel', 'guest_house', 'bed_and_breakfast', 'boutique_hotel',
        'apartment_hotel', 'hostel', 'lodge', 'villa', 'chalet', 'homestay',
        'capsule_hotel', 'inn', 'farm_stay'
    ]
];


?>

<div class="mt-6">
    <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-200">Add New Property</h2>
    <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="POST" enctype="multipart/form-data" class="bg-white dark:bg-gray-800 mt-6 p-6 rounded shadow-md">

        <?php echo csrf_token_input(); ?>
        <input type="hidden" name="MAX_FILE_SIZE" value="20971520">

        <!-- Basic Fields -->
        <div class="mb-4">
            <input type="hidden" name="property_code" value="">
            <label class="block" id="title-label">Title</label>
            <input type="text" name="title" id="title-input" required class="w-full p-3 border rounded mt-1" placeholder="e.g., Luxurious Apartment">
        </div>
       
        <div class="mb-4">
            <label class="block">Location</label>
            <input type="text" name="location" required class="w-full p-3 border rounded mt-1">
        </div>

        <div class="mb-4">
            <label class="block">Listing Type</label>
            <select name="listing_type" id="listing_type" required class="w-full p-3 border rounded mt-1">
                <option value="">Select</option>
                <option value="for_sale">For Sale</option>
                <option value="for_rent">For Rent</option>
                <option value="short_let">Short Let</option>
                <option value="hotel">Hotel</option>
            </select>
        </div>

        <div class="mb-4">
            <label class="block">Price (₦)</label>
            <input type="number" id="price" name="price" required class="w-full p-3 border rounded mt-1" placeholder="e.g., 5000000">
        </div>

        <div class="mb-4">
            <label class="block">Property Type</label>
            <select name="type" id="property_type" required class="w-full p-3 border rounded mt-1">
                <option value="">Select</option>
            </select>
        </div>

        <!-- Amenities -->
        <div class="mb-4">
            <label class="block">Amenities</label>
            <div class="flex flex-wrap">
                <hr class='my-6 w-full'>
                <?php
                $amenities = ["Pool","Gym","Parking","Security","Garden","Elevator","Balcony","CCTV","Internet",
                              "Air Conditioning","Fireplace","Washer/Dryer","Generator","Solar Power","Borehole Water",
                              "Playground","Clubhouse","Tennis Court","Sauna"];
                foreach ($amenities as $amenity) {
                    echo "<label class='w-1/3 p-2'><input type='checkbox' name='amenities[]' value='$amenity'> $amenity</label>";
                }
                ?>
            </div>
        </div>

        <!-- Dynamic Fields -->
        <div class="mb-4">
            <label class="block">Furnishing Status</label>
            <select name="furnishing_status" class="w-full p-3 border rounded mt-1">
                <option value="">Select</option>
                <option value="furnished">Furnished</option>
                <option value="semi_furnished">Semi-Furnished</option>
                <option value="unfurnished">Unfurnished</option>
            </select>
        </div>

        <div class="mb-4">
            <label class="block">Property Condition</label>
            <select name="property_condition" class="w-full p-3 border rounded mt-1">
                <option value="">Select</option>
                <option value="new">New</option>
                <option value="fairly_used">Fairly Used</option>
                <option value="renovated">Renovated</option>
            </select>
        </div>

        <div class="mb-4">
            <label class="block">Bedrooms</label>
            <input type="number" name="bedrooms" required min="0" class="w-full p-3 border rounded mt-1" placeholder="e.g., 3">
        </div>

        <div class="mb-4">
            <label class="block">Bathrooms</label>
            <input type="number" name="bathrooms" required min="0" class="w-full p-3 border rounded mt-1" placeholder="e.g., 2">
        </div>

        <div class="mb-4">
            <label class="block">Size (sqft or acres)</label>
            <input type="text" name="size" required class="w-full p-3 border rounded mt-1" placeholder="e.g., 2000 sqft or 5 acres">
        </div>

        <div class="mb-4">
            <label class="block">Garage Spaces</label>
            <input type="number" name="garage" required min="0" class="w-full p-3 border rounded mt-1" placeholder="e.g., 1">
        </div>

        <div class="mb-4">
            <label class="block">Description</label>
            <textarea name="description" required minlength="20" class="w-full p-3 border rounded mt-1" placeholder="Provide a detailed description of the property..." rows="4"></textarea>
        </div>

        <div class="mb-4">
            <label class="block">Upload Property Images</label>
            <input type="file" name="images[]" id="images" multiple required accept="image/*" class="w-full p-3 border rounded mt-1">
            <small class="text-gray-500">Max 7 images</small>
            <div id="image-preview" class="mt-2 flex flex-wrap gap-2"></div>
        </div>

        <div id="dynamic-fields"></div>

        <button type="submit" class="bg-[#F4A124] text-white w-full py-3 rounded hover:bg-[#d88b1c] mt-4">
            Add Property
        </button>
    </form>
</div>

<script>
    const propertyTypes = <?= json_encode($property_type) ?>;

    document.addEventListener("DOMContentLoaded", function () {
        const listingType = document.getElementById("listing_type");
        const propertyTypeSelect = document.getElementById("property_type");
        const priceInput = document.getElementById("price");
        const titleLabel = document.getElementById("title-label");
        const titleInput = document.getElementById("title-input");

        const fields = {
            furnishing: document.querySelector("select[name='furnishing_status']").closest(".mb-4"),
            condition: document.querySelector("select[name='property_condition']").closest(".mb-4"),
            bedrooms: document.querySelector("input[name='bedrooms']").closest(".mb-4"),
            bathrooms: document.querySelector("input[name='bathrooms']").closest(".mb-4"),
            size: document.querySelector("input[name='size']").closest(".mb-4"),
            garage: document.querySelector("input[name='garage']").closest(".mb-4")
        };

        function toggleField(fieldWrapper, visible, required = false) {
            if (!fieldWrapper) return;
            fieldWrapper.style.display = visible ? "" : "none";
            const input = fieldWrapper.querySelector("input, select, textarea");
            if (input) {
                if (visible && required) {
                    input.setAttribute("required", "required");
                } else {
                    input.removeAttribute("required");
                }
            }
        }

        function updatePropertyTypes(key) {
            propertyTypeSelect.innerHTML = "<option value=''>Select</option>";
            if (propertyTypes[key]) {
                propertyTypes[key].forEach(function(pt) {
                    const opt = document.createElement("option");
                    opt.value = pt;
                    opt.textContent = pt.replace(/_/g, " ").replace(/\b\w/g, l => l.toUpperCase());
                    propertyTypeSelect.appendChild(opt);
                });
            }
        }

        function updateDynamicFields(type) {
            const container = document.getElementById("dynamic-fields");
            let html = "";

            if (type === "for_sale" || type === "for_rent") {
                html += `
                <div class='mb-4'><label>Maintenance Fee (₦)</label><input type='number' name='maintenance_fee' class='w-full p-3 border rounded mt-1'></div>
                <div class='mb-4'><label>Agent Fee (₦)</label><input type='number' id='agent_fee' name='agent_fee' class='w-full p-3 border rounded mt-1' readonly></div>
                <div class='mb-4'><label>Caution Fee (₦)</label><input type='number' id='caution_fee' name='caution_fee' class='w-full p-3 border rounded mt-1' readonly></div>`;
            }

            if (type === "for_rent" || type === "short_let" || type === "hotel") {
                html += `<div class='mb-4'><label>Price Frequency</label>
                    <select name='price_frequency' class='w-full p-3 border rounded mt-1' required>
                        <option value=''>Select</option>
                        <option value='per_day'>Per Day</option>
                        <option value='per_night'>Per Night</option>
                        <option value='per_month'>Per Month</option>
                        <option value='per_annum'>Per Annum</option>
                    </select></div>`;
            }

            if (type === "short_let") {
                html += `
                <div class='mb-4'><label>Minimum Stay (nights)</label><input type='number' name='minimum_stay' required class='w-full p-3 border rounded mt-1'></div>
                <div class='mb-4'><label>Check-in Time</label><input type='time' name='checkin_time' required class='w-full p-3 border rounded mt-1'></div>
                <div class='mb-4'><label>Check-out Time</label><input type='time' name='checkout_time' required class='w-full p-3 border rounded mt-1'></div>`;
            }

            if (type === "hotel") {
                html += `
                <div class='mb-4'><label>Room Type</label><input type='text' name='room_type' required class='w-full p-3 border rounded mt-1'></div>
                <div class='mb-4'><label>Star Rating</label>
                    <select name='star_rating' required class='w-full p-3 border rounded mt-1'>
                        <option value=''>Select</option>
                        <option value='1'>1 Star</option>
                        <option value='2'>2 Stars</option>
                        <option value='3'>3 Stars</option>
                        <option value='4'>4 Stars</option>
                        <option value='5'>5 Stars</option>
                    </select></div>
                <div class='mb-4'><label>Check-in Time</label><input type='time' name='checkin_time' required class='w-full p-3 border rounded mt-1'></div>
                <div class='mb-4'><label>Check-out Time</label><input type='time' name='checkout_time' required class='w-full p-3 border rounded mt-1'></div>
                <div class='mb-4'><label>Policies</label><textarea name='policies' class='w-full p-3 border rounded mt-1' required></textarea></div>`;
            }

            container.innerHTML = html;
        }

        listingType.addEventListener("change", function () {
            let propertyKey = "";
            if (this.value === "for_sale") propertyKey = "buy";
            if (this.value === "for_rent") propertyKey = "rent";
            if (this.value === "short_let") propertyKey = "shortlet";
            if (this.value === "hotel") propertyKey = "hotel";

            updatePropertyTypes(propertyKey);
            updateDynamicFields(this.value);

            titleLabel.textContent = (this.value === "hotel") ? "Hotel Name" : "Title";
            titleInput.placeholder = (this.value === "hotel") ? "e.g., Sheraton Lagos Hotel" : "e.g., Luxurious Apartment";

            toggleField(fields.furnishing, this.value !== "hotel", true);
            toggleField(fields.condition, this.value !== "hotel", true);
            toggleField(fields.bedrooms, this.value !== "hotel", true);
            toggleField(fields.bathrooms, this.value !== "hotel", true);
            toggleField(fields.size, this.value === "for_sale" || this.value === "for_rent", true);
            toggleField(fields.garage, this.value !== "hotel", true);
        });

        priceInput.addEventListener("input", function () {
            if (["for_sale", "for_rent"].includes(listingType.value)) {
                const price = parseFloat(priceInput.value) || 0;
                const agentFee = document.getElementById("agent_fee");
                const cautionFee = document.getElementById("caution_fee");
                if (agentFee) agentFee.value = (price * 0.1).toFixed(2);
                if (cautionFee) cautionFee.value = (price * 0.1).toFixed(2);
            }
        });

        document.getElementById('images').addEventListener('change', function (e) {
            const preview = document.getElementById('image-preview');
            preview.innerHTML = '';
            const files = e.target.files;
            if (files.length > 7) {
                alert('You can upload a maximum of 7 images.');
                e.target.value = '';
                return;
            }
            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                if (!file.type.startsWith('image/')) continue;
                const reader = new FileReader();
                reader.onload = function (event) {
                    const img = document.createElement('img');
                    img.src = event.target.result;
                    img.classList.add('w-20', 'h-20', 'object-cover', 'rounded', 'border', 'border-gray-300');
                    preview.appendChild(img);
                };
                reader.readAsDataURL(file);
            }
        });
    });
    
    document.addEventListener("DOMContentLoaded", function () {
    const form = document.querySelector("form");
    const description = document.querySelector("textarea[name='description']");

    form.addEventListener("submit", function (e) {
        const text = description.value;

        // Regex patterns
        const phonePattern = /\+?\d{2,4}?[-.\s]?\(?\d{2,4}\)?[-.\s]?\d{3,4}[-.\s]?\d{3,4}/g;
        const addressPattern = /\b(street|st\.|road|rd\.|avenue|ave|lane|ln|close|crescent|house|building|block)\b/ig;

        let blockedMatch = null;

        if (phonePattern.test(text)) {
            blockedMatch = text.match(phonePattern)[0];
        } else if (addressPattern.test(text)) {
            blockedMatch = text.match(addressPattern)[0];
        }

        if (blockedMatch) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: '❌ Not Allowed',
                html: `Please remove <b>"${blockedMatch}"</b> from the description before submitting.`
            });
            return false;
        }
    });
});

</script>
