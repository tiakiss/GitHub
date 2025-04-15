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
    $groupByPort = isset($_GET['group_by_port']) ? filter_var($_GET['group_by_port'], FILTER_VALIDATE_BOOLEAN) : false;
    
    // クエリの基本部分 - 新しいgroup_by_portパラメータに基づいてSELECT句とGROUP BY句を変更
    if ($groupByPort) {
        $sql = "
            SELECT 
                remote_ip,
                port,
                servername, 
                COUNT(*) as count 
            FROM 
                netstat_date 
            WHERE 1=1
        ";
    } else {
        $sql = "
            SELECT 
                remote_ip,
                servername, 
                COUNT(*) as count 
            FROM 
                netstat_date 
            WHERE 1=1
        ";
    }
    
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
    
    // グループ化と並べ替え - group_by_portパラメータに基づいてGROUP BY句を変更
    if ($groupByPort) {
        $sql .= " GROUP BY remote_ip, port, servername ORDER BY COUNT(*) DESC";
    } else {
        $sql .= " GROUP BY remote_ip, servername ORDER BY COUNT(*) DESC";
    }
    
    // クエリの準備と実行
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // トップエントリを特定する
    if ($groupByPort) {
        // リモートIP+ポートの組み合わせでカウント
        $ipPortCounts = [];
        
        foreach ($results as $row) {
            $key = $row['remote_ip'] . ':' . $row['port'];
            if (!isset($ipPortCounts[$key])) {
                $ipPortCounts[$key] = 0;
            }
            $ipPortCounts[$key] += $row['count'];
        }
        
        // 接続数の降順でソート
        arsort($ipPortCounts);
        
        // トップ20のリモートIP+ポートを取得
        $topEntries = array_slice(array_keys($ipPortCounts), 0, 20);
        
        // 結果をトップエントリのみに絞り込む
        $filteredResults = array_filter($results, function($row) use ($topEntries) {
            $key = $row['remote_ip'] . ':' . $row['port'];
            return in_array($key, $topEntries);
        });
    } else {
        // 従来通りリモートIPのみでカウント
        $ipCounts = [];
        
        foreach ($results as $row) {
            if (!isset($ipCounts[$row['remote_ip']])) {
                $ipCounts[$row['remote_ip']] = 0;
            }
            $ipCounts[$row['remote_ip']] += $row['count'];
        }
        
        // リモートIPを接続数の降順でソート
        arsort($ipCounts);
        
        // トップ20のリモートIPを取得
        $topIps = array_slice(array_keys($ipCounts), 0, 20);
        
        // 結果をトップIPのみに絞り込む
        $filteredResults = array_filter($results, function($row) use ($topIps) {
            return in_array($row['remote_ip'], $topIps);
        });
    }
    
    // JSONで結果を返す
    echo json_encode([
        'success' => true,
        'data' => array_values($filteredResults), // インデックスをリセット
        'top_entries' => $groupByPort ? $topEntries : $topIps,
        'group_by_port' => $groupByPort,
        'filters' => [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'server_name' => $serverName,
            'group_by_port' => $groupByPort
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