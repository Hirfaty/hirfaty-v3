<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Names are stored per locale; the slug is derived once from the French
     * name and stays stable across locales because it is the URL key.
     *
     * Purely additive: `firstOrCreate` means dropping an entry from either
     * array below stops *new* environments creating it, but does nothing to a
     * database that already has the row. Retiring a category from a live
     * environment is therefore a deliberate deletion, not a re-seed — and it
     * nulls `category_id` on every product and store pointing at it, so it is
     * not something this seeder should ever do implicitly.
     *
     * @var array<string, array{slug?: string, fr: string, ar: string, en: string}>
     */
    private const MAINS = [
        'poterie' => [
            'fr' => 'Poterie & Céramique',
            'ar' => 'الفخار والخزف',
            'en' => 'Pottery & Ceramics',
        ],
        'maroquinerie' => [
            'fr' => 'Maroquinerie',
            'ar' => 'المصنوعات الجلدية',
            'en' => 'Leather Goods',
        ],
        /*
         * Slugs stay `textiles-tissage` and `lanterne-marocaine` below,
         * because `Str::slug` is applied to the *French* name — so renaming
         * these two here would hand a fresh database different slugs from
         * every existing one, and the homepage tiles in
         * `ExploreByCategories` match on those fragments. The rename for
         * already-seeded databases lives in
         * `2026_09_14_140000_rename_and_retire_categories`, which keeps the
         * slugs; these entries are pinned to match it.
         */
        'table' => [
            'slug' => 'textiles-tissage',
            'fr' => 'Art de la table & célébrations',
            'ar' => 'فن المائدة والاحتفالات',
            'en' => 'Tableware & Celebrations',
        ],
        'bois' => [
            'fr' => 'Bois & Menuiserie',
            'ar' => 'الخشب والنجارة',
            'en' => 'Wood & Carpentry',
        ],
        'peinture' => [
            'fr' => 'Peinture & Art',
            'ar' => 'الرسم والفن',
            'en' => 'Painting & Art',
        ],
        'luminaires' => [
            'slug' => 'lanterne-marocaine',
            'fr' => 'Luminaires & dinanderie d\'art',
            'ar' => 'الإنارة والنحاسيات الفنية',
            'en' => 'Lighting & Art Metalwork',
        ],
        'textiles' => [
            'fr' => 'Textiles & Tapis',
            'ar' => 'المنسوجات والزرابي',
            'en' => 'Textiles & Rugs',
        ],
        'bijoux' => [
            'fr' => 'Bijoux & Argenterie',
            'ar' => 'المجوهرات والفضيات',
            'en' => 'Jewelry & Silverware',
        ],
    ];

    /**
     * Subcategories, keyed the same way as `MAINS` above. `parents` names one
     * or more of those keys — most subcategories sit under a single craft,
     * but a handful genuinely belong under two or three (a teapot is both
     * pottery and tableware; a leather pouf is both maroquinerie and
     * textiles), which is what the `category_parent` pivot exists for. See
     * `CategoryTaxonomyShapeTest` for the shape this is meant to keep.
     *
     * @var array<string, array{fr: string, ar: string, en: string, parents: array<int, string>}>
     */
    private const SUBS = [
        // ── Poterie & Céramique ────────────────────────────────────
        'tajines_decoratifs' => ['fr' => 'Tajines décoratifs', 'ar' => 'الطواجن الزخرفية', 'en' => 'Decorative Tagines', 'parents' => ['poterie']],
        'vaisselle_ceramique' => ['fr' => 'Vaisselle en céramique', 'ar' => 'أواني السيراميك', 'en' => 'Ceramic Tableware', 'parents' => ['poterie', 'table']],
        'zellige_mosaique' => ['fr' => 'Zellige & mosaïque', 'ar' => 'الزليج والفسيفساء', 'en' => 'Zellige & Mosaic', 'parents' => ['poterie', 'luminaires']],
        'poteries_safi' => ['fr' => 'Poteries de Safi', 'ar' => 'فخار آسفي', 'en' => 'Safi Pottery', 'parents' => ['poterie']],
        'poteries_fes' => ['fr' => 'Poteries de Fès', 'ar' => 'فخار فاس', 'en' => 'Fez Pottery', 'parents' => ['poterie']],
        'vases_terre_cuite' => ['fr' => 'Vases en terre cuite', 'ar' => 'أواني من الطين', 'en' => 'Terracotta Vases', 'parents' => ['poterie']],
        'bols_plats' => ['fr' => 'Bols & plats', 'ar' => 'أطباق وصحون', 'en' => 'Bowls & Plates', 'parents' => ['poterie', 'table']],
        'theieres_services_the' => ['fr' => 'Théières & services à thé', 'ar' => 'أباريق وأطقم الشاي', 'en' => 'Teapots & Tea Sets', 'parents' => ['poterie', 'table', 'luminaires']],
        'ceramique_peinte' => ['fr' => 'Céramique peinte à la main', 'ar' => 'سيراميك مرسوم يدويًا', 'en' => 'Hand-painted Ceramics', 'parents' => ['poterie', 'peinture']],
        'cendriers_bibelots' => ['fr' => 'Cendriers & bibelots', 'ar' => 'منافض السجائر والتحف', 'en' => 'Ashtrays & Trinkets', 'parents' => ['poterie']],
        'carreaux_decoratifs' => ['fr' => 'Carreaux décoratifs', 'ar' => 'بلاط زخرفي', 'en' => 'Decorative Tiles', 'parents' => ['poterie']],

        // ── Maroquinerie ────────────────────────────────────────────
        'sacs_main' => ['fr' => 'Sacs à main', 'ar' => 'حقائب يد', 'en' => 'Handbags', 'parents' => ['maroquinerie']],
        'ceintures_cuir' => ['fr' => 'Ceintures en cuir', 'ar' => 'أحزمة جلدية', 'en' => 'Leather Belts', 'parents' => ['maroquinerie']],
        'babouches' => ['fr' => 'Babouches', 'ar' => 'بلغة مغربية', 'en' => 'Babouches', 'parents' => ['maroquinerie']],
        'portefeuilles' => ['fr' => 'Portefeuilles', 'ar' => 'محافظ جلدية', 'en' => 'Wallets', 'parents' => ['maroquinerie']],
        'sacoches_besaces' => ['fr' => 'Sacoches & besaces', 'ar' => 'حقائب وأكياس كتف', 'en' => 'Satchels & Crossbody Bags', 'parents' => ['maroquinerie']],
        'poufs_cuir' => ['fr' => 'Poufs en cuir', 'ar' => 'مقاعد جلدية (بوف)', 'en' => 'Leather Poufs', 'parents' => ['maroquinerie', 'textiles']],
        'tapis_cuir' => ['fr' => 'Tapis en cuir', 'ar' => 'سجاد جلدي', 'en' => 'Leather Rugs', 'parents' => ['maroquinerie', 'textiles']],
        'coussins_cuir' => ['fr' => 'Coussins en cuir', 'ar' => 'وسائد جلدية', 'en' => 'Leather Cushions', 'parents' => ['maroquinerie', 'textiles']],
        'etuis_pochettes' => ['fr' => 'Étuis & pochettes', 'ar' => 'أغلفة وحقائب صغيرة', 'en' => 'Cases & Pouches', 'parents' => ['maroquinerie']],
        'accessoires_cuir_grave' => ['fr' => 'Accessoires en cuir gravé', 'ar' => 'إكسسوارات جلدية منقوشة', 'en' => 'Engraved Leather Accessories', 'parents' => ['maroquinerie', 'bijoux']],
        'sandales_artisanales' => ['fr' => 'Sandales artisanales', 'ar' => 'صنادل حرفية', 'en' => 'Handmade Sandals', 'parents' => ['maroquinerie']],

        // ── Art de la table & célébrations ─────────────────────────
        // (vaisselle_ceramique, bols_plats, theieres_services_the above)
        'nappes_serviettes_brodees' => ['fr' => 'Nappes & serviettes brodées', 'ar' => 'مفارش ومناديل مطرزة', 'en' => 'Embroidered Tablecloths & Napkins', 'parents' => ['table', 'textiles']],
        'plateaux_service' => ['fr' => 'Plateaux de service', 'ar' => 'صواني التقديم', 'en' => 'Serving Trays', 'parents' => ['table', 'luminaires']],
        'corbeilles_paniers' => ['fr' => 'Corbeilles & paniers', 'ar' => 'سلال وقفف', 'en' => 'Baskets', 'parents' => ['table', 'textiles']],
        'verres_the_peints' => ['fr' => 'Verres à thé peints', 'ar' => 'كؤوس الشاي المرسومة', 'en' => 'Painted Tea Glasses', 'parents' => ['table', 'peinture']],
        'decorations_mariage' => ['fr' => 'Décorations de mariage', 'ar' => 'زينة الأعراس', 'en' => 'Wedding Decorations', 'parents' => ['table']],
        'coffrets_cadeaux' => ['fr' => 'Coffrets cadeaux', 'ar' => 'علب الهدايا', 'en' => 'Gift Boxes', 'parents' => ['table', 'bois']],
        'bougeoirs_chandeliers' => ['fr' => 'Bougeoirs & chandeliers', 'ar' => 'شمعدانات', 'en' => 'Candle Holders', 'parents' => ['table', 'luminaires']],
        'ustensiles_cuisine_bois' => ['fr' => 'Ustensiles de cuisine en bois', 'ar' => 'أدوات مطبخ خشبية', 'en' => 'Wooden Kitchen Utensils', 'parents' => ['table', 'bois']],

        // ── Bois & Menuiserie ───────────────────────────────────────
        // (coffrets_cadeaux, ustensiles_cuisine_bois above)
        'meubles_marocains' => ['fr' => 'Meubles marocains', 'ar' => 'أثاث مغربي', 'en' => 'Moroccan Furniture', 'parents' => ['bois']],
        'tables_basses_sculptees' => ['fr' => 'Tables basses sculptées', 'ar' => 'طاولات منخفضة منحوتة', 'en' => 'Carved Coffee Tables', 'parents' => ['bois']],
        'coffrets_bois' => ['fr' => 'Coffrets en bois', 'ar' => 'صناديق خشبية', 'en' => 'Wooden Boxes', 'parents' => ['bois']],
        'miroirs_bois_sculpte' => ['fr' => 'Miroirs en bois sculpté', 'ar' => 'مرايا خشبية منحوتة', 'en' => 'Carved Wooden Mirrors', 'parents' => ['bois', 'peinture']],
        'portes_moucharabiehs' => ['fr' => 'Portes & moucharabiehs', 'ar' => 'أبواب ومشربيات', 'en' => 'Doors & Moucharabiehs', 'parents' => ['bois']],
        'jouets_bois' => ['fr' => 'Jouets en bois', 'ar' => 'ألعاب خشبية', 'en' => 'Wooden Toys', 'parents' => ['bois']],
        'cadres_bois' => ['fr' => 'Cadres en bois', 'ar' => 'إطارات خشبية', 'en' => 'Wooden Frames', 'parents' => ['bois', 'peinture']],
        'etageres_rangements' => ['fr' => 'Étagères & rangements', 'ar' => 'أرفف ووحدات تخزين', 'en' => 'Shelves & Storage', 'parents' => ['bois']],
        'marqueterie_essaouira' => ['fr' => 'Marqueterie de Essaouira', 'ar' => 'التطعيم الخشبي لالصويرة', 'en' => 'Essaouira Marquetry', 'parents' => ['bois']],
        'coffrets_bijoux' => ['fr' => 'Coffrets à bijoux', 'ar' => 'علب المجوهرات', 'en' => 'Jewelry Boxes', 'parents' => ['bois', 'bijoux']],

        // ── Peinture & Art ──────────────────────────────────────────
        // (ceramique_peinte, verres_the_peints, miroirs_bois_sculpte, cadres_bois above)
        'tableaux_peints_main' => ['fr' => 'Tableaux peints à la main', 'ar' => 'لوحات مرسومة يدويًا', 'en' => 'Hand-painted Paintings', 'parents' => ['peinture']],
        'calligraphie_arabe' => ['fr' => 'Calligraphie arabe', 'ar' => 'الخط العربي', 'en' => 'Arabic Calligraphy', 'parents' => ['peinture']],
        'portraits_commande' => ['fr' => 'Portraits sur commande', 'ar' => 'بورتريهات حسب الطلب', 'en' => 'Custom Portraits', 'parents' => ['peinture']],
        'art_mural_berbere' => ['fr' => 'Art mural berbère', 'ar' => 'فن الجدران الأمازيغي', 'en' => 'Berber Wall Art', 'parents' => ['peinture']],
        'toiles_abstraites' => ['fr' => 'Toiles abstraites', 'ar' => 'لوحات تجريدية', 'en' => 'Abstract Canvases', 'parents' => ['peinture']],
        'illustrations_gravures' => ['fr' => 'Illustrations & gravures', 'ar' => 'رسوم توضيحية ومنقوشات', 'en' => 'Illustrations & Engravings', 'parents' => ['peinture']],

        // ── Luminaires & dinanderie d'art ───────────────────────────
        // (zellige_mosaique, theieres_services_the, plateaux_service, bougeoirs_chandeliers above)
        'lanternes_marocaines' => ['fr' => 'Lanternes marocaines', 'ar' => 'فوانيس مغربية', 'en' => 'Moroccan Lanterns', 'parents' => ['luminaires']],
        'suspensions_fer_forge' => ['fr' => 'Suspensions en fer forgé', 'ar' => 'ثريات من الحديد المطروق', 'en' => 'Wrought Iron Pendant Lights', 'parents' => ['luminaires']],
        'appliques_murales' => ['fr' => 'Appliques murales', 'ar' => 'مصابيح جدارية', 'en' => 'Wall Sconces', 'parents' => ['luminaires']],
        'objets_cuivre_grave' => ['fr' => 'Objets en cuivre gravé', 'ar' => 'قطع نحاسية منقوشة', 'en' => 'Engraved Copper Objects', 'parents' => ['luminaires', 'bijoux']],
        'lampes_chevet' => ['fr' => 'Lampes de chevet artisanales', 'ar' => 'مصابيح سرير حرفية', 'en' => 'Handmade Bedside Lamps', 'parents' => ['luminaires']],
        'miroirs_metal_cisele' => ['fr' => 'Miroirs en métal ciselé', 'ar' => 'مرايا معدنية منقوشة', 'en' => 'Chiseled Metal Mirrors', 'parents' => ['luminaires', 'bijoux']],

        // ── Textiles & Tapis ────────────────────────────────────────
        // (poufs_cuir, tapis_cuir, coussins_cuir, nappes_serviettes_brodees, corbeilles_paniers above)
        'tapis_berberes' => ['fr' => 'Tapis berbères', 'ar' => 'زرابي أمازيغية', 'en' => 'Berber Rugs', 'parents' => ['textiles']],
        'kilims' => ['fr' => 'Kilims', 'ar' => 'الكليم', 'en' => 'Kilims', 'parents' => ['textiles']],
        'caftans_takchitas' => ['fr' => 'Caftans & takchitas', 'ar' => 'قفاطين وتكشيطة', 'en' => 'Kaftans & Takchitas', 'parents' => ['textiles']],
        'djellabas_artisanales' => ['fr' => 'Djellabas artisanales', 'ar' => 'جلابيب حرفية', 'en' => 'Handmade Djellabas', 'parents' => ['textiles']],
        'foutas_serviettes_hammam' => ['fr' => 'Foutas & serviettes hammam', 'ar' => 'فوطة ومناشف الحمام', 'en' => 'Foutas & Hammam Towels', 'parents' => ['textiles']],
        'echarpes_soie' => ['fr' => 'Écharpes en soie', 'ar' => 'أوشحة حريرية', 'en' => 'Silk Scarves', 'parents' => ['textiles']],

        // ── Bijoux & Argenterie ─────────────────────────────────────
        // (accessoires_cuir_grave, coffrets_bijoux, objets_cuivre_grave, miroirs_metal_cisele above)
        'bijoux_argent' => ['fr' => 'Bijoux en argent', 'ar' => 'مجوهرات فضية', 'en' => 'Silver Jewelry', 'parents' => ['bijoux']],
        'colliers_berberes' => ['fr' => 'Colliers berbères', 'ar' => 'قلائد أمازيغية', 'en' => 'Berber Necklaces', 'parents' => ['bijoux']],
        'bracelets_graves' => ['fr' => 'Bracelets gravés', 'ar' => 'أساور منقوشة', 'en' => 'Engraved Bracelets', 'parents' => ['bijoux']],
        'boucles_oreilles' => ['fr' => 'Boucles d\'oreilles artisanales', 'ar' => 'أقراط حرفية', 'en' => 'Handmade Earrings', 'parents' => ['bijoux']],
        'bagues_argent' => ['fr' => 'Bagues en argent', 'ar' => 'خواتم فضية', 'en' => 'Silver Rings', 'parents' => ['bijoux']],
        'broches_fibules' => ['fr' => 'Broches & fibules', 'ar' => 'بروشات وخلالات (تيزرزيت)', 'en' => 'Brooches & Fibulas', 'parents' => ['bijoux']],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        /** @var array<string, Category> */
        $byKey = [];

        foreach (self::MAINS as $key => $entry) {
            $byKey[$key] = $this->firstOrCreateCategory($entry);
        }

        foreach (self::SUBS as $key => $entry) {
            $category = $this->firstOrCreateCategory($entry);
            $byKey[$key] = $category;

            /*
             * Only on the category's own creation, not on every re-run: an
             * admin may have since reassigned a subcategory's parents by hand
             * in /admin/categories, and a reseed silently overwriting that
             * would defeat the point of `firstOrCreate` being purely
             * additive. A category that already existed before this run is
             * left exactly as the admin left it.
             */
            if (! $category->wasRecentlyCreated) {
                continue;
            }

            $parentIds = collect($entry['parents'])
                ->map(fn (string $parentKey) => $byKey[$parentKey]->id)
                ->all();

            $category->parents()->sync($parentIds);
        }

        // `parents()->sync()` writes to the pivot table directly and fires no
        // model event, so the cache `Category::booted()` clears on `saved`
        // never runs for it — without this the header would keep serving
        // whatever tree existed before this seeder ran, for up to an hour.
        Category::forgetNavCache();
    }

    /**
     * @param  array{slug?: string, fr: string, ar: string, en: string}  $entry
     */
    private function firstOrCreateCategory(array $entry): Category
    {
        // Slug derives from the canonical locale, matching CategoryController,
        // unless the entry pins one because its label has since been renamed.
        $slug = $entry['slug'] ?? Str::slug($entry[Category::FALLBACK_LOCALE]);
        $names = Arr::only($entry, Category::LOCALES);

        return Category::firstOrCreate(
            ['slug' => $slug],
            ['name' => $names],
        );
    }
}
