<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CatalogAutomationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unconfigured_or_disabled_automation_does_nothing(): void
    {
        Storage::fake('local');
        $this->artisan('catalog:automation-check')->assertSuccessful();
        Storage::disk('local')->put('catalog-automation/sources.json', '{"enabled":false}');
        $this->artisan('catalog:automation-check')->assertSuccessful();
        Storage::disk('local')->assertMissing('catalog-automation/products.json');
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('catalog_price_uploads', 0);
    }

    public function test_invalid_configuration_fails_without_importing(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('catalog-automation/sources.json', '{');
        $this->artisan('catalog:automation-check')->assertFailed();
        Storage::disk('local')->assertExists('catalog-automation/last-error.json');
        $this->assertDatabaseCount('catalog_import_batches', 0);
    }
}
