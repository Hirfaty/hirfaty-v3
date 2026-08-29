<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The admin fills the catalogue in several passes, so a product can be
     * parked as a `draft` with incomplete details and finished later. Drafts are
     * never shown in the storefront.
     */
    public function up(): void
    {
        $this->setStatusEnum(['draft', 'pending_review', 'published', 'archived', 'rejected'], 'pending_review');
    }

    public function down(): void
    {
        // Drafts have no equivalent in the old enum; park them as pending review
        // so the column can be narrowed again without losing rows.
        DB::table('products')->where('status', 'draft')->update(['status' => 'pending_review']);

        $this->setStatusEnum(['pending_review', 'published', 'archived', 'rejected'], 'pending_review');
    }

    /**
     * @param  array<int, string>  $values
     */
    private function setStatusEnum(array $values, string $default): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            $list = collect($values)->map(fn (string $value) => "'{$value}'")->implode(', ');

            DB::statement('alter table products drop constraint if exists products_status_check');
            DB::statement('alter table products alter column status type varchar(255)');
            DB::statement("alter table products alter column status set default '{$default}'");
            DB::statement("alter table products add constraint products_status_check check (status in ({$list}))");
        } else {
            Schema::table('products', function (Blueprint $table) use ($values, $default) {
                $table->enum('status', $values)->default($default)->change();
            });
        }
    }
};
