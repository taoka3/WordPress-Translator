<!DOCTYPE html>
<html lang="ja">

<head>
    @include('head')
</head>

<body>
    <!-- 画面全体を縦横中央に配置するためのコンテナ -->
    <div class="mt-3 container d-flex flex-column justify-content-center align-items-center">

        <!-- メインのタイトル -->
        <h1 class="rounded mb-4 bg-white p-2 text-center w-100" style="max-width: 600px;">楽しんで学ぶ</h1>

        <!-- 学習システムの紹介 -->
        <div class="rounded text-center mb-3 bg-white p-3 w-100" style="max-width: 600px;">
            <h2 class="mb-3">学習の第一歩を踏み出そう！</h2>
            <p class="mb-0">
                このプラットフォームでは、算数や数学を楽しく学ぶことができます。学習をゲーム感覚で進めながら、自然と計算力を身につけることができるよう設計されています。自分のペースで学習し、理解を深めていきましょう！</p>
        </div>

        <!-- コンテンツを囲むカード -->
        <div class="card p-4 text-center shadow mb-4">
            <h2 class="mb-3 h4">楽しんで学ぶ<br>スタート・ライン・システム</h2>

            <!-- 学習科目の説明 -->
            <p class="small mb-4">以下の学習科目から選択してください。各科目ごとに、異なる学習内容を用意していますので、興味のあるものから挑戦してみましょう。</p>

            <!-- ボタンリスト -->
            <div class="d-grid gap-3">
                <a href="/multiplication" class="btn btn-primary">掛け算九九 - 基本を学ぼう<br>掛け算の基礎をしっかり身につけ、スムーズに計算できるようになろう！</a>
                <a href="/division" class="btn btn-success">割り算 - 分ける力を養う<br>ものの分け方を学び、日常生活にも活かせる計算力をつけよう！</a>
                <a href="/matheasyquestions" class="btn btn-warning">100問算数（やさしい） - 挑戦しよう<br>ランダムな問題に挑戦し、計算スピードと正確さを鍛えよう！</a>
                <a href="/mathquestions" class="btn btn-danger">100問算数（むずかしい） - 挑戦しよう<br>ランダムな問題に挑戦し、計算スピードと正確さを鍛えよう！</a>
            </div>
        </div>

        @include('footer')
    </div>

    <!-- Bootstrapのスクリプトを読み込む -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>