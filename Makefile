reset:
	php artisan migrate:fresh
	php artisan db:seed
# 	php artisan make:filament-user

reset-seed:
	php artisan migrate:fresh
	php artisan db:seed
	php artisan db:seed --class=DeliveryOptionSeeder
	php artisan db:seed --class=BuyerCartSeeder
	php artisan db:seed --class=SuperAdminSeeder


mm:
	php artisan make:model Product
	php artisan make:model ProductTranslation
	php artisan make:model ProductVariant
	php artisan make:model ProductVariantAttribute