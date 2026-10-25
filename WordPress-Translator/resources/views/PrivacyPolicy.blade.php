<!DOCTYPE html>
<html lang="ja">

<head>
    @include('head')
</head>

<body>
    <!-- 画面全体を縦横中央に配置するためのコンテナ -->
    <div class="mt-3 container d-flex flex-column justify-content-center align-items-center">
        <h2 class="rounded text-center mb-3 bg-white"><a href="/">Topへもどる</a></h2>
        <span id="main_content" class="bg-white opacity-75">
            <h2 class="wp-block-heading">プライバシーポリシー</h2>
            <p>[manabu.start-line-system.com]（以下「当サイト」）は、個人情報の保護に関する法律を遵守し、利用者のプライバシーを尊重いたします。当サイトでは、以下のとおり個人情報を取り扱います。
            </p>
            <h3 class="wp-block-heading">1. 個人情報の定義</h3>
            <p>個人情報とは、個人に関する情報であって、当サイトのサービスをご利用いただく際に、お客様からご提供いただく、氏名、メールアドレス、IPアドレスなど、特定の個人を識別できる情報のことをいいます。</p>
            <h3 class="wp-block-heading">2. 個人情報の収集目的</h3>
            <p>当サイトでは、お問い合わせ対応、統計データの分析、広告配信などの目的で、個人情報を収集することがあります。</p>
            <h3 class="wp-block-heading">3. 個人情報の第三者への提供</h3>
            <p>当サイトでは、法令に基づく場合を除き、原則として、お客様の同意を得ることなく、第三者に個人情報を提供することはありません。</p>
            <h3 class="wp-block-heading">4. Cookieの使用</h3>
            <p>当サイトでは、Cookieを使用しています。Cookieは、お客様のコンピュータを識別するために、お客様のブラウザに送信される小さなデータファイルです。お客様は、ブラウザの設定を変更することにより、Cookieの受け入れを拒否することができます。ただし、Cookieを拒否した場合、当サイトの一部の機能がご利用できない場合があります。
            </p>
            <h3 class="wp-block-heading">5. Google AdSenseの利用について</h3>
            <p>当サイトは、Google AdSenseを利用しています。Google AdSenseは、Google社が提供する広告配信サービスです。Google
                AdSenseの利用により、お客様のブラウザにCookieが設定される場合があります。Google AdSenseに関する詳細は、Googleのプライバシーポリシー（<a
                    href="https://policies.google.com/privacy" target="_blank" rel="noopener nofollow sponsored ugc"
                    title="" style="color: rgb(0, 0, 0);">https://policies.google.com/privacy</a>）をご覧ください。</p>
        </span>
        @include('footer')
    </div>
</body>

</html>
