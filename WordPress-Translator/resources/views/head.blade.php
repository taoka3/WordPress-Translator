<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>楽しんで学ぶ・スタート・ライン・システム {{$title?'('.$title.')':''}}</title>
@vite(['resources/js/app.js'])
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
    /* カスタムスタイルの追加 */
    .card {
        width: 100%;
        max-width: 600px; /* Allow card to be wider on desktop, but still responsive */
    }

    body {
        background-color: #f3ecde;
        background-image: url(/images/image.jpg);
        background-repeat: repeat-y;
        background-size: cover;
        background-attachment: fixed; /* Keep background fixed while scrolling */
    }

    @media (max-width: 576px) {
        .card {
            padding: 1.5rem !important;
        }
    }
</style>
<link rel="apple-touch-icon" sizes="180x180" href="/images/icon-1.png">
<link rel="icon" type="image/png" href="/images/icon-1.png">
<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-JJZ5QS2EP4"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'G-JJZ5QS2EP4');
</script>
<meta property="og:title" content="{{ url()->current() }}/">
<meta property="og:type" content="website">
<meta property="og:url" content="{{ url()->current() }}/">
<meta property="og:image" content="{{ url()->current() }}/images/image-s.png??{{time()}}">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:site" content="manabu.start-line-system.com">
<meta name="twitter:creator" content="@toshiaki_taoka">
<meta name="twitter:title" content="{{e(config('app.name'))??''}}">
<meta name="twitter:description" content="{{e(config('app.name'))??''}}">
<meta property="twitter:image" content="{{ url()->current() }}/images/image-s.png??{{time()}}">
