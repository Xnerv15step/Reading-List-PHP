<?php
// ============================================================
// 引入資料庫連線設定，載入 getDB() 函式
// ============================================================
require_once __DIR__ . "/../config/database.php";

// ============================================================
// 取得所有書籍
// GET /api/books
// ============================================================
function getAll()
{
    $connect = getDB();

    // 不需要帶參數，直接用 query() 查詢所有書籍
    $stmt = $connect->query("SELECT * FROM books");

    // fetchAll() 取得所有結果，回傳二維陣列
    $books = $stmt->fetchAll();

    // JSON_UNESCAPED_UNICODE 讓中文正常顯示，不被轉成 \u5c0f 這種格式
    echo json_encode($books, JSON_UNESCAPED_UNICODE);
}

// ============================================================
// 取得單筆書籍
// GET /api/books/{id}
// ============================================================
function getById($id)
{
    $connect = getDB();

    // prepare + execute：帶參數的查詢，? 會被 $id 取代（防止 SQL Injection）
    $stmt = $connect->prepare("SELECT * FROM books WHERE id = ?");
    $stmt->execute([$id]);

    // fetch() 只取一筆，找不到回傳 false
    $book = $stmt->fetch();

    if ($book) {
        echo json_encode($book, JSON_UNESCAPED_UNICODE);
    } else {
        // id 不存在，回傳 404
        http_response_code(404);
        echo json_encode(["error" => "沒找到這本書"]);
    }
}

// ============================================================
// 新增書籍
// POST /api/books
// ============================================================
function create()
{
    // 從 request body 取得 JSON 資料，轉成 PHP 陣列（true = 關聯陣列）
    $data = json_decode(file_get_contents("php://input"), true);

    // 驗證必填欄位，empty() 會擋掉 null、空字串、未傳的欄位
    if (empty($data["title"]) || empty($data["author"]) || empty($data["genre"])) {
        http_response_code(400);
        echo json_encode(["error" => "缺少 書名 或 作者 或 類別 欄位"]);
        return;
    }

    $connect = getDB();
    $stmt = $connect->prepare(
        "INSERT INTO books (title, author, genre, status, rate) VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $data["title"],
        $data["author"],
        $data["genre"],
        $data["status"] ?? "to-read",   // 前端沒傳則預設 "to-read"
        $data["rate"] ?? 0              // 前端沒傳則預設 0
    ]);

    // lastInsertId() 取得剛新增那筆資料的 id
    $id = $connect->lastInsertId();

    // 新增成功回傳 201 Created
    http_response_code(201);
    echo json_encode(["id" => $id], JSON_UNESCAPED_UNICODE);
}

// ============================================================
// 更新書籍（部分更新，只更新前端傳來的欄位）
// PUT /api/books/{id}
// ============================================================
function update($id)
{
    // 從 request body 取得資料
    $data = json_decode(file_get_contents("php://input"), true);

    // 驗證：有傳的欄位不能是空字串（沒傳的欄位不管，允許部分更新）
    foreach (["title", "author", "genre"] as $field) {
        if (isset($data[$field]) && empty($data[$field])) {
            http_response_code(400);
            echo json_encode(["error" => "{$field} 不能為空"]);
            return;
        }
    }

    // 動態組 SQL：只更新前端有傳的欄位
    $fields = [];  // 存放 "title = ?" 這類字串
    $values = [];  // 存放對應的值

    foreach (["title", "author", "genre", "status", "rate"] as $field) {
        if (isset($data[$field])) {
            $fields[] = "$field = ?";
            $values[] = $data[$field];
        }
    }

    // 沒有任何欄位要更新
    if (empty($fields)) {
        http_response_code(400);
        echo json_encode(["error" => "沒有要更新的欄位"]);
        return;
    }

    // 把 id 加在 values 最後，對應 WHERE id = ?
    $values[] = $id;

    $connect = getDB();

    // implode(", ", $fields) 把陣列組成 "title = ?, author = ?" 字串
    $stmt = $connect->prepare(
        "UPDATE books SET " . implode(", ", $fields) . " WHERE id = ?"
    );
    $stmt->execute($values);

    // rowCount() 取得受影響的筆數，0 代表 id 不存在或資料沒有變更
    $rowCount = $stmt->rowCount();
    if ($rowCount === 0) {
        http_response_code(404);
        echo json_encode(["error" => "沒找到這本書或沒有變更"]);
        return;
    } else {
        echo json_encode(["message" => "更新成功"], JSON_UNESCAPED_UNICODE);
    }
}

// ============================================================
// 刪除書籍
// DELETE /api/books/{id}
// ============================================================
function delete($id)
{
    $connect = getDB();
    $stmt = $connect->prepare("DELETE FROM books WHERE id = ?");
    $stmt->execute([$id]);

    // rowCount() 為 0 代表該 id 不存在
    $rowCount = $stmt->rowCount();
    if ($rowCount === 0) {
        http_response_code(404);
        echo json_encode(["error" => "沒找到這本書"]);
        return;
    } else {
        echo json_encode(["message" => "刪除成功"], JSON_UNESCAPED_UNICODE);
    }
}
