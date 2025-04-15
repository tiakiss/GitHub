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
    $filterPort = isset($_GET['filter_port']) ? $_GET['filter_port'] : null;
    
    // クエリの基本部分 - サーバー名も含めて取得するように変更
    $sql = "
        SELECT 
            port, 
            servername,
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
    
    // ポートフィルター（複数ポート対応）
    if ($filterPort && $filterPort !== 'all') {
        // カンマ区切りのポートリストをチェック
        if (strpos($filterPort, ',') !== false) {
            $ports = explode(',', $filterPort);
            $placeholders = [];
            foreach ($ports as $i => $port) {
                $paramName = ":filter_port_$i"; // アンダースコアを使用（コロンとセット）
                $placeholders[] = $paramName;
                $params[$paramName] = trim($port);
            }
            $sql .= " AND port IN (" . implode(', ', $placeholders) . ")";
        } else {
            // 単一ポートの場合
            $sql .= " AND port = :filter_port";
            $params[':filter_port'] = $filterPort;
        }
    }
    
    // グループ化と並べ替え - portとservernameの両方でグループ化
    $sql .= " GROUP BY port, servername ORDER BY COUNT(*) DESC";
    
    // クエリの準備と実行
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // トップ10のポートを特定する
    $topPorts = [];
    $portCounts = [];
    
    foreach ($results as $row) {
        if (!isset($portCounts[$row['port']])) {
            $portCounts[$row['port']] = 0;
        }
        $portCounts[$row['port']] += $row['count'];
    }
    
    // ポートを接続数の降順でソート
    arsort($portCounts);
    
    // トップ10のポートを取得
    $topPorts = array_slice(array_keys($portCounts), 0, 10);
    
    // 結果をトップポートのみに絞り込む
    $filteredResults = array_filter($results, function($row) use ($topPorts) {
        return in_array($row['port'], $topPorts);
    });
    
    // JSONで結果を返す
    echo json_encode([
        'success' => true,
        'data' => array_values($filteredResults),  // インデックスをリセット
        'top_ports' => $topPorts,
        'filters' => [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'server_name' => $serverName,
            'filter_port' => $filterPort
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