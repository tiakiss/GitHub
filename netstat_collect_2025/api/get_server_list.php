// api/get_server_list.php
<?php
header('Content-Type: application/json');

try {
    // データベース接続
    $dbConfig = include '../config/database.php';
    $dsn = "pgsql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['dbname']};user={$dbConfig['user']};password={$dbConfig['password']}";
    $pdo = new PDO($dsn);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // ユニークなサーバー名を取得
    $stmt = $pdo->query("
        SELECT DISTINCT servername 
        FROM netstat_date 
        WHERE servername IS NOT NULL
        ORDER BY servername
    ");
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // JSONで結果を返す
    echo json_encode([
        'success' => true,
        'data' => $results
    ]);
    
} catch (Exception $e) {
    // エラーの場合
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>