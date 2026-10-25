<?php

namespace App\Services;

use App\Models\SystemSetting;
use App\Models\TranslationItem;
use App\Models\TranslationLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class TranslationService
{
    public function __construct(
        protected LMStudioService $lmStudio,
        protected CodePreservationService $codePreserver
    ) {}

    /**
     * Get real-time stats of the translation system.
     *
     * @return array
     */
    public function getStats(): array
    {
        $total = TranslationItem::count();
        $completed = TranslationItem::where('status', 'completed')->count();
        $pending = TranslationItem::where('status', 'pending')->count();
        $failed = TranslationItem::where('status', 'failed')->count();
        $translating = TranslationItem::where('status', 'translating')->count();

        // Breakdown by type
        $postsTotal = TranslationItem::where('item_type', 'post')->count();
        $postsCompleted = TranslationItem::where('item_type', 'post')->where('status', 'completed')->count();

        $categoriesTotal = TranslationItem::where('item_type', 'category')->count();
        $categoriesCompleted = TranslationItem::where('item_type', 'category')->where('status', 'completed')->count();

        $tagsTotal = TranslationItem::where('item_type', 'tag')->count();
        $tagsCompleted = TranslationItem::where('item_type', 'tag')->where('status', 'completed')->count();

        $percent = $total > 0 ? round(($completed / $total) * 100, 1) : 0;
        $runnerState = SystemSetting::get('runner_state', 'idle');
        $currentTaskId = SystemSetting::get('current_task_id');
        $currentItem = $currentTaskId ? TranslationItem::find($currentTaskId) : null;

        return [
            'total' => $total,
            'completed' => $completed,
            'pending' => $pending,
            'failed' => $failed,
            'translating' => $translating,
            'percentage' => $percent,
            'runner_state' => $runnerState,
            'current_item' => $currentItem,
            'breakdown' => [
                'posts' => [
                    'total' => $postsTotal,
                    'completed' => $postsCompleted,
                    'remaining' => $postsTotal - $postsCompleted,
                ],
                'categories' => [
                    'total' => $categoriesTotal,
                    'completed' => $categoriesCompleted,
                    'remaining' => $categoriesTotal - $categoriesCompleted,
                ],
                'tags' => [
                    'total' => $tagsTotal,
                    'completed' => $tagsCompleted,
                    'remaining' => $tagsTotal - $tagsCompleted,
                ],
            ],
        ];
    }

    /**
     * Sync posts, categories, and tags from WordPress MySQL  into SQLite.
     *
     * @return array{posts_synced: int, categories_synced: int, tags_synced: int, total_in_db: int}
     */
    public function syncFromWordPress(): array
    {
        $postsSynced = 0;
        $categoriesSynced = 0;
        $tagsSynced = 0;
        $now = now()->toDateTimeString();

        // 1. Sync Published Posts in batches using UPSERT
        DB::connection('wp_mysql')
            ->table('wp_posts')
            ->where('post_type', 'post')
            ->where('post_status', 'publish')
            ->select('ID', 'post_title', 'post_content', 'post_title_en', 'post_content_en', 'post_translated_at')
            ->orderBy('ID', 'asc')
            ->chunk(150, function ($posts) use (&$postsSynced, $now) {
                $batch = [];
                foreach ($posts as $p) {
                    $isCompleted = !empty($p->post_title_en) && !empty($p->post_content_en);
                    $batch[] = [
                        'item_type' => 'post',
                        'source_id' => $p->ID,
                        'title_ja' => $p->post_title,
                        'title_en' => $p->post_title_en,
                        'content_ja' => $p->post_content,
                        'content_en' => $p->post_content_en,
                        'status' => $isCompleted ? 'completed' : 'pending',
                        'translated_at' => $p->post_translated_at,
                        'synced_to_wp_at' => $isCompleted ? $p->post_translated_at : null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                DB::connection('sqlite')->table('translation_items')->upsert(
                    $batch,
                    ['item_type', 'source_id'],
                    ['title_ja', 'content_ja', 'updated_at']
                );
                $postsSynced += count($batch);
            });

        // 2. Sync Categories
        $wpCategories = DB::connection('wp_mysql')
            ->table('wp_terms as t')
            ->join('wp_term_taxonomy as tt', 't.term_id', '=', 'tt.term_id')
            ->where('tt.taxonomy', 'category')
            ->select('t.term_id', 't.name', 't.name_en', 't.term_translated_at')
            ->get();

        $catBatch = [];
        $directCatUpdates = [];
        foreach ($wpCategories as $cat) {
            $nameJa = $cat->name;
            $nameEn = $cat->name_en;

            // 英字、数字、記号の組み合わせの文字ならLM Studioを介さずenへ設定
            if (empty($nameEn) && $this->isAlphaNumericSymbolOnly($nameJa)) {
                $nameEn = mb_convert_kana(trim($nameJa), 'as', 'UTF-8');
                $directCatUpdates[] = [
                    'term_id' => $cat->term_id,
                    'name_en' => $nameEn,
                ];
            }

            $isCompleted = !empty($nameEn);
            $catBatch[] = [
                'item_type' => 'category',
                'source_id' => $cat->term_id,
                'title_ja' => $cat->name,
                'title_en' => $nameEn,
                'content_ja' => null,
                'content_en' => null,
                'status' => $isCompleted ? 'completed' : 'pending',
                'translated_at' => $isCompleted ? ($cat->term_translated_at ?: $now) : null,
                'synced_to_wp_at' => $isCompleted ? ($cat->term_translated_at ?: $now) : null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // WordPress MySQLの英数記号カテゴリへ直接反映
        foreach ($directCatUpdates as $update) {
            DB::connection('wp_mysql')
                ->table('wp_terms')
                ->where('term_id', $update['term_id'])
                ->update([
                    'name_en' => $update['name_en'],
                    'term_translated_at' => $now,
                ]);
        }

        if (!empty($catBatch)) {
            DB::connection('sqlite')->table('translation_items')->upsert(
                $catBatch,
                ['item_type', 'source_id'],
                ['title_ja', 'title_en', 'status', 'translated_at', 'synced_to_wp_at', 'updated_at']
            );
            $categoriesSynced = count($catBatch);
        }

        // 3. Sync Tags (in chunks)
        DB::connection('wp_mysql')
            ->table('wp_terms as t')
            ->join('wp_term_taxonomy as tt', 't.term_id', '=', 'tt.term_id')
            ->where('tt.taxonomy', 'post_tag')
            ->select('t.term_id', 't.name', 't.name_en', 't.term_translated_at')
            ->orderBy('t.term_id', 'asc')
            ->chunk(200, function ($tags) use (&$tagsSynced, $now) {
                $tagBatch = [];
                $directTagUpdates = [];
                foreach ($tags as $tag) {
                    $nameJa = $tag->name;
                    $nameEn = $tag->name_en;

                    // 英字、数字、記号の組み合わせの文字ならLM Studioを介さずenへ設定
                    if (empty($nameEn) && $this->isAlphaNumericSymbolOnly($nameJa)) {
                        $nameEn = mb_convert_kana(trim($nameJa), 'as', 'UTF-8');
                        $directTagUpdates[] = [
                            'term_id' => $tag->term_id,
                            'name_en' => $nameEn,
                        ];
                    }

                    $isCompleted = !empty($nameEn);
                    $tagBatch[] = [
                        'item_type' => 'tag',
                        'source_id' => $tag->term_id,
                        'title_ja' => $tag->name,
                        'title_en' => $nameEn,
                        'content_ja' => null,
                        'content_en' => null,
                        'status' => $isCompleted ? 'completed' : 'pending',
                        'translated_at' => $isCompleted ? ($tag->term_translated_at ?: $now) : null,
                        'synced_to_wp_at' => $isCompleted ? ($tag->term_translated_at ?: $now) : null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                // WordPress MySQLの英数記号タグへ直接反映
                foreach ($directTagUpdates as $update) {
                    DB::connection('wp_mysql')
                        ->table('wp_terms')
                        ->where('term_id', $update['term_id'])
                        ->update([
                            'name_en' => $update['name_en'],
                            'term_translated_at' => $now,
                        ]);
                }

                DB::connection('sqlite')->table('translation_items')->upsert(
                    $tagBatch,
                    ['item_type', 'source_id'],
                    ['title_ja', 'title_en', 'status', 'translated_at', 'synced_to_wp_at', 'updated_at']
                );
                $tagsSynced += count($tagBatch);
            });

        // 既存のpendingデータに対しても英数記号の直接反映を実行
        $batchResult = $this->batchUpdateAlphaNumericItems();

        $totalInDb = TranslationItem::count();

        $directMsg = $batchResult['total'] > 0 ? " (英数記号 {$batchResult['total']}件 を直接反映)" : "";
        TranslationLog::log(
            "WordPressから同期完了: 記事 {$postsSynced}件, カテゴリ {$categoriesSynced}件, タグ {$tagsSynced}件 (総キュー数: {$totalInDb}件){$directMsg}",
            'info'
        );

        return [
            'posts_synced' => $postsSynced,
            'categories_synced' => $categoriesSynced,
            'tags_synced' => $tagsSynced,
            'total_in_db' => $totalInDb,
            'alphanumeric_direct_updated' => $batchResult['total'],
        ];
    }

    /**
     * Determine if a text consists only of letters, numbers, symbols, and whitespace
     * (i.e. ASCII printable characters without Japanese/CJK characters).
     *
     * @param string|null $text
     * @return bool
     */
    public function isAlphaNumericSymbolOnly(?string $text): bool
    {
        if ($text === null) {
            return false;
        }

        // HTMLエンティティ（&amp;, &quot;, &#038; 等）をデコード
        $decoded = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $trimmed = trim($decoded);
        if ($trimmed === '') {
            return false;
        }

        // 全角英数字および全角スペースを半角に正規化
        $normalized = mb_convert_kana($trimmed, 'as', 'UTF-8');

        // 日本語文字（ひらがな、カタカナ、漢字、CJK句読点等）が含まれている場合はfalse
        // \x{3040}-\x{309F}: ひらがな
        // \x{30A0}-\x{30FF}: カタカナ
        // \x{4E00}-\x{9FAF}: 漢字 (CJK統合漢字)
        // \x{3400}-\x{4DBF}: CJK統合漢字拡張A
        // \x{3000}-\x{303F}: CJKの記号および句読点
        if (preg_match('/[\x{3040}-\x{309F}\x{30A0}-\x{30FF}\x{4E00}-\x{9FAF}\x{3400}-\x{4DBF}\x{3000}-\x{303F}]/u', $normalized)) {
            return false;
        }

        // 印字可能ASCII文字 (0x20〜0x7E) および改行・タブ等の空白文字のみで構成されているか
        if (!preg_match('/^[\x20-\x7E\r\n\t]+$/', $normalized)) {
            return false;
        }

        // 空白文字以外の印字可能文字（英字、数字、記号: 0x21〜0x7E）が少なくとも1文字含まれているか
        return preg_match('/[\x21-\x7E]/', $normalized) === 1;
    }

    /**
     * Batch update all pending/failed items consisting only of alphanumeric & symbol characters
     * directly into the en column without going through LM Studio.
     * Uses memory-safe chunking and query builder to support thousands of terms.
     *
     * @return array{categories: int, tags: int, posts: int, total: int}
     */
    public function batchUpdateAlphaNumericItems(): array
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(300);

        $categoriesUpdated = 0;
        $tagsUpdated = 0;
        $postsUpdated = 0;
        $now = now()->toDateTimeString();

        // 1. カテゴリ＆タグの英数記号一括反映
        // メモリ枯渇を防ぐため必要なカラムのみ取得し chunkById で200件ずつ処理
        DB::connection('sqlite')
            ->table('translation_items')
            ->whereIn('item_type', ['category', 'tag'])
            ->where(function ($q) {
                $q->whereIn('status', ['pending', 'failed'])
                  ->orWhereNull('title_en')
                  ->orWhere('title_en', '');
            })
            ->select('id', 'source_id', 'item_type', 'title_ja')
            ->orderBy('id', 'asc')
            ->chunkById(200, function ($items) use (&$categoriesUpdated, &$tagsUpdated, $now) {
                $sqliteUpdates = [];
                $wpTermUpdates = [];

                foreach ($items as $item) {
                    if ($this->isAlphaNumericSymbolOnly($item->title_ja)) {
                        $nameEn = mb_convert_kana(trim($item->title_ja), 'as', 'UTF-8');
                        $sqliteUpdates[] = [
                            'id' => $item->id,
                            'title_en' => $nameEn,
                        ];
                        $wpTermUpdates[] = [
                            'term_id' => $item->source_id,
                            'name_en' => $nameEn,
                        ];

                        if ($item->item_type === 'category') {
                            $categoriesUpdated++;
                        } else {
                            $tagsUpdated++;
                        }
                    }
                }

                // SQLite更新
                foreach ($sqliteUpdates as $up) {
                    DB::connection('sqlite')
                        ->table('translation_items')
                        ->where('id', $up['id'])
                        ->update([
                            'title_en' => $up['title_en'],
                            'status' => 'completed',
                            'error_message' => null,
                            'translated_at' => $now,
                            'synced_to_wp_at' => $now,
                            'updated_at' => $now,
                        ]);
                }

                // WordPress MySQL更新
                foreach ($wpTermUpdates as $wpUp) {
                    DB::connection('wp_mysql')
                        ->table('wp_terms')
                        ->where('term_id', $wpUp['term_id'])
                        ->update([
                            'name_en' => $wpUp['name_en'],
                            'term_translated_at' => $now,
                        ]);
                }
            });

        // 2. 記事の英数記号一括反映 (タイトル・本文ともに英数記号または本文空)
        // 記事本文のメモリ消費を抑えるため chunkById(50) で軽量処理
        DB::connection('sqlite')
            ->table('translation_items')
            ->where('item_type', 'post')
            ->where(function ($q) {
                $q->whereIn('status', ['pending', 'failed'])
                  ->orWhereNull('title_en')
                  ->orWhere('title_en', '');
            })
            ->select('id', 'source_id', 'title_ja', 'content_ja')
            ->orderBy('id', 'asc')
            ->chunkById(50, function ($posts) use (&$postsUpdated, $now) {
                foreach ($posts as $item) {
                    $titleOnly = $this->isAlphaNumericSymbolOnly($item->title_ja);
                    $contentOnly = empty(trim($item->content_ja ?? '')) || $this->isAlphaNumericSymbolOnly($item->content_ja);

                    if ($titleOnly && $contentOnly) {
                        $titleEn = mb_convert_kana(trim($item->title_ja ?? ''), 'as', 'UTF-8');
                        $contentEn = $item->content_ja ?? '';

                        DB::connection('sqlite')
                            ->table('translation_items')
                            ->where('id', $item->id)
                            ->update([
                                'title_en' => $titleEn,
                                'content_en' => $contentEn,
                                'status' => 'completed',
                                'error_message' => null,
                                'translated_at' => $now,
                                'synced_to_wp_at' => $now,
                                'updated_at' => $now,
                            ]);

                        DB::connection('wp_mysql')
                            ->table('wp_posts')
                            ->where('ID', $item->source_id)
                            ->update([
                                'post_title_en' => $titleEn,
                                'post_content_en' => $contentEn,
                                'post_translated_at' => $now,
                            ]);

                        $postsUpdated++;
                    }
                }
            });

        $total = $categoriesUpdated + $tagsUpdated + $postsUpdated;

        if ($total > 0) {
            TranslationLog::log(
                "英数記号の直接反映完了: 合計 {$total}件 (カテゴリ: {$categoriesUpdated}件, タグ: {$tagsUpdated}件, 記事: {$postsUpdated}件) をLM Studioを介さずenカラムへ更新しました",
                'success'
            );
        }

        return [
            'categories' => $categoriesUpdated,
            'tags' => $tagsUpdated,
            'posts' => $postsUpdated,
            'total' => $total,
        ];
    }

    /**
     * Translate a single TranslationItem.
     *
     * @param TranslationItem $item
     * @param string|null $modelOverride
     * @return TranslationItem
     * @throws Exception
     */
    public function translateItem(TranslationItem $item, ?string $modelOverride = null): TranslationItem
    {
        $item->status = 'translating';
        $item->attempts += 1;
        $item->save();

        SystemSetting::set('current_task_id', $item->id);

        $start = microtime(true);

        try {
            if ($item->item_type === 'post') {
                // 1. Translate Title (英数記号ならLM Studioを介さず直接反映)
                $titleJa = trim($item->title_ja ?? '');
                $titleEn = '';
                $titleBypassed = false;
                if (!empty($titleJa)) {
                    if ($this->isAlphaNumericSymbolOnly($titleJa)) {
                        $titleEn = mb_convert_kana($titleJa, 'as', 'UTF-8');
                        $titleBypassed = true;
                    } else {
                        $titleEn = $this->lmStudio->translate($titleJa, 'title', $modelOverride);
                    }
                }

                // 2. Protect Code in Content (Rule 9)
                $protectResult = $this->codePreserver->protect($item->content_ja);
                $protectedContent = $protectResult['text'];
                $codeBlocks = $protectResult['blocks'];
                $item->code_blocks_count = $protectResult['count'];

                // 3. Translate Content (英数記号のみならLM Studioを介さず直接反映)
                $contentEn = '';
                $contentBypassed = false;
                if (!empty(trim($protectedContent))) {
                    if ($this->isAlphaNumericSymbolOnly($protectedContent)) {
                        $contentEn = $protectedContent;
                        $contentBypassed = true;
                    } else {
                        $contentEn = $this->translateContentInChunks($protectedContent, $modelOverride);
                    }
                }

                // 4. Restore Code Blocks byte-for-byte
                $contentEn = $this->codePreserver->restore($contentEn, $codeBlocks);

                // 5. Update SQLite
                $item->title_en = $titleEn;
                $item->content_en = $contentEn;
                $item->status = 'completed';
                $item->error_message = null;
                $item->translated_at = now();
                $item->save();

                // 6. Sync to WordPress MySQL (Rule 7 & 8)
                DB::connection('wp_mysql')
                    ->table('wp_posts')
                    ->where('ID', $item->source_id)
                    ->update([
                        'post_title_en' => $titleEn,
                        'post_content_en' => $contentEn,
                        'post_translated_at' => now(),
                    ]);

                $item->synced_to_wp_at = now();
                $item->save();

                $duration = round(microtime(true) - $start, 2);
                $bypassLabels = [];
                if ($titleBypassed) $bypassLabels[] = 'タイトル英数バイパス';
                if ($contentBypassed) $bypassLabels[] = '本文英数バイパス';
                $bypassNotice = !empty($bypassLabels) ? ' [' . implode(', ', $bypassLabels) . ']' : '';

                TranslationLog::log(
                    "記事 [ID: {$item->source_id}] 「{$item->title_ja}」 の英訳が完了しました ({$duration}秒{$bypassNotice}, コード保護: {$item->code_blocks_count}箇所)",
                    'success',
                    $item->id
                );
            } else {
                // Category or Tag name translation (英数記号ならLM Studioを介さず直接enへ更新)
                $nameJa = trim($item->title_ja ?? '');
                $nameEn = '';
                $isDirect = false;

                if (!empty($nameJa)) {
                    if ($this->isAlphaNumericSymbolOnly($nameJa)) {
                        $nameEn = mb_convert_kana($nameJa, 'as', 'UTF-8');
                        $isDirect = true;
                    } else {
                        $nameEn = $this->lmStudio->translate($nameJa, 'term', $modelOverride);
                    }
                }

                $item->title_en = $nameEn;
                $item->status = 'completed';
                $item->error_message = null;
                $item->translated_at = now();
                $item->save();

                // Sync to WordPress MySQL 
                DB::connection('wp_mysql')
                    ->table('wp_terms')
                    ->where('term_id', $item->source_id)
                    ->update([
                        'name_en' => $nameEn,
                        'term_translated_at' => now(),
                    ]);

                $item->synced_to_wp_at = now();
                $item->save();

                $typeName = $item->item_type === 'category' ? 'カテゴリー' : 'タグ';
                $duration = round(microtime(true) - $start, 2);
                $viaNotice = $isDirect ? ' (英数記号のためLM Studioバイパス)' : " ({$duration}秒)";
                TranslationLog::log(
                    "{$typeName} [ID: {$item->source_id}] 「{$item->title_ja}」 -> 「{$nameEn}」 の英訳が完了しました{$viaNotice}",
                    'success',
                    $item->id
                );
            }

            return $item;
        } catch (Exception $e) {
            $item->status = 'failed';
            $item->error_message = $e->getMessage();
            $item->save();

            TranslationLog::log(
                "英訳エラー [{$item->item_type} ID: {$item->source_id}]: " . $e->getMessage(),
                'error',
                $item->id,
                $e->getTraceAsString()
            );

            throw $e;
        } finally {
            if (SystemSetting::get('current_task_id') == $item->id) {
                SystemSetting::set('current_task_id', null);
            }
        }
    }

    /**
     * Safely translate content, splitting into chunks if exceptionally large.
     */
    protected function translateContentInChunks(string $text, ?string $modelOverride = null): string
    {
        $charLimit = 3500;
        if (mb_strlen($text) <= $charLimit) {
            return $this->lmStudio->translate($text, 'content', $modelOverride);
        }

        // Split by Gutenberg blocks or paragraphs
        $delimiters = ["<!-- wp:", "\n\n", "\r\n\r\n", "\n"];
        $chunks = [];
        $currentChunk = '';

        $lines = preg_split('/(\r\n|\n)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);

        foreach ($lines as $line) {
            if (mb_strlen($currentChunk . $line) > $charLimit && !empty($currentChunk)) {
                $chunks[] = $currentChunk;
                $currentChunk = $line;
            } else {
                $currentChunk .= $line;
            }
        }
        if (!empty($currentChunk)) {
            $chunks[] = $currentChunk;
        }

        $translatedChunks = [];
        foreach ($chunks as $idx => $chunk) {
            if (trim($chunk) === '') {
                $translatedChunks[] = $chunk;
                continue;
            }
            $translatedChunks[] = $this->lmStudio->translate($chunk, 'content', $modelOverride);
        }

        return implode('', $translatedChunks);
    }

    /**
     * Process next pending translation item.
     *
     * @return array{status: string, message: string, item: TranslationItem|null, stats: array}
     */
    public function processNextStep(): array
    {
        $state = SystemSetting::get('runner_state', 'idle');

        if ($state === 'paused') {
            return [
                'status' => 'paused',
                'message' => '翻訳処理は一時停止中です',
                'item' => null,
                'stats' => $this->getStats(),
            ];
        }

        if ($state === 'stopped') {
            return [
                'status' => 'stopped',
                'message' => '翻訳処理は停止されています',
                'item' => null,
                'stats' => $this->getStats(),
            ];
        }

        // Target type prioritization: categories first, then tags, then posts, or mixed
        $targetTypes = json_decode(SystemSetting::get('target_types', '["category", "tag", "post"]'), true) ?: ['category', 'tag', 'post'];

        $item = TranslationItem::whereIn('item_type', $targetTypes)
            ->whereIn('status', ['pending', 'failed'])
            ->where('attempts', '<', 5)
            ->orderByRaw("CASE WHEN item_type = 'category' THEN 1 WHEN item_type = 'tag' THEN 2 ELSE 3 END")
            ->orderBy('id', 'asc')
            ->first();

        if (!$item) {
            SystemSetting::set('runner_state', 'idle');
            return [
                'status' => 'completed',
                'message' => 'すべての対象の英訳が完了しました！',
                'item' => null,
                'stats' => $this->getStats(),
            ];
        }

        try {
            $translated = $this->translateItem($item);
            return [
                'status' => 'success',
                'message' => "{$translated->item_type} #{$translated->source_id} の英訳に成功しました",
                'item' => $translated,
                'stats' => $this->getStats(),
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => "英訳エラー: " . $e->getMessage(),
                'item' => $item,
                'stats' => $this->getStats(),
            ];
        }
    }
}
