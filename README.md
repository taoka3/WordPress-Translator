# WordPress-Translator（WordPress 英訳スタジオ）とは
ローカルに落としてきたWordPressデータを同テーブルに新たなカラムを追加し英訳保存するシステム
前提条件
### ※ ローカルサーバーが設置出来る事
### ※ ローカルのDBサーバーにWordPressデータを流し込んでいる事
### ※ LM Studio を使用できAIモデルを読み込んでいるAPIサーバーが稼働している事
### ※ database/database.sqliteの設置

## 使用方法
1. .envを設定
2. ローカルサーバーを起動
3. ターミナルより`php artisan migrate`と`npm i && npm run build `を実行
4. ブラウザを開き、WordPress-TranslatorのURLを入力し移動
5. 自動英訳を開始／再開ボタンをクリックし実行
