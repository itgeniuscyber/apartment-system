<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\Features;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::registration());
    }

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get(route('register'));

        $response->assertOk();
    }

    public function test_new_residents_can_register_with_their_security_profile(): void
    {
        Storage::fake('local');

        $response = $this->post(route('register.store'), [
            'name' => ' Test User ',
            'email' => 'test@example.com',
            'phone' => '+256 700 000001',
            'alternative_phone' => '+256 700 000003',
            'identity_document_type' => 'national_id',
            'identity_document_number' => 'CM 9000 1234 5678',
            'identity_document' => UploadedFile::fake()->create('national-id.pdf', 200, 'application/pdf'),
            'next_of_kin_name' => 'Sarah User',
            'next_of_kin_relationship' => 'Sister',
            'next_of_kin_phone' => '+256 700 000002',
            'next_of_kin_email' => 'sarah@example.com',
            'next_of_kin_address' => 'Kampala, Uganda',
            'preferred_channel' => 'whatsapp',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'role' => 'tenant',
        ]);
        $this->assertDatabaseHas('tenants', [
            'email' => 'test@example.com',
            'first_name' => 'Test',
            'last_name' => 'User',
            'status' => 'pending',
            'identity_document_type' => 'national_id',
            'identity_document_last_four' => '5678',
            'next_of_kin_name' => 'Sarah User',
            'next_of_kin_relationship' => 'Sister',
            'next_of_kin_phone' => '+256 700 000002',
            'preferred_channel' => 'whatsapp',
        ]);

        $tenant = Tenant::query()->firstOrFail();
        $this->assertNotNull($tenant->user_id);
        $this->assertNotNull($tenant->portal_activated_at);
        $this->assertSame('CM 9000 1234 5678', $tenant->identity_document_number);
        $this->assertNotSame(
            'CM 9000 1234 5678',
            DB::table('tenants')->where('id', $tenant->id)->value('identity_document_number'),
        );
        $this->assertNotNull($tenant->identity_document_path);
        Storage::disk('local')->assertExists($tenant->identity_document_path);
    }

    public function test_registration_requires_contact_identification_and_next_of_kin(): void
    {
        $this->from(route('register'))->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('register'))->assertSessionHasErrors([
            'phone',
            'identity_document_type',
            'identity_document_number',
            'next_of_kin_name',
            'next_of_kin_relationship',
            'next_of_kin_phone',
            'preferred_channel',
        ]);

        $this->assertGuest();
        $this->assertSame(0, Tenant::query()->count());
    }

    public function test_an_existing_management_created_profile_cannot_be_claimed_by_email(): void
    {
        Tenant::query()->create([
            'first_name' => 'Existing',
            'last_name' => 'Resident',
            'email' => 'resident@example.com',
        ]);

        $this->from(route('register'))->post(route('register.store'), [
            'name' => 'Someone Else',
            'email' => 'resident@example.com',
            'phone' => '+256 700 000001',
            'identity_document_type' => 'national_id',
            'identity_document_number' => 'CM12345678',
            'next_of_kin_name' => 'Trusted Person',
            'next_of_kin_relationship' => 'Sibling',
            'next_of_kin_phone' => '+256 700 000002',
            'preferred_channel' => 'whatsapp',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('register'))->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseHas('tenants', [
            'email' => 'resident@example.com',
            'user_id' => null,
        ]);
    }
}
