<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NetStat分析ダッシュボード</title>
    <!-- Chart.jsをCDNから読み込み -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f7fa;
            color: #333;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #2c3e50;
            color: white;
            padding: 15px 20px;
            border-radius: 8px 8px 0 0;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .dashboard {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-top: 20px;
        }
        .card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .card-header {
            background-color: #3498db;
            color: white;
            padding: 10px 15px;
            font-weight: bold;
        }
        .card-body {
            padding: 15px;
            height: 300px;
        }
        .filters {
            background-color: white;
            padding: 15px;
            margin-top: 20px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        .filter-group label {
            margin-bottom: 5px;
            font-weight: 500;
            font-size: 12px;
            color: #555;
        }
        .filter-group select,
        .filter-group input[type="date"] {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .filter-group button {
            background-color: #2c3e50;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
            font-size: 14px;
        }
        .filter-group button:hover {
            background-color: #1a252f;
        }
        .toggle-container {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 40px;
            height: 20px;
        }
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 2px;
            bottom: 2px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        input:checked + .toggle-slider {
            background-color: #2c3e50;
        }
        input:checked + .toggle-slider:before {
            transform: translateX(20px);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>NetStat分析ダッシュボード</h1>
        </div>
        
        <div class="filters">
            <div class="filter-group">
                <label for="date-range">期間</label>
                <select id="date-range">
                    <option value="today">今日</option>
                    <option value="yesterday">昨日</option>
                    <option value="last7days" selected>過去7日間</option>
                    <option value="last30days">過去30日間</option>
                    <option value="custom">カスタム</option>
                </select>
            </div>
            
            <div class="filter-group" id="custom-date-container" style="display: none;">
                <label for="custom-from">開始日</label>
                <input type="date" id="custom-from">
            </div>
            
            <div class="filter-group" id="custom-date-to-container" style="display: none;">
                <label for="custom-to">終了日</label>
                <input type="date" id="custom-to">
            </div>
            
            <div class="filter-group">
                <label>サーバー名（複数選択可）</label>
                <div style="max-height: 120px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px; padding: 8px;">
                    <div style="display: flex; align-items: center; margin-bottom: 5px;">
                        <input type="checkbox" id="server-all" checked>
                        <label for="server-all" style="margin-left: 5px; margin-bottom: 0;">すべて</label>
                    </div>
                    <!-- サーバーリストが動的に読み込まれます -->
                    <div id="server-loading" style="color: #666; padding: 5px;">読み込み中...</div>
                </div>
            </div>
            
            <div class="filter-group">
                <button id="apply-filters">適用</button>
            </div>
        </div>
        
        <div class="dashboard">
            <div class="card">
                <div class="card-header">接続状態の分布</div>
                <div class="card-body">
                    <canvas id="state-chart"></canvas>
                </div>
            </div>
            <div class="card">
                <div class="card-header">時間帯別接続数</div>
                <div class="card-body">
                    <canvas id="hourly-chart"></canvas>
                </div>
            </div>
                    
            <div class="card">
                <div class="card-header">ポート別接続数</div>
                <div class="card-body">
                    <canvas id="port-chart"></canvas>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    トップリモートIP
                    <div class="toggle-container" style="float: right; font-size: 14px; font-weight: normal;">
                        <label class="toggle-switch">
                            <input type="checkbox" id="ip-port-toggle">
                            <span class="toggle-slider"></span>
                        </label>
                        <span id="toggle-label">ポート別表示: オフ</span>
                    </div>
                </div>
                <div class="card-body">
                    <canvas id="remote-ip-chart"></canvas>
                </div>
            </div>

        </div>
    </div>

    <script>
        // フィルター条件を含むAPIの呼び出し関数
        async function fetchDataWithFilters(endpoint, additionalParams = {}) {
            console.log(`${endpoint} のデータ取得開始`);
            
            // 日付範囲を取得
            const dateRange = document.getElementById('date-range').value;
            let fromDate = null;
            let toDate = null;
            
            // 日付範囲に応じてフィルター値を設定
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            
            switch (dateRange) {
                case 'today':
                    fromDate = today;
                    toDate = new Date(today);
                    toDate.setHours(23, 59, 59, 999);
                    break;
                case 'yesterday':
                    fromDate = new Date(today);
                    fromDate.setDate(fromDate.getDate() - 1);
                    toDate = new Date(today);
                    toDate.setMilliseconds(-1);
                    break;
                case 'last7days':
                    fromDate = new Date(today);
                    fromDate.setDate(fromDate.getDate() - 7);
                    toDate = new Date(today);
                    toDate.setHours(23, 59, 59, 999);
                    break;
                case 'last30days':
                    fromDate = new Date(today);
                    fromDate.setDate(fromDate.getDate() - 30);
                    toDate = new Date(today);
                    toDate.setHours(23, 59, 59, 999);
                    break;
                case 'custom':
                    const customFrom = document.getElementById('custom-from').value;
                    const customTo = document.getElementById('custom-to').value;
                    
                    if (customFrom) {
                        fromDate = new Date(customFrom);
                        fromDate.setHours(0, 0, 0, 0);
                    }
                    
                    if (customTo) {
                        toDate = new Date(customTo);
                        toDate.setHours(23, 59, 59, 999);
                    }
                    break;
            }
            
            // サーバー選択を取得
            const allServersSelected = document.getElementById('server-all').checked;
            let selectedServer = null;
            
            if (!allServersSelected) {
                // クラスセレクタを使用して選択されたサーバーチェックボックスを取得
                const serverCheckboxes = document.querySelectorAll('input.server-checkbox:checked');
                console.log('選択されたサーバーチェックボックス数:', serverCheckboxes.length);
                
                if (serverCheckboxes.length === 1) {
                    // 1つのサーバーだけが選択されている場合
                    selectedServer = serverCheckboxes[0].value;
                    console.log('選択されたサーバー:', selectedServer);
                } else if (serverCheckboxes.length > 1) {
                    // 複数選択の場合は「すべて」として扱う
                    console.log('複数サーバーが選択されています - 「すべて」として扱います');
                    // selectedServer は null のまま
                }
            }
            
            // クエリパラメータの構築
            const params = new URLSearchParams();
            if (fromDate) {
                params.append('from_date', fromDate.toISOString().split('T')[0]);
            }
            if (toDate) {
                params.append('to_date', toDate.toISOString().split('T')[0]);
            }
            if (selectedServer) {
                params.append('server_name', selectedServer);
            }
            
            // 追加パラメータがあれば追加
            for (const [key, value] of Object.entries(additionalParams)) {
                params.append(key, value);
            }
            
            // APIからデータを取得
            const apiUrl = `api/${endpoint}?${params.toString()}`;
            console.log(`Fetch URL: ${apiUrl}`);
            
            try {
                const response = await fetch(apiUrl);
                if (!response.ok) {
                    throw new Error(`APIリクエストエラー: ${response.status} ${response.statusText}`);
                }
                
                const data = await response.json();
                console.log(`${endpoint} の応答データ:`, data);
                
                // フィルター情報をログ出力
                if (data.filters) {
                    console.log('適用されたフィルター:', data.filters);
                }
                
                return data;
            } catch (error) {
                console.error(`${endpoint} データ取得エラー:`, error);
                throw error;
            }
        }
        
        // 接続状態のデータを取得してグラフを描画
        async function loadStateChart() {
            const chartContainer = document.getElementById('state-chart').parentNode; // 親要素を取得
            const canvasElement = document.getElementById('state-chart'); // Canvas要素を取得

            try {
                console.log('接続状態のグラフ読み込み開始'); // 追加: 開始ログ

                // 既存のメッセージ表示があればクリア（ローディング表示を除く）
                const existingMessage = chartContainer.querySelector('div[style*="color:"]');
                if (existingMessage) {
                    chartContainer.removeChild(existingMessage);
                }
                // ローディング表示があれば削除 (reloadAllCharts側で追加される想定)
                const loadingDiv = chartContainer.querySelector('div:not([style*="color:"])');
                if (loadingDiv && loadingDiv.textContent.includes('読み込み中')) {
                     chartContainer.removeChild(loadingDiv);
                }
                // Canvasを再表示（以前に非表示にされた可能性があるため）
                canvasElement.style.display = 'block';

                // フィルター付きでデータを取得
                const result = await fetchDataWithFilters('get_state_stats.php');
                console.log('取得データ (state):', result); // 追加: API応答ログ

                if (!result.success) {
                    throw new Error(result.error || 'データ取得エラー');
                }
                
                const data = result.data;
                console.log('グラフ用データ (state):', data); // 追加: 処理対象データログ
                
                // 既存のチャートを破棄（再描画時） - データチェック前に移動
                if (window.stateChart instanceof Chart) {
                    window.stateChart.destroy();
                    window.stateChart = null; // 参照をクリアしておく
                }

                // データが空の場合の処理
                if (!data || data.length === 0) {
                    // Canvasを非表示にし、メッセージを表示
                    canvasElement.style.display = 'none';
                    const messageDiv = document.createElement('div');
                    messageDiv.style.color = 'orange';
                    messageDiv.style.padding = '20px';
                    messageDiv.style.textAlign = 'center';
                    messageDiv.textContent = '選択した期間のデータがありません';
                    // 既存のメッセージがなければ追加
                    if (!chartContainer.querySelector('div[style*="color: orange"]')) {
                         chartContainer.appendChild(messageDiv);
                    }
                    console.log('データが空のためグラフ描画をスキップ (state)'); // 追加: 空データログ
                    return; // データがない場合はここで終了
                }
                
                // --- データがある場合の描画処理 ---
                // Canvasが表示されていることを確認
                canvasElement.style.display = 'block';
                
                // グラフの描画
                const ctx = canvasElement.getContext('2d');
                
                window.stateChart = new Chart(ctx, {
                    type: 'pie',
                    data: {
                        labels: data.map(item => item.state),
                        datasets: [{
                            data: data.map(item => item.count),
                            backgroundColor: [
                                'rgba(255, 99, 132, 0.7)',
                                'rgba(54, 162, 235, 0.7)',
                                'rgba(255, 206, 86, 0.7)',
                                'rgba(75, 192, 192, 0.7)',
                                'rgba(153, 102, 255, 0.7)',
                                'rgba(255, 159, 64, 0.7)' // 追加の色
                            ]
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'right'
                            }
                        }
                    }
                });
                console.log('接続状態グラフ描画完了'); // 追加: 完了ログ
                
            } catch (error) {
                console.error('状態グラフ描画エラー:', error); // 修正: エラーログの改善
                // 既存のチャートがあれば破棄
                if (window.stateChart instanceof Chart) {
                    window.stateChart.destroy();
                    window.stateChart = null;
                }
                // Canvasを非表示にし、エラーメッセージを表示
                canvasElement.style.display = 'none';
                 // 既存のエラー/空メッセージ表示があればクリア
                const existingMessages = chartContainer.querySelectorAll('div[style*="color:"]');
                existingMessages.forEach(msg => chartContainer.removeChild(msg));
                 // ローディング表示があれば削除
                const loadingDiv = chartContainer.querySelector('div:not([style*="color:"])');
                if (loadingDiv && loadingDiv.textContent.includes('読み込み中')) {
                     chartContainer.removeChild(loadingDiv);
                }
                const errorDiv = document.createElement('div');
                errorDiv.style.color = 'red';
                errorDiv.style.padding = '20px';
                errorDiv.style.textAlign = 'center';
                errorDiv.innerHTML = `グラフ表示エラー<br><small>${error.message}</small>`;
                chartContainer.appendChild(errorDiv);
            }
        }
        
        // 時間帯別接続数のグラフを描画
        async function loadHourlyChart() {
            try {
                // フィルター付きでデータを取得
                const result = await fetchDataWithFilters('get_hourly_stats.php');
                
                if (!result.success) {
                    throw new Error(result.error || 'データ取得エラー');
                }
                
                const data = result.data;
                
                // グラフの描画
                const ctx = document.getElementById('hourly-chart').getContext('2d');
                
                // 既存のチャートを破棄（再描画時）
                if (window.hourlyChart instanceof Chart) {
                    window.hourlyChart.destroy();
                }
                
                window.hourlyChart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: data.map(item => `${item.hour}時`),
                        datasets: [{
                            label: '接続数',
                            data: data.map(item => item.count),
                            backgroundColor: 'rgba(54, 162, 235, 0.2)',
                            borderColor: 'rgba(54, 162, 235, 1)',
                            borderWidth: 2,
                            tension: 0.1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
                
            } catch (error) {
                console.error('時間帯別グラフ描画エラー:', error);
                const chartContainer = document.getElementById('hourly-chart').parentNode;
                chartContainer.innerHTML = 
                    `<div style="color: red; padding: 20px; text-align: center;">グラフ表示エラー<br><small>${error.message}</small></div>`;
            }
        }
        
        // サーバー表示用の色を取得する共通関数
        function getServerColors(index) {
            const colors = [
                'rgba(255, 99, 132, 0.7)',   // 赤
                'rgba(54, 162, 235, 0.7)',   // 青
                'rgba(255, 206, 86, 0.7)',   // 黄
                'rgba(75, 192, 192, 0.7)',   // 緑
                'rgba(153, 102, 255, 0.7)',  // 紫
                'rgba(255, 159, 64, 0.7)',   // オレンジ
                'rgba(199, 199, 199, 0.7)',  // グレー
                'rgba(83, 102, 255, 0.7)',   // 青紫
                'rgba(255, 99, 255, 0.7)',   // ピンク
                'rgba(159, 159, 64, 0.7)',   // オリーブ
            ];
            
            const color = colors[index % colors.length];
            const borderColor = color.replace('0.7', '1');
            
            return {
                backgroundColor: color,
                borderColor: borderColor
            };
        }

        // ポート別接続数のグラフを描画
        async function loadPortChart() {
            const chartContainer = document.getElementById('port-chart').parentNode; // 親要素を取得
            const canvasElement = document.getElementById('port-chart'); // Canvas要素を取得

            try {
                console.log('ポート別グラフ読み込み開始');

                // 既存のメッセージ表示があればクリア
                const existingMessage = chartContainer.querySelector('div[style*="color:"]');
                if (existingMessage) {
                    chartContainer.removeChild(existingMessage);
                }
                // ローディング表示があれば削除
                const loadingDiv = chartContainer.querySelector('div:not([style*="color:"])');
                if (loadingDiv && loadingDiv.textContent.includes('読み込み中')) {
                    chartContainer.removeChild(loadingDiv);
                }
                // Canvasを再表示
                canvasElement.style.display = 'block';

                // フィルター付きでデータを取得
                const result = await fetchDataWithFilters('get_port_stats.php');
                console.log('取得データ (port):', result);

                if (!result.success) {
                    throw new Error(result.error || 'データ取得エラー');
                }
                
                const data = result.data;
                console.log('グラフ用データ (port):', data);
                
                // 既存のチャートを破棄
                if (window.portChart instanceof Chart) {
                    window.portChart.destroy();
                    window.portChart = null;
                }

                // データが空の場合の処理
                if (!data || data.length === 0) {
                    // Canvasを非表示にし、メッセージを表示
                    canvasElement.style.display = 'none';
                    const messageDiv = document.createElement('div');
                    messageDiv.style.color = 'orange';
                    messageDiv.style.padding = '20px';
                    messageDiv.style.textAlign = 'center';
                    messageDiv.textContent = '選択した期間のデータがありません';
                    // 既存のメッセージがなければ追加
                    if (!chartContainer.querySelector('div[style*="color: orange"]')) {
                        chartContainer.appendChild(messageDiv);
                    }
                    console.log('データが空のためグラフ描画をスキップ (port)');
                    return;
                }

                // --- データがある場合の処理 ---
                canvasElement.style.display = 'block';

                // ポート別・サーバー別にデータを整理
                const topPorts = result.top_ports || [];
                // トップポートがない場合は、データから抽出
                if (topPorts.length === 0) {
                    const portCounts = {};
                    data.forEach(item => {
                        if (!portCounts[item.port]) {
                            portCounts[item.port] = 0;
                        }
                        portCounts[item.port] += parseInt(item.count);
                    });
                    // 降順にソートして上位10件を取得
                    const sortedPorts = Object.keys(portCounts).sort((a, b) => portCounts[b] - portCounts[a]);
                    topPorts.push(...sortedPorts.slice(0, 10));
                }

                // サーバー一覧を取得
                const servers = [...new Set(data.map(item => item.servername))];
                
                // サーバーごとにデータセットを作成
                const datasets = servers.map((server, index) => {
                    const serverColor = getServerColors(index);
                    // 各ポートのこのサーバーでの接続数を取得
                    const serverData = topPorts.map(port => {
                        const match = data.find(item => item.port === port && item.servername === server);
                        return match ? parseInt(match.count) : 0;
                    });
                    
                    return {
                        label: server,
                        data: serverData,
                        backgroundColor: serverColor.backgroundColor,
                        borderColor: serverColor.borderColor,
                        borderWidth: 1
                    };
                });

                // グラフの描画
                const ctx = canvasElement.getContext('2d');
                
                window.portChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: topPorts.map(port => `${port}`),
                        datasets: datasets
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        indexAxis: 'y', // 横棒グラフ
                        scales: {
                            x: {
                                beginAtZero: true,
                                stacked: true
                            },
                            y: {
                                stacked: true
                            }
                        },
                        plugins: {
                            tooltip: {
                                callbacks: {
                                    title: function(context) {
                                        return `Port ${context[0].label}`;
                                    }
                                }
                            },
                            legend: {
                                position: 'right'
                            }
                        }
                    }
                });
                console.log('ポート別グラフ描画完了');
                
            } catch (error) {
                console.error('ポート別グラフ描画エラー:', error);
                // 既存のチャートがあれば破棄
                if (window.portChart instanceof Chart) {
                    window.portChart.destroy();
                    window.portChart = null;
                }
                // Canvasを非表示にし、エラーメッセージを表示
                canvasElement.style.display = 'none';
                // 既存のメッセージ/ローディング表示をクリア
                const existingMessages = chartContainer.querySelectorAll('div[style*="color:"]');
                existingMessages.forEach(msg => chartContainer.removeChild(msg));
                const loadingDiv = chartContainer.querySelector('div:not([style*="color:"])');
                if (loadingDiv && loadingDiv.textContent.includes('読み込み中')) {
                    chartContainer.removeChild(loadingDiv);
                }
                const errorDiv = document.createElement('div');
                errorDiv.style.color = 'red';
                errorDiv.style.padding = '20px';
                errorDiv.style.textAlign = 'center';
                errorDiv.innerHTML = `グラフ表示エラー<br><small>${error.message}</small>`;
                chartContainer.appendChild(errorDiv);
            }
        }
        
        // トップリモートIPのグラフを描画
        async function loadRemoteIpChart() {
            const chartContainer = document.getElementById('remote-ip-chart').parentNode;
            const canvasElement = document.getElementById('remote-ip-chart');
            const groupByPort = document.getElementById('ip-port-toggle').checked;

            try {
                console.log('リモートIPグラフ読み込み開始 (ポート別表示: ' + (groupByPort ? 'オン' : 'オフ') + ')');

                // 既存のメッセージ表示があればクリア
                const existingMessage = chartContainer.querySelector('div[style*="color:"]');
                if (existingMessage) {
                    chartContainer.removeChild(existingMessage);
                }
                // ローディング表示があれば削除
                const loadingDiv = chartContainer.querySelector('div:not([style*="color:"])');
                if (loadingDiv && loadingDiv.textContent.includes('読み込み中')) {
                    chartContainer.removeChild(loadingDiv);
                }
                // Canvasを再表示
                canvasElement.style.display = 'block';

                // フィルター付きでデータを取得 (group_by_portパラメータを追加)
                const result = await fetchDataWithFilters('get_remote_ip_stats.php', { 
                    group_by_port: groupByPort 
                });
                console.log('取得データ (remote_ip):', result);

                if (!result.success) {
                    throw new Error(result.error || 'データ取得エラー');
                }
                
                const data = result.data;
                console.log('グラフ用データ (remote_ip):', data);
                
                // 既存のチャートを破棄
                if (window.remoteIpChart instanceof Chart) {
                    window.remoteIpChart.destroy();
                    window.remoteIpChart = null;
                }

                // データが空の場合の処理
                if (!data || data.length === 0) {
                    // Canvasを非表示にし、メッセージを表示
                    canvasElement.style.display = 'none';
                    const messageDiv = document.createElement('div');
                    messageDiv.style.color = 'orange';
                    messageDiv.style.padding = '20px';
                    messageDiv.style.textAlign = 'center';
                    messageDiv.textContent = '選択した期間のデータがありません';
                    // 既存のメッセージがなければ追加
                    if (!chartContainer.querySelector('div[style*="color: orange"]')) {
                        chartContainer.appendChild(messageDiv);
                    }
                    console.log('データが空のためグラフ描画をスキップ (remote_ip)');
                    return;
                }

                // --- データがある場合の処理 ---
                canvasElement.style.display = 'block';

                // リモートIP別・サーバー別にデータを整理 (ポートを含むかどうかで処理を分岐)
                const topEntries = result.top_entries || [];
                
                // トップエントリがない場合は、データから抽出
                if (topEntries.length === 0) {
                    if (groupByPort) {
                        // ポート別表示の場合
                        const ipPortCounts = {};
                        data.forEach(item => {
                            const key = `${item.remote_ip}:${item.port}`;
                            if (!ipPortCounts[key]) {
                                ipPortCounts[key] = 0;
                            }
                            ipPortCounts[key] += parseInt(item.count);
                        });
                        // 降順にソートして上位10件を取得（表示数制限）
                        const sortedEntries = Object.keys(ipPortCounts).sort((a, b) => ipPortCounts[b] - ipPortCounts[a]);
                        topEntries.push(...sortedEntries.slice(0, 10));
                    } else {
                        // IP単位表示の場合（既存の処理）
                        const ipCounts = {};
                        data.forEach(item => {
                            if (!ipCounts[item.remote_ip]) {
                                ipCounts[item.remote_ip] = 0;
                            }
                            ipCounts[item.remote_ip] += parseInt(item.count);
                        });
                        // 降順にソートして上位10件を取得（表示数制限）
                        const sortedIps = Object.keys(ipCounts).sort((a, b) => ipCounts[b] - ipCounts[a]);
                        topEntries.push(...sortedIps.slice(0, 10));
                    }
                } else {
                    // 表示数を制限（多すぎるとグラフが見づらくなるため）
                    topEntries.splice(10);
                }

                // サーバー一覧を取得
                const servers = [...new Set(data.map(item => item.servername))];
                
                // サーバーごとにデータセットを作成
                const datasets = servers.map((server, index) => {
                    const serverColor = getServerColors(index);
                    
                    // 各エントリのこのサーバーでの接続数を取得
                    const serverData = topEntries.map(entry => {
                        if (groupByPort) {
                            // IP:ポートの形式からIPとポートを分離
                            const [ip, port] = entry.split(':');
                            const match = data.find(item => 
                                item.remote_ip === ip && 
                                item.port === port && 
                                item.servername === server
                            );
                            return match ? parseInt(match.count) : 0;
                        } else {
                            // 従来通りIPのみで検索
                            const match = data.find(item => 
                                item.remote_ip === entry && 
                                item.servername === server
                            );
                            return match ? parseInt(match.count) : 0;
                        }
                    });
                    
                    return {
                        label: server,
                        data: serverData,
                        backgroundColor: serverColor.backgroundColor,
                        borderColor: serverColor.borderColor,
                        borderWidth: 1
                    };
                });

                // グラフの描画
                const ctx = canvasElement.getContext('2d');
                
                window.remoteIpChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: topEntries,
                        datasets: datasets
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        indexAxis: 'y', // 横棒グラフ
                        scales: {
                            x: {
                                beginAtZero: true,
                                stacked: true
                            },
                            y: {
                                stacked: true
                            }
                        },
                        plugins: {
                            tooltip: {
                                callbacks: {
                                    title: function(context) {
                                        if (groupByPort) {
                                            const [ip, port] = context[0].label.split(':');
                                            return `IP: ${ip}, ポート: ${port}`;
                                        } else {
                                            return `IP: ${context[0].label}`;
                                        }
                                    }
                                }
                            },
                            legend: {
                                position: 'right'
                            }
                        }
                    }
                });
                console.log('リモートIPグラフ描画完了');
                
            } catch (error) {
                console.error('リモートIPグラフ描画エラー:', error);
                // 既存のチャートがあれば破棄
                if (window.remoteIpChart instanceof Chart) {
                    window.remoteIpChart.destroy();
                    window.remoteIpChart = null;
                }
                // Canvasを非表示にし、エラーメッセージを表示
                canvasElement.style.display = 'none';
                // 既存のメッセージ/ローディング表示をクリア
                const existingMessages = chartContainer.querySelectorAll('div[style*="color:"]');
                existingMessages.forEach(msg => chartContainer.removeChild(msg));
                const loadingDiv = chartContainer.querySelector('div:not([style*="color:"])');
                if (loadingDiv && loadingDiv.textContent.includes('読み込み中')) {
                    chartContainer.removeChild(loadingDiv);
                }
                const errorDiv = document.createElement('div');
                errorDiv.style.color = 'red';
                errorDiv.style.padding = '20px';
                errorDiv.style.textAlign = 'center';
                errorDiv.innerHTML = `グラフ表示エラー<br><small>${error.message}</small>`;
                chartContainer.appendChild(errorDiv);
            }
        }

        // すべてのグラフを再読み込み
        function reloadAllCharts() {
            // 各グラフのCanvas要素を取得し、必要ならクリア/ローディング表示
            const chartIds = ['state-chart', 'hourly-chart', 'port-chart', 'remote-ip-chart'];
            chartIds.forEach(id => {
                const canvas = document.getElementById(id);
                if (canvas) {
                    const ctx = canvas.getContext('2d');
                    // 以前のエラーメッセージが表示されている可能性があるのでクリア
                    const parent = canvas.parentNode;
                    if (parent.querySelector('div[style*="color: red"]')) {
                         // Canvasを再生成しないとChart.jsがエラーになることがあるため注意
                         // 簡単なローディング表示
                         parent.innerHTML = `<canvas id="${id}"></canvas><div style="text-align:center; padding: 20px;">読み込み中...</div>`;
                    } else {
                        // 既存のCanvasにローディング表示を追加
                        const loadingDiv = document.createElement('div');
                        loadingDiv.style.textAlign = 'center';
                        loadingDiv.style.padding = '20px';
                        loadingDiv.textContent = '読み込み中...';
                        // 既存のローディング表示があれば削除
                        const existingLoading = parent.querySelector('div[style*="text-align:center"]');
                        if (existingLoading) {
                           parent.removeChild(existingLoading);
                        }
                        parent.appendChild(loadingDiv);
                    }
                }
            });

            // 各グラフのロード処理を呼び出す
            Promise.all([
                loadStateChart(),
                loadHourlyChart(),
                loadPortChart(),
                loadRemoteIpChart()
            ]).then(() => {
                 // ローディング表示を削除
                 chartIds.forEach(id => {
                     const canvas = document.getElementById(id);
                     if (canvas) {
                         const parent = canvas.parentNode;
                         const loadingDiv = parent.querySelector('div[style*="text-align:center"]');
                         if (loadingDiv) {
                             parent.removeChild(loadingDiv);
                         }
                     }
                 });
                 console.log("すべてのグラフが更新されました。");
            }).catch(error => {
                console.error("グラフの更新中にエラーが発生しました:", error);
                // エラー発生時もローディング表示を削除した方が良い場合がある
                 chartIds.forEach(id => {
                     const canvas = document.getElementById(id);
                      if (canvas) {
                         const parent = canvas.parentNode;
                         const loadingDiv = parent.querySelector('div[style*="text-align:center"]');
                         if (loadingDiv && loadingDiv.textContent === '読み込み中...') {
                             parent.removeChild(loadingDiv);
                             // 必要であれば全体的なエラーメッセージを表示
                         }
                     }
                 });
            });
        }
        
        // ページロード時にすべてのグラフを読み込む
        window.addEventListener('load', function() {
            // 初期表示時にカスタム日付欄を正しく表示するための処理
            const dateRangeSelect = document.getElementById('date-range');
            const customDateContainers = [
                document.getElementById('custom-date-container'),
                document.getElementById('custom-date-to-container')
            ];
            
            function toggleCustomDateInputs() {
                if (dateRangeSelect.value === 'custom') {
                    customDateContainers.forEach(container => container.style.display = 'flex');
                } else {
                    customDateContainers.forEach(container => container.style.display = 'none');
                }
            }
            
            // 初期表示
            toggleCustomDateInputs();
            
            // セレクトボックス変更時のイベントリスナー
            dateRangeSelect.addEventListener('change', toggleCustomDateInputs);
            
            // トグルスイッチのイベントリスナーを追加
            document.getElementById('ip-port-toggle').addEventListener('change', function() {
                const toggleLabel = document.getElementById('toggle-label');
                toggleLabel.textContent = this.checked ? 'ポート別表示: オン' : 'ポート別表示: オフ';
                // トグルが変更されたら対応するグラフを再読み込み
                loadRemoteIpChart();
            });
            
            // サーバーリストとグラフを読み込む
            loadServerList();
            reloadAllCharts();
        });

        // フィルター関連のイベントハンドラ (カスタム日付表示切り替え - 上のloadイベント内に移動)
        // document.getElementById('date-range').addEventListener('change', function() { ... }); // この部分はloadイベントリスナー内に統合

        // フィルターを適用する関数
        function applyFilters() {
            console.log('フィルター適用ボタンがクリックされました。');
            // すべてのグラフを再読み込み
            reloadAllCharts();
        }

        // 適用ボタンのイベントハンドラ
        document.getElementById('apply-filters').addEventListener('click', applyFilters);
        
        // Chart.jsのインスタンスをグローバルスコープに保持するための変数を宣言 (任意)
        window.stateChart = null;
        window.hourlyChart = null;
        window.portChart = null;
        window.remoteIpChart = null;

        // サーバーリストを取得して表示する関数の改善版
        async function loadServerList() {
            try {
                console.log('サーバーリストの読み込み開始');
                
                const container = document.querySelector('.filter-group div[style*="overflow-y: auto"]');
                if (!container) {
                    console.error('サーバーリスト表示用のコンテナが見つかりませんでした');
                    return;
                }
                
                const existingCheckboxes = container.querySelectorAll('div:not(:first-child)');
                existingCheckboxes.forEach(checkbox => {
                    checkbox.remove();
                });
                
                const existingLoading = container.querySelector('#server-loading');
                if (existingLoading) {
                    container.removeChild(existingLoading);
                }
                
                const loadingDiv = document.createElement('div');
                loadingDiv.id = 'server-loading';
                loadingDiv.style.cssText = 'color: #666; padding: 5px;';
                loadingDiv.textContent = 'サーバーリスト取得中...';
                container.appendChild(loadingDiv);
                
                const response = await fetch('api/get_server_list.php');
                const responseData = await response.json();
                
                console.log('サーバーリストAPIの応答:', responseData);
                
                loadingDiv.remove();
                
                if (!responseData.success) {
                    throw new Error(responseData.error || 'サーバーリスト取得エラー');
                }
                
                const servers = responseData.data;
                console.log('取得したサーバーリスト:', servers);
                
                if (!servers || !Array.isArray(servers) || servers.length === 0) {
                    console.warn('サーバーリストが空です');
                    const messageDiv = document.createElement('div');
                    messageDiv.textContent = 'サーバーリストが空です';
                    messageDiv.style.cssText = 'color: #666; padding: 5px;';
                    container.appendChild(messageDiv);
                    return;
                }
                
                servers.forEach(server => {
                    if (!server.servername) {
                        console.warn('サーバー名が null または undefined のサーバーはスキップします');
                        return;
                    }
                    
                    console.log('サーバー追加:', server.servername);
                    
                    const serverDiv = document.createElement('div');
                    serverDiv.style.cssText = 'display: flex; align-items: center; margin-bottom: 5px;';
                    
                    const checkbox = document.createElement('input');
                    checkbox.type = 'checkbox';
                    checkbox.id = `server-${server.servername.replace(/[.\s]/g, '-')}`;
                    checkbox.value = server.servername;
                    checkbox.className = 'server-checkbox';
                    checkbox.disabled = document.getElementById('server-all').checked;
                    
                    const label = document.createElement('label');
                    label.setAttribute('for', checkbox.id);
                    label.style.cssText = 'margin-left: 5px; margin-bottom: 0;';
                    label.textContent = server.servername;
                    
                    serverDiv.appendChild(checkbox);
                    serverDiv.appendChild(label);
                    container.appendChild(serverDiv);
                    
                    checkbox.addEventListener('change', handleServerCheckboxChange);
                });
                
                console.log('サーバーリスト表示完了');
                
            } catch (error) {
                console.error('サーバーリスト読み込みエラー:', error);
                
                const container = document.querySelector('.filter-group div[style*="overflow-y: auto"]');
                if (container) {
                    const loadingElement = document.getElementById('server-loading');
                    if (loadingElement) {
                        container.removeChild(loadingElement);
                    }
                    
                    const errorDiv = document.createElement('div');
                    errorDiv.textContent = `サーバーリスト取得エラー: ${error.message}`;
                    errorDiv.style.cssText = 'color: red; padding: 5px;';
                    container.appendChild(errorDiv);
                }
            }
        }

        // サーバー選択のチェックボックス制御
        function handleServerCheckboxChange(event) {
            console.log('チェックボックス変更イベント:', this.id, 'チェック状態:', this.checked);
            
            const allServerCheckbox = document.getElementById('server-all');
            const serverCheckboxes = document.querySelectorAll('input.server-checkbox');
            
            console.log('サーバーチェックボックス数:', serverCheckboxes.length);
            
            if (this.id === 'server-all') {
                if (this.checked) {
                    console.log('「すべて」が選択されました - 他のチェックボックスを無効化します');
                    serverCheckboxes.forEach(checkbox => {
                        checkbox.checked = false;
                        checkbox.disabled = true;
                    });
                } else {
                    console.log('「すべて」の選択が解除されました - 他のチェックボックスを有効化します');
                    serverCheckboxes.forEach(checkbox => {
                        checkbox.disabled = false;
                    });
                }
            } else {
                if (this.checked) {
                    console.log('個別サーバーが選択されました:', this.value);
                    allServerCheckbox.checked = false;
                }
                
                const anyChecked = Array.from(serverCheckboxes).some(cb => cb.checked);
                console.log('いずれかのサーバーが選択されているか:', anyChecked);
                
                if (!anyChecked) {
                    console.log('すべてのサーバーの選択が解除されました - 「すべて」を選択します');
                    allServerCheckbox.checked = true;
                    serverCheckboxes.forEach(cb => {
                        cb.disabled = true;
                    });
                }
            }
            
            if (allServerCheckbox.checked) {
                console.log('現在の選択: すべてのサーバー');
            } else {
                const selected = Array.from(serverCheckboxes)
                    .filter(cb => cb.checked)
                    .map(cb => cb.value);
                console.log('現在選択されているサーバー:', selected.length > 0 ? selected : '選択なし（すべて）');
            }
        }

    </script>
</body>
</html>