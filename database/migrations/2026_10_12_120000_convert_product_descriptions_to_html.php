<?php

use App\Services\HtmlSanitizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Product descriptions move from a plain textarea to the rich-text editor, and
 * are now rendered as HTML. Each existing description becomes one paragraph
 * per line it was typed with — the editor's own shape for "Enter" — escaped,
 * so the text reads exactly as it was written and nothing in it is taken for
 * markup.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('products')
            ->whereNotNull('description')
            ->orderBy('id')
            ->select(['id', 'description'])
            ->chunkById(200, function ($products) {
                foreach ($products as $product) {
                    // Already editor HTML: a re-run must not escape it twice.
                    if (str_starts_with(ltrim($product->description), '<')) {
                        continue;
                    }

                    DB::table('products')->where('id', $product->id)->update([
                        'description' => $this->toHtml($product->description),
                    ]);
                }
            });
    }

    public function down(): void
    {
        DB::table('products')
            ->whereNotNull('description')
            ->orderBy('id')
            ->select(['id', 'description'])
            ->chunkById(200, function ($products) {
                foreach ($products as $product) {
                    DB::table('products')->where('id', $product->id)->update([
                        'description' => HtmlSanitizer::toText($product->description),
                    ]);
                }
            });
    }

    private function toHtml(string $text): ?string
    {
        if (trim($text) === '') {
            return null;
        }

        $lines = preg_split('/\r\n|\r|\n/', rtrim($text));

        return implode('', array_map(
            // Quotes left alone, as the editor leaves them — only what could
            // open a tag or an entity is escaped, so a search still matches.
            fn (string $line) => '<p>'.htmlspecialchars($line, ENT_NOQUOTES | ENT_HTML5, 'UTF-8').'</p>',
            $lines,
        ));
    }
};
