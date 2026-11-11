<?php

namespace Tests\Feature;

use App\Models\{Product, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductContentAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_save_reorder_and_clear_content_and_draft_photos_are_private(): void
    {
        Storage::fake('local');
        $this->withoutVite();
        $staff = User::factory()->create(['is_admin' => true]);
        $product = Product::create(['name' => 'Keyboard', 'is_active' => false]);
        $url = '/admin/products/'.$product->id;
        $this->actingAs($staff)->post($url, [
            '_method' => 'PUT', 'name' => 'Keyboard', 'description' => 'Описание товара',
            'specifications' => [['name' => 'Интерфейс', 'value' => 'USB']],
            'photos' => [UploadedFile::fake()->image('front.jpg'), UploadedFile::fake()->image('back.png')],
        ])->assertSessionHasNoErrors();
        $product->refresh();
        $this->assertSame('USB', $product->specifications[0]['value']);
        $this->assertSame('Описание товара', $product->description);
        $photos = $product->photos;
        Storage::disk('local')->assertExists($photos);
        $this->get(route('products.photo', [$product, 0]))->assertOk();
        $this->put($url, ['name' => 'Keyboard', 'photos' => array_reverse($photos)])->assertSessionHasNoErrors();
        $this->assertSame(array_reverse($photos), $product->fresh()->photos);
        $this->put($url, ['name' => 'Keyboard', 'photos' => ['product-photos/not-owned.jpg']])->assertSessionHasErrors('photos.0');
        $this->post($url, ['_method' => 'PUT', 'name' => 'Keyboard', 'photos' => [UploadedFile::fake()->create('script.svg', 1, 'image/svg+xml')]])->assertSessionHasErrors('photos.0');
        $this->put($url, ['name' => 'Keyboard', 'specifications' => [['name' => 'USB', 'value' => '']]])->assertSessionHasErrors('specifications.0.value');
        auth()->logout();
        $this->get(route('products.photo', [$product, 0]))->assertForbidden();
        $product->update(['is_active' => true]);
        $this->get(route('products.photo', [$product, 0]))->assertOk();
        $this->actingAs($staff)->put($url, ['name' => 'Keyboard', 'photos' => null, 'specifications' => null, 'description' => null])->assertSessionHasNoErrors();
        $product->refresh();
        $this->assertSame([], $product->photos);
        $this->assertSame([], $product->specifications);
        $this->assertNull($product->description);
        $this->assertSame([], \App\Support\Storefront::product($product)['images']);
    }
}
