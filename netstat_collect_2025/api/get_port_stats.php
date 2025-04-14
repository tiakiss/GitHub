<?php
header('Content-Type: application/json');

try {
    // データベース接続
    $dbConfig = include '../config/database.php';
    $dsn = "pgsql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['dbname']};user={$dbConfig['user']};password={$dbConfig['password']}";
    $pdo = new PDO($dsn);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // フィルターパラメータの取得
    $fromDate = isset($_GET['from_date']) ? $_GET['from_date'] : null;
    $toDate = isset($_GET['to_date']) ? $_GET['to_date'] : null;
    $serverName = isset($_GET['server_name']) ? $_GET['server_name'] : null;
    
    // クエリの基本部分
    $sql = "
        SELECT 
            port, 
            COUNT(*) as count 
        FROM 
            netstat_date 
        WHERE 1=1
    ";
    
    $params = [];
    
    // 日付範囲フィルター
    if ($fromDate) {
        $sql .= " AND timestamp >= :from_date";
        $params[':from_date'] = $fromDate . ' 00:00:00';
    }
    
    if ($toDate) {
        $sql .= " AND timestamp <= :to_date";
        $params[':to_date'] = $toDate . ' 23:59:59';
    }
    
    // サーバー名フィルター
    if ($serverName && $serverName !== 'all') {
        $sql .= " AND servername = :server_name";
        $params[':server_name'] = $serverName;
    }
    
    // グループ化と並べ替え
    $sql .= " GROUP BY port ORDER BY count DESC LIMIT 10";
    
    // クエリの準備と実行
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // JSONで結果を返す
    echo json_encode([
        'success' => true,
        'data' => $results,
        'filters' => [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'server_name' => $serverName
        ]
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