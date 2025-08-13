<?php
// 資料庫連線設定
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'foodmanager');

// OpenAI API 設定
define('OPENAI_API_KEY', '{API_KEY}');
define('OPENAI_API_URL', 'https://api.openai.com/v1/chat/completions');
define('OPENAI_MODEL', 'gpt-4o');
define('OPENAI_MAX_TOKENS', 1000);
define('OPENAI_TEMPERATURE', 0.2);

// 驗證設定
define('VALIDATION_ENABLED', true); // 是否啟用 AI 驗證
define('VALIDATION_TIMEOUT', 30); // API 請求超時時間（秒）

// 檔案上傳設定
define('UPLOAD_DIR', 'Uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif']);

// 錯誤報告設定
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
