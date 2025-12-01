<?php
// In production, set these via real environment variables
return [
  'db' => [
    'host' => getenv('DB_HOST') ?: '127.0.0.1',
    'port' => (int)(getenv('DB_PORT') ?: 3306),
    'name' => getenv('DB_NAME') ?: 'u561302917_Pishonserv',
    'user' => getenv('DB_USER') ?: 'root',
    'pass' => getenv('DB_PASS') ?: '',
    'charset' => 'utf8mb4',
  ],
  'allowed_origins' => [],
  'log_dir' => __DIR__ . '/logs',
  'log_file' => __DIR__ . '/logs/app.log',
  'whatsapp' => [
    'token' => getenv('WHATSAPP_TOKEN') ?: 'EAAQHdSVLv5EBQA0PR6o1Yg4YQ1bVkvzvGkenkXnELuCoKZAynpo6oyhO3xIv6gUxafnLsOAJVfZBoPHLiIYDjwsa46cPSlQSOQ64pVHgcSeD1KtstmrXDHyhwrCgfxgOr2cwyv5q7Cs6dSAzBZC4MBOLIHR0gXX4QfD0V8RFZCtwNCVjteppRW5G4mVk34ljHjcxGvlZCA29VzHZAfQ0ZBQwpvGbQEmKJniqvwBQ3cGG1eqtPrwJzsuZBZBFnu7lmBZBLIkn5eJYYFtEM9w50IJbZCBj6B3',
    'phone_id' => getenv('WHATSAPP_PHONE_ID') ?: '838560935998072',
    'base_url' => getenv('WHATSAPP_BASE_URL') ?: 'https://graph.facebook.com/v20.0',
    'verify_token' => getenv('WHATSAPP_VERIFY_TOKEN') ?: 'pishonserv-verify-token',
    'app_secret' => getenv('WHATSAPP_APP_SECRET') ?: '',
  ],
  'drive' => [
    'root_folder_id' => getenv('DRIVE_ROOT_FOLDER_ID') ?: '1HemhiKIBgMyM7ZrM4GK_ls5pvWKZqxBT',
    'credentials_path' => getenv('GOOGLE_DRIVE_CREDENTIALS') ?: (__DIR__ . '/json/pishonserv-api-development-67b40849289b.json'),
  ],
  'botpress_incoming_url' => getenv('BOTPRESS_INCOMING_URL') ?: 'https://api.botpress.cloud/v1/bots/c889b0dc-c8e5-4e3e-a191-158721ff6b56/events',
  'botpress_api_token' => getenv('BOTPRESS_API_TOKEN') ?: '',
];
// // ✅ Database connection live server
// $host = "localhost";
// $username = "u561302917_Pishonserv";
// $password = "Pishonserv@255";
// $database = "u561302917_Pishonserv";
