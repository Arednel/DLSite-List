<?php

namespace Tests\Feature;

use App\Models\Option;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

class PublicStorageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_access_public_storage_when_authentication_is_disabled(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('Works/RJ123456/cover.jpg', 'cover');

        $response = $this->get('/storage/Works/RJ123456/cover.jpg?v=123');

        $response->assertOk();

        $baseResponse = $response->baseResponse;

        $this->assertInstanceOf(BinaryFileResponse::class, $baseResponse);

        /** @var BinaryFileResponse $baseResponse */
        $this->assertSame(
            realpath(Storage::disk('public')->path('Works/RJ123456/cover.jpg')),
            realpath($baseResponse->getFile()->getPathname()),
        );
    }

    public function test_guest_receives_unauthorized_for_public_storage_when_authentication_is_enabled(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('Works/RJ123456/cover.jpg', 'cover');
        Option::setUserAuthenticationEnabled(true);

        $this->get('/storage/Works/RJ123456/cover.jpg')->assertStatus(401);
    }

    public function test_authenticated_user_can_access_public_storage_when_authentication_is_enabled(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('Refetch/1/Works/RJ123456/cover.jpg', 'cover');
        Option::setUserAuthenticationEnabled(true);

        /** @var User $user */
        $user = User::factory()->createOne();

        $this->actingAs($user)
            ->get('/storage/Refetch/1/Works/RJ123456/cover.jpg')
            ->assertOk();
    }

    public function test_public_storage_directories_return_not_found(): void
    {
        Storage::fake('public');
        Storage::disk('public')->makeDirectory('Works/RJ123456');

        $this->get('/storage/Works/RJ123456')->assertNotFound();
    }

    public function test_guest_cannot_probe_missing_public_storage_files_when_authentication_is_enabled(): void
    {
        Storage::fake('public');
        Option::setUserAuthenticationEnabled(true);

        $this->get('/storage/Works/RJ123456/missing.jpg')->assertStatus(401);
    }

    public function test_missing_or_outside_public_storage_paths_return_not_found(): void
    {
        Storage::fake('public');
        $outsideFile = dirname(Storage::disk('public')->path('')) . DIRECTORY_SEPARATOR . 'secret.txt';
        file_put_contents($outsideFile, 'secret');

        try {
            $this->get('/storage/Works/RJ123456/missing.jpg')->assertNotFound();
            $this->get('/storage/%2E%2E/secret.txt')->assertNotFound();
        } finally {
            @unlink($outsideFile);
        }
    }
}
