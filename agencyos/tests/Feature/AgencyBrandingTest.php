<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Role;
use App\Models\User;
use App\Services\AgencyBranding;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AgencyBrandingTest extends TestCase
{
    use RefreshDatabase;

    private Agency $agency;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->withoutVite();
        Storage::fake('local');
        $this->agency = Agency::create(['name' => 'My Agency']);
        $owner = User::factory()->create();
        $this->agency->users()->attach($owner, ['role_id' => Role::where('name', 'agency_owner')->value('id')]);
        $this->actingAs($owner)->withSession(['agency_id' => $this->agency->id]);
    }

    private function settings(array $extra = []): array
    {
        return $extra + ['name' => 'My Agency', 'timezone' => 'Asia/Kolkata', 'currency' => 'INR'];
    }

    public function test_owner_can_save_branding_replace_and_remove_a_private_logo(): void
    {
        $this->get('/agency/settings')->assertOk()->assertSee('Saved brand preview');
        $this->patch('/agency/settings', $this->settings([
            'email' => 'hello@example.com', 'support_email' => 'help@example.com',
            'address' => "10 Market Road\nMumbai", 'accent_color' => '#123abc',
            'logo' => UploadedFile::fake()->image('logo.png', 240, 80),
            'branding' => ['logo_path' => 'secrets.env'], 'agency_id' => 999,
        ]))->assertRedirect()->assertSessionHasNoErrors();
        $brand = $this->agency->fresh()->branding;
        $this->assertSame('hello@example.com', $brand['email']);
        $this->assertStringStartsWith('branding/'.$this->agency->id.'/', $brand['logo_path']);
        Storage::disk('local')->assertExists($brand['logo_path']);
        $this->get('/dashboard')->assertOk()->assertSee('data:image/png;base64,', false)->assertSee('help@example.com');
        $this->patch('/agency/settings', $this->settings(['logo' => UploadedFile::fake()->image('replacement.jpg')]))->assertSessionHasNoErrors();
        Storage::disk('local')->assertMissing($brand['logo_path']);
        $replacement = $this->agency->fresh()->branding['logo_path'];
        $this->patch('/agency/settings', $this->settings(['remove_logo' => true]))->assertSessionHasNoErrors();
        Storage::disk('local')->assertMissing($replacement);
        $this->assertNull(app(AgencyBranding::class)->snapshot($this->agency->fresh())['logo_data']);
    }

    public function test_invalid_images_links_colors_and_conflicting_logo_actions_are_rejected(): void
    {
        $this->patch('/agency/settings', $this->settings([
            'website' => 'javascript:alert(1)', 'support_url' => 'file:///etc/passwd',
            'accent_color' => 'red;display:none', 'email' => 'invalid',
            'logo' => UploadedFile::fake()->create('logo.svg', 2, 'image/svg+xml'),
        ]))->assertSessionHasErrors(['website', 'support_url', 'accent_color', 'email', 'logo']);
        $this->patch('/agency/settings', $this->settings(['logo' => UploadedFile::fake()->image('big.png', 1601, 10)]))->assertSessionHasErrors('logo');
        $this->patch('/agency/settings', $this->settings(['logo' => UploadedFile::fake()->image('logo.png')->size(1025)]))->assertSessionHasErrors('logo');
        $this->patch('/agency/settings', $this->settings(['logo' => UploadedFile::fake()->image('logo.png'), 'remove_logo' => true]))->assertSessionHasErrors('logo');
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_branding_is_escaped_and_other_agencies_and_employees_cannot_change_it(): void
    {
        $other = Agency::create(['name' => 'Other']);
        $this->patch('/agency/settings', $this->settings(['tagline' => '<script>alert(1)</script>', 'agency_id' => $other->id]))->assertSessionHasNoErrors();
        $this->get('/agency/settings')->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->assertNull($other->fresh()->branding);
        $this->agency->users()->updateExistingPivot(auth()->id(), ['role_id' => Role::where('name', 'developer')->value('id')]);
        $this->get('/agency/settings')->assertForbidden();
        $this->patch('/agency/settings', $this->settings(['name' => 'Changed']))->assertForbidden();
        $this->assertSame('My Agency', $this->agency->fresh()->name);
    }

    public function test_logo_reader_rejects_paths_outside_the_agency_directory(): void
    {
        $this->agency->update(['branding' => ['logo_path' => 'branding/999/logo.png']]);
        $this->assertNull(app(AgencyBranding::class)->logoData($this->agency));
    }
}
