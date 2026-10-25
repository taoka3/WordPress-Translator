<!DOCTYPE html>
<html lang="ja">
<head>
    @include('head')
</head>
<body>
    <!-- 画面全体を縦横中央に配置するためのコンテナ -->
    <div class="mt-3 container d-flex flex-column justify-content-center align-items-center">
        <h2 class="rounded text-center mb-3 bg-white"><a href="/">Topへもどる</a></h2>
        <h2 class="rounded text-center mb-3 bg-white">掛け算九九</h2>
        <p class="rounded text-center bg-white">
            掛け算の基礎をしっかり身につけ、スムーズに計算できるようになろう！</p>
            <div class="d-grid gap-3">
                <div id="app">
                </div>
            </div>
        @include('footer')
    </div>
</body>
</html>
