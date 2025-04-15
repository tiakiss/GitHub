<?php
header('Content-Type: application/json');

try {
    // デバッグ情報を記録
    error_log('ポート一覧取得処理の開始');
    
    // データベース接続
    $dbConfig = include '../config/database.php';
    $dsn = "pgsql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['dbname']};user={$dbConfig['user']};password={$dbConfig['password']}";
    $pdo = new PDO($dsn);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // フィルターパラメータの取得（期間やサーバーでポートリストを絞り込む場合）
    $fromDate = isset($_GET['from_date']) ? $_GET['from_date'] : null;
    $toDate = isset($_GET['to_date']) ? $_GET['to_date'] : null;
    $serverName = isset($_GET['server_name']) ? $_GET['server_name'] : null;
    
    // クエリ構築
    $sql = "SELECT DISTINCT port FROM netstat_date WHERE 1=1";
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
    
    $sql .= " ORDER BY port";
    
    // クエリ実行
    error_log('実行するSQL: ' . $sql);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $ports = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // 結果をログ出力
    error_log('取得したポート数: ' . count($ports));
    if (count($ports) > 0) {
        error_log('ポートのサンプル: ' . $ports[0]);
    } else {
        error_log('ポートが取得できませんでした。');
    }
    
    // 接続数でトップ10のポートも取得
    $topPortSql = "
        SELECT 
            port, 
            COUNT(*) as count 
        FROM 
            netstat_date 
        WHERE 1=1
    ";
    
    // 同じフィルターを適用
    if ($fromDate) {
        $topPortSql .= " AND timestamp >= :from_date";
    }
    
    if ($toDate) {
        $topPortSql .= " AND timestamp <= :to_date";
    }
    
    if ($serverName && $serverName !== 'all') {
        $topPortSql .= " AND servername = :server_name";
    }
    
    $topPortSql .= " GROUP BY port ORDER BY count DESC LIMIT 10";
    
    $topPortStmt = $pdo->prepare($topPortSql);
    $topPortStmt->execute($params);
    $topPorts = $topPortStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // JSONで結果を返す
    echo json_encode([
        'success' => true,
        'data' => $ports,
        'top_ports' => $topPorts,
        'filters' => [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'server_name' => $serverName
        ]
    ]);
    
} catch (Exception $e) {
    // エラー詳細をログに記録
    error_log('ポート一覧取得エラー: ' . $e->getMessage());
    error_log('スタックトレース: ' . $e->getTraceAsString());
    
    // エラーの場合
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>