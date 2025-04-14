// api/get_server_list.php への修正提案
<?php
header('Content-Type: application/json');

try {
    // デバッグ情報を記録（必要に応じてエラーログに）
    error_log('サーバーリスト取得処理の開始');
    
    // データベース接続
    $dbConfig = include '../config/database.php';
    $dsn = "pgsql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['dbname']};user={$dbConfig['user']};password={$dbConfig['password']}";
    $pdo = new PDO($dsn);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // ユニークなサーバー名を取得
    $sql = "SELECT DISTINCT servername FROM netstat_date WHERE servername IS NOT NULL ORDER BY servername";
    error_log('実行するSQL: ' . $sql);
    
    $stmt = $pdo->query($sql);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 結果をログ出力
    error_log('取得したサーバー数: ' . count($results));
    if (count($results) > 0) {
        error_log('サーバー名のサンプル: ' . $results[0]['servername']);
    } else {
        error_log('サーバー名が取得できませんでした。テーブルにデータがあることを確認してください。');
    }
    
    // JSONで結果を返す
    echo json_encode([
        'success' => true,
        'data' => $results
    ]);
    
} catch (Exception $e) {
    // エラー詳細をログに記録
    error_log('サーバーリスト取得エラー: ' . $e->getMessage());
    error_log('スタックトレース: ' . $e->getTraceAsString());
    
    // エラーの場合
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>