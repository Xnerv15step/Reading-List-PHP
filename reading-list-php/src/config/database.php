<?php
// ============================================================
// 建立並回傳 PDO 資料庫連線
// 在需要操作資料庫的地方呼叫 getDB() 即可取得連線物件
// ============================================================
function getDB(): PDO
{
    $host   = "localhost";       // 資料庫主機
    $dbname = "reading_list";    // 資料庫名稱
    $user   = "root";            // 資料庫帳號
    $pass   = "lkhtm505";                // 資料庫密碼

    try {
        $pdo = new PDO(
            // DSN：指定資料庫類型、主機、資料庫名稱、編碼
            // charset=utf8mb4 支援中文及 emoji
            "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
            $user,
            $pass,
            [
                // 發生錯誤時拋出例外（Exception），方便 catch 捕捉
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,

                // 查詢結果預設以關聯陣列回傳（key => value）
                // 例如 ["id" => 1, "title" => "書名"]
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        // 連線失敗時回傳 500，並顯示錯誤訊息
        http_response_code(500);
        echo json_encode(["error" => "DB connection failed: " . $e->getMessage()]);
        exit; // 終止程式，避免繼續執行後續邏輯
    }
}
