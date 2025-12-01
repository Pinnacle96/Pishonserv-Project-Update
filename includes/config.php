<?php
// Flutterwave
define('FLW_PUBLIC_KEY', 'FLWPUBK_TEST-85fed0f30009f4844c19530687d075d8-X');
define('FLW_SECRET_KEY', 'FLWSECK_TEST-a4bc76c6c449de30c349df37da1c9c1d-X');
define('FLW_ENCRYPTION_KEY', 'FLWSECK_TESTa1af66c197c8'); // Only needed if using inline encryption method
define('FLW_REDIRECT_URL', 'http://127.0.0.1/pishonserv/payment/success_callback.php');

define('PAYSTACK_PUBLIC_KEY', 'pk_live_ae68f4dc85cf684a3f9b6f5e0d658297671bc3e8');
define('PAYSTACK_SECRET_KEY', 'sk_live_31b6d65cf771528e6f35f78ed1ac0988b98af88e');
define('PAYSTACK_SPLIT_CODE', 'your_split_code_here');
define('LOCATIONIQ_API_KEY', 'pk.5a0b494acd70410c3f14bed0c34b778a');

define('BACKFILL_SECRET', '769e2e5acce7b957428116595d6c554f5a1c58644f54e27f47d4def8a9925662');
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USER', 'pishonserv@gmail.com');
define('SMTP_PASS', 'ikfx ghpt zrxl xfzv');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls');
define('FROM_EMAIL', 'pishonserv@gmail.com');
define('FROM_NAME', 'PISHONSERV');

define('REFERRAL_MIN_BOOKING_NGN', 10000);
define('REFERRAL_CUSTOMER_REWARD_NGN', 1000);
define('REFERRAL_AGENT_SIGNUP_REWARD_NGN', 5000);
define('WHATSAPP_TOKEN', getenv('WHATSAPP_TOKEN') ?: 'EAAQHdSVLv5EBQA0PR6o1Yg4YQ1bVkvzvGkenkXnELuCoKZAynpo6oyhO3xIv6gUxafnLsOAJVfZBoPHLiIYDjwsa46cPSlQSOQ64pVHgcSeD1KtstmrXDHyhwrCgfxgOr2cwyv5q7Cs6dSAzBZC4MBOLIHR0gXX4QfD0V8RFZCtwNCVjteppRW5G4mVk34ljHjcxGvlZCA29VzHZAfQ0ZBQwpvGbQEmKJniqvwBQ3cGG1eqtPrwJzsuZBZBFnu7lmBZBLIkn5eJYYFtEM9w50IJbZCBj6B3');
define('WHATSAPP_PHONE_ID', getenv('WHATSAPP_PHONE_ID') ?: '838560935998072');
define('WHATSAPP_BASE_URL', getenv('WHATSAPP_BASE_URL') ?: 'https://graph.facebook.com/v20.0');
define('BOTPRESS_INCOMING_URL', getenv('BOTPRESS_INCOMING_URL') ?: 'https://webhook.botpress.cloud/87e3002f-4d05-4f69-a7e9-39388c4c4ecb');
define('DRIVE_ROOT_FOLDER_ID', getenv('DRIVE_ROOT_FOLDER_ID') ?: '1HemhiKIBgMyM7ZrM4GK_ls5pvWKZqxBT');
define('GOOGLE_DRIVE_CREDENTIALS', getenv('GOOGLE_DRIVE_CREDENTIALS') ?: __DIR__ . '/../api/json/pishonserv-api-development-67b40849289b.json');
define('WHATSAPP_VERIFY_TOKEN', getenv('WHATSAPP_VERIFY_TOKEN') ?: 'pishonserv-verify-token');
define('WHATSAPP_APP_SECRET', getenv('WHATSAPP_APP_SECRET') ?: '');
define('BOTPRESS_API_TOKEN', getenv('BOTPRESS_API_TOKEN') ?: '');
