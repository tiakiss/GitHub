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
    
    // クエリの基本部分
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
    
    // グループ化と並べ替え
    if ($groupByPort) {
        $sql .= " GROUP BY remote_ip, port, servername ORDER BY COUNT(*) DESC";
    } else {
        $sql .= " GROUP BY remote_ip, servername ORDER BY COUNT(*) DESC";
    }
    
    // クエリの準備と実行
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // データ処理
    if ($groupByPort) {
        // IP+ポートの組み合わせでカウント
        $ipPortCounts = [];
        $detailedData = [];
        
        foreach ($results as $row) {
            // ラベル作成: IP:PORT形式
            $key = $row['remote_ip'] . ':' . $row['port'];
            
            // 全体カウント集計
            if (!isset($ipPortCounts[$key])) {
                $ipPortCounts[$key] = 0;
                $detailedData[$key] = [
                    'remote_ip' => $row['remote_ip'],
                    'port' => $row['port'],
                    'servers' => []
                ];
            }
            $ipPortCounts[$key] += intval($row['count']);
            
            // サーバー別集計データ保持
            $detailedData[$key]['servers'][] = [
                'servername' => $row['servername'],
                'count' => intval($row['count'])
            ];
        }
        
        // カウント降順ソート
        arsort($ipPortCounts);
        
        // トップ20エントリ取得
        $topEntries = array_slice(array_keys($ipPortCounts), 0, 20);
        
        // 最終データセット作成
        $filteredResults = [];
        foreach ($topEntries as $entry) {
            $parts = explode(':', $entry);
            $ip = $parts[0];
            $port = $parts[1];
            
            // サーバーごとのデータを追加
            foreach ($detailedData[$entry]['servers'] as $serverData) {
                $filteredResults[] = [
                    'remote_ip' => $ip,
                    'port' => $port,
                    'servername' => $serverData['servername'],
                    'count' => $serverData['count'],
                    'label' => $entry  // 明示的にIP:PORTラベルを追加
                ];
            }
        }
    } else {
        // IPごとに集計
        $ipCounts = [];
        $detailedData = [];
        
        foreach ($results as $row) {
            // 全体カウント集計
            if (!isset($ipCounts[$row['remote_ip']])) {
                $ipCounts[$row['remote_ip']] = 0;
                $detailedData[$row['remote_ip']] = [
                    'servers' => []
                ];
            }
            $ipCounts[$row['remote_ip']] += intval($row['count']);
            
            // サーバー別集計データ保持
            $detailedData[$row['remote_ip']]['servers'][] = [
                'servername' => $row['servername'],
                'count' => intval($row['count'])
            ];
        }
        
        // カウント降順ソート
        arsort($ipCounts);
        
        // トップ20のIPを取得
        $topIps = array_slice(array_keys($ipCounts), 0, 20);
        
        // 最終データセット作成
        $filteredResults = [];
        foreach ($topIps as $ip) {
            // サーバーごとのデータを追加
            foreach ($detailedData[$ip]['servers'] as $serverData) {
                $filteredResults[] = [
                    'remote_ip' => $ip,
                    'servername' => $serverData['servername'],
                    'count' => $serverData['count'],
                    'label' => $ip  // 明示的にIPラベルを追加
                ];
            }
        }
    }
    
    // JSONで結果を返す
    echo json_encode([
        'success' => true,
        'data' => $filteredResults,
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