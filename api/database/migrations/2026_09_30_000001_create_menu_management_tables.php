<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });

        Schema::create('menu_service_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('menu_locations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('allergens', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->string('name');
            $table->string('short_name')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('menu_categories')->nullOnDelete();
            $table->string('subcategory')->nullable();
            $table->string('image_path')->nullable();
            $table->string('food_type')->default('non_vegetarian'); // vegetarian|vegan|non_vegetarian
            $table->boolean('is_vegetarian')->default(false);
            $table->boolean('is_vegan')->default(false);
            $table->boolean('is_halal')->default(false);
            $table->boolean('active')->default(true);
            $table->boolean('seasonal')->default(false);
            $table->string('kitchen_station')->nullable();
            $table->unsignedInteger('prep_time_minutes')->nullable();
            $table->decimal('default_price', 12, 2)->default(0);
            $table->decimal('vat_rate', 5, 4)->nullable();
            $table->string('availability_status')->default('available'); // available|unavailable|sold_out
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['active', 'availability_status']);
            $table->index('category_id');
        });

        Schema::create('product_allergens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('allergen_id')->constrained()->cascadeOnDelete();
            $table->unique(['product_id', 'allergen_id']);
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('sku')->nullable()->unique();
            $table->string('portion_size')->nullable();
            $table->string('unit')->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->decimal('recipe_multiplier', 8, 3)->default(1);
            $table->boolean('available')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('product_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('price_type')->default('standard'); // standard|service|location|seasonal|promo|catering_per_person
            $table->foreignId('service_type_id')->nullable()->constrained('menu_service_types')->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('menu_locations')->nullOnDelete();
            $table->decimal('price', 12, 2);
            $table->string('currency', 3)->default('EUR');
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'price_type']);
        });

        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('menu_type'); // lunch|food_truck|catering|private_event|evening_snacks|online
            $table->string('status')->default('draft'); // draft|active|scheduled|expired|archived
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->json('available_days')->nullable();
            $table->time('available_time_start')->nullable();
            $table->time('available_time_end')->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->boolean('seasonal')->default(false);
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['status', 'menu_type']);
        });

        Schema::create('menu_service_type', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_service_type_id')->constrained()->cascadeOnDelete();
            $table->unique(['menu_id', 'menu_service_type_id']);
        });

        Schema::create('menu_location', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_location_id')->constrained()->cascadeOnDelete();
            $table->unique(['menu_id', 'menu_location_id']);
        });

        Schema::create('menu_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('menu_categories')->nullOnDelete();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('menu_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_section_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->decimal('price', 12, 2)->nullable();
            $table->text('description')->nullable();
            $table->boolean('featured')->default(false);
            $table->boolean('available')->default(true);
            $table->unsignedInteger('min_quantity')->nullable();
            $table->unsignedInteger('max_quantity')->nullable();
            $table->timestamps();

            $table->unique(['menu_id', 'product_id', 'product_variant_id'], 'menu_entries_unique_product');
        });

        Schema::create('addons', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('product_addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('addon_id')->constrained()->cascadeOnDelete();
            $table->unique(['product_id', 'addon_id']);
        });

        Schema::create('menu_entry_addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('addon_id')->constrained()->cascadeOnDelete();
            $table->unique(['menu_entry_id', 'addon_id']);
        });

        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->unsignedInteger('people_count')->nullable();
            $table->decimal('price_per_person', 12, 2)->nullable();
            $table->decimal('fixed_price', 12, 2)->nullable();
            $table->unsignedInteger('min_quantity')->nullable();
            $table->unsignedInteger('max_quantity')->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->string('status')->default('draft');
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('package_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity', 8, 2)->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('package_service_type', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_service_type_id')->constrained()->cascadeOnDelete();
            $table->unique(['package_id', 'menu_service_type_id']);
        });

        Schema::create('availability_rules', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->json('days_of_week')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->date('date_from')->nullable();
            $table->date('date_to')->nullable();
            $table->foreignId('location_id')->nullable()->constrained('menu_locations')->nullOnDelete();
            $table->foreignId('service_type_id')->nullable()->constrained('menu_service_types')->nullOnDelete();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name')->nullable();
            $table->decimal('yield_quantity', 12, 3)->default(1);
            $table->timestamps();
        });

        Schema::create('recipe_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->timestamps();
        });

        Schema::create('menu_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
        });

        if (! Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('role')->default('admin')->after('email');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_audit_logs');
        Schema::dropIfExists('recipe_items');
        Schema::dropIfExists('recipes');
        Schema::dropIfExists('availability_rules');
        Schema::dropIfExists('package_service_type');
        Schema::dropIfExists('package_items');
        Schema::dropIfExists('packages');
        Schema::dropIfExists('menu_entry_addons');
        Schema::dropIfExists('product_addons');
        Schema::dropIfExists('addons');
        Schema::dropIfExists('menu_entries');
        Schema::dropIfExists('menu_sections');
        Schema::dropIfExists('menu_location');
        Schema::dropIfExists('menu_service_type');
        Schema::dropIfExists('menus');
        Schema::dropIfExists('product_prices');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('product_allergens');
        Schema::dropIfExists('products');
        Schema::dropIfExists('allergens');
        Schema::dropIfExists('menu_locations');
        Schema::dropIfExists('menu_service_types');
        Schema::dropIfExists('menu_categories');

        if (Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('role');
            });
        }
    }
};
