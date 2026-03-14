<?php
// ============================================================
// 設定回應格式為 JSON，告訴前端回傳的資料是 JSON 格式
// ============================================================
header("Content-Type: application/json");

function loadDotEnv(string $path): void
{
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$key, $val] = explode('=', $line, 2);
        $key = trim($key, " \t\r\n\0\x0B\xEF\xBB\xBF"); // 去掉 BOM
        $val = trim($val);
        putenv("$key=$val");
        $_ENV[$key] = $val;
    }
}
loadDotEnv(__DIR__ . '/.env');

// ============================================================
// CORS 設定：允許跨來源請求（前端與後端不同 port 時必須設定）
// Access-Control-Allow-Origin  : 允許所有來源（* 代表任何網域）
// Access-Control-Allow-Methods : 允許的 HTTP 方法
// Access-Control-Allow-Headers : 允許前端帶的 header（如 JWT Token）
// ============================================================
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// ============================================================
// 處理 OPTIONS 預檢請求（Preflight）
// 瀏覽器在發送跨域請求前，會先發一個 OPTIONS 請求確認伺服器是否允許
// 直接回 200 並結束，不需要進入任何業務邏輯
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ============================================================
// 取得當前請求的 HTTP 方法（GET / POST / PUT / DELETE）
// ============================================================
$method = $_SERVER["REQUEST_METHOD"];

// ============================================================
// 引入 BookController，載入所有書籍相關的函式
// __DIR__ 代表當前檔案所在目錄，確保路徑不會因執行位置不同而出錯
// ============================================================
require_once __DIR__ . "/src/controllers/BookController.php";

// ============================================================
// 取得請求的 URL 路徑（去掉 query string）
// 例如 /api/books?id=1 → 只取 /api/books
// ============================================================
$path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);

// ============================================================
// 路由判斷：根據 HTTP method 和路徑，決定呼叫哪個 controller 函式
// ============================================================

// GET /api/books → 取得所有書籍
if ($method === "GET" && $path === "/api/books") {
    getAll();
}

// GET /api/books/{id} → 取得單筆書籍
// preg_match 用正則從路徑中抓出動態的 id
// \d+ 代表一個或多個數字，() 將抓到的值存入 $matches[1]
if ($method === "GET" && preg_match("/\/api\/books\/(\d+)/", $path, $matches)) {
    $id = $matches[1];
    getById($id);
}

// POST /api/books → 新增書籍
if ($method === "POST" && $path === "/api/books") {
    create();
}

// PUT /api/books/{id} → 更新指定書籍
if ($method === "PUT" && preg_match("/\/api\/books\/(\d+)/", $path, $matches)) {
    $id = $matches[1];
    update($id);
}

// DELETE /api/books/{id} → 刪除指定書籍
if ($method === "DELETE" && preg_match("/\/api\/books\/(\d+)/", $path, $matches)) {
    $id = $matches[1];
    delete($id);
}
