<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per external identity linked to a Hirfaty account.
 *
 * A table rather than `provider`/`provider_id` columns on `users`, because the
 * relationship is genuinely one-to-many: somebody can sign in with Google
 * today and Facebook next month on the same address, and two columns would
 * silently overwrite the first link with the second — quietly unlinking an
 * identity the account still relies on.
 *
 * Note what is *not* stored: no access or refresh tokens. Hirfaty only ever
 * asks these providers "who is this", never for ongoing access to the account,
 * so keeping the tokens would be storing a credential with no use for it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // 'google' or 'facebook' — see SocialAuthService::PROVIDERS, which
            // is the whitelist the routes are actually validated against.
            $table->string('provider', 32);

            // The provider's own immutable id for this person. Deliberately the
            // match key rather than the email: an email can be changed at the
            // provider and handed to somebody else, the subject id cannot.
            $table->string('provider_id');

            /*
             * Load-bearing. Without it a provider id could be attached to two
             * different Hirfaty accounts, and "sign in with Google" would then
             * be ambiguous about which one it opens.
             */
            $table->unique(['provider', 'provider_id']);

            // One link per provider per account: connecting a second Google
            // identity to the same user is a mistake, not a feature.
            $table->unique(['user_id', 'provider']);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_accounts');
    }
};
