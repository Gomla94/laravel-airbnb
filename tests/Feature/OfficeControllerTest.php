<?php

namespace Tests\Feature;

use App\Models\Image;
use App\Models\Office;
use App\Models\Reservation;
use App\Models\Tag;
use App\Models\User;
use App\Notifications\OfficePendingApproval;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

use function PHPUnit\Framework\assertCount;

class OfficeControllerTest extends TestCase
{
    use RefreshDatabase;
    /** @test */

    public function itListPaginatedOffices()
    {
        Office::factory(3)->create();
        
        $response = $this->get('/api/offices');
        
        $response->assertOk($response->json('data'));
        $response->assertJsonCount(3, 'data');
        $this->assertNotNull($response->json('data')[0]['id']);
    }

    /** @test */

    public function itListOnlyApprovedAndNotHiidenOffices()
    {
        Office::factory(3)->create();
        Office::factory(2)->create(['hidden' => false, 'approval_status' => Office::PENDING_STATUS]);
        
        $response = $this->get('/api/offices');

        $response->assertJsonCount(3, 'data');
    }

    /** @test */
    public function itFiltersOfficesByHosts()
    {
       Office::factory(3)->create();
       $host = User::factory()->create();
        Office::factory(2)->for($host)->create();

       $response = $this->get('/api/offices?user_id=' . $host->id);

       $response->assertJsonCount(2, 'data');
    }

    /** @test */
    public function itFiltersOfficesByVisitors()
    {
        Office::factory(3)->create();
        $user = User::factory()->create();
        $office = Office::factory()->create();
        Reservation::factory()->for($office)->create(['user_id' => $user->id]);
        Reservation::factory()->for($office)->create();

        $response = $this->get('/api/offices?visitor_id=' . $user->id);

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $this->assertEquals($response->json('data')[0]['id'], $office->id);
        $this->assertCount(1, $response->json('data'));
    }

    /** @test */
    public function itListOfficesWithTagsAndImagesAndUsers()
    {
        $user = User::factory()->create();
        $tag = Tag::factory()->create();
        $office = Office::factory()->for($user)->create();
        $office->tags()->attach($tag);
        $office->images()->create(['path' => 'image.path']);

        $response = $this->get('/api/offices');

        $response->assertOk();
        $this->assertIsArray($response->json('data')[0]['tags']);
        $this->assertCount(1, $response->json('data')[0]['tags']);
        $this->assertIsArray($response->json('data')[0]['user']);
        $this->assertEquals($user->id, $response->json('data')[0]['user']['id']);
        $this->assertIsArray($response->json('data')[0]['images']);
        $this->assertCount(1, $response->json('data')[0]['images']);
    }

    /** @test */
    public function itListOfficesWithReservationCount()
    {
        $office = Office::factory()->create();
        Reservation::factory()->for($office)->create([
            'status' => Reservation::ACTIVE_STATUS
        ]);
        Reservation::factory()->for($office)->create([
            'status' => Reservation::CANCELLED_STATUS
        ]);

        $response = $this->get('/api/offices');

        $response->assertOk();
        $this->assertNotNull($response->json('data')[0]['reservations_count']);
        $this->assertEquals(1, $response->json('data')[0]['reservations_count']);
    }

    /** @test */
    public function itListsOfficesByDistanceIfLatAndLngAreProvided()
    {
        // Lispon lat 38.72244826131101, lng -9.139605919702468

        // Torres Verdas lat 39.09227514532061, lng -9.259767822630309 the closest to Lisbon

        // Lieria lat 39.74977043948755, lng -8.807252372412037 the farest to Lispon

        $office1 = Office::factory()->create([
            'name' => 'Torres Vedras',
            'lat' => 39.09227514532061,
            'lng' => -9.259767822630309
        ]);

        $office2 = Office::factory()->create([
            'name' => 'Lieria',
            'lat' => 39.74977043948755,
            'lng' => -8.807252372412037
        ]);

        $response = $this->get('/api/offices?lat=38.72244826131101&lng=-9.139605919702468');

        $response->assertOk();
        $this->assertEquals($office1->name, $response->json('data')[0]['name']);
        $this->assertEquals($office2->name, $response->json('data')[1]['name']);
    }

    /** @test */
    public function itShowsOffice()
    {
        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();
        Reservation::factory()->for($office)->create([
            'status' => Reservation::ACTIVE_STATUS
        ]);
        Reservation::factory()->for($office)->create([
            'status' => Reservation::CANCELLED_STATUS
        ]);
        $office->images()->create(['path' => 'path']);
        $tag = Tag::factory()->create();
        $office->tags()->attach($tag);

        $response = $this->get("/api/offices/$office->id");

        $response->assertOk();
        $this->assertEquals($office->name, $response->json('data')['name']);
        $this->assertEquals(1, $response->json('data')['reservations_count']);
        $this->assertIsArray($response->json('data')['images']);
        $this->assertEquals(1, count($response->json('data')['images']));
        $this->assertIsArray($response->json('data')['tags']);
        $this->assertEquals($tag->name, $response->json('data')['tags'][0]['name']);
        $this->assertIsArray($response->json('data')['user']);
    }

    /** @test */
    public function itCreatesOffice()
    {
        $user = User::factory()->createQuietly();
        $this->actingAs($user);
        $tag = Tag::factory()->create();
        $tag2 = Tag::factory()->create();
        $response = $this->postJson('/api/offices', [
            'name' => 'office1',
            'description' => 'description',
            'lat' => 39.09227514532061,
            'lng' => -9.259767822630309,
            'address_line_1' => 'Cairo',
            'price_per_day' => 100,
            'hidden' => true,
            'tags' => [$tag->id, $tag2->id]
        ]);

        $response->assertCreated()
                    ->assertJsonPath('data.name', 'office1')
                    ->assertJsonCount(2, 'data.tags')
                    ->assertJsonPath('data.approval_status', Office::PENDING_STATUS);

        $this->assertDatabaseHas('offices', [
            'name' => 'office1'
        ]);
    }

    /** @test */
    public function itCreatesOfficeIfUserHasToken()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test', []);
        //the third argument passed to the createToken method is for the actions that the user has
        //access to.

        $response = $this->postJson('/api/offices', [], [
            'Authorization' => 'Bearer ' . $token->plainTextToken
        ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function itUpdatesOffice()
    {
        $user = User::factory()->create();
        $tags = Tag::factory(2)->create();
        $anotherTag = Tag::factory(2)->create();
        $office = Office::factory()->for($user)->create();
        $office->tags()->attach($tags);
        $this->actingAs($user);

        // dd($anotherTag);
        $response = $this->putJson('/api/offices/' . $office->id, [
            'name' => 'Amazing Office',
            'tags' => [$anotherTag[0]->id, $tags[0]->id]
        ]);

        // dd($response);
        // dd($response->json('data'));
        $response->assertOk()
                    ->assertJsonPath('data.name', 'Amazing Office')
                    ->assertJsonPath('data.tags.0.name', $tags[0]->name)
                    ->assertJsonPath('data.tags.1.name', $anotherTag[0]->name)
                    ->assertJsonCount(2, 'data.tags');
        // $this->assertEquals('Amazing Office', $response->json('data')['name']);
        
    }

    /** @test */
    public function itDoesNotUpdateSomeoneElseOffice()
    {
        $user = User::factory()->create();
        $anotherUser = User::factory()->create();
        $tags = Tag::factory(2)->create();
        $office = Office::factory()->for($anotherUser)->create();
        $office->tags()->attach($tags);
        $this->actingAs($user);

        $response = $this->putJson('/api/offices/' . $office->id, [
            'name' => 'Amazing Office'
        ]);

        $response->assertStatus(403);         
    }

    /** @test */
    public function itSetsApprovalStatusToPendingWhenUpdating()
    {
        Notification::fake();
        $admin = User::factory()->create(['name' => 'Ahmed']);
        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();
        $this->actingAs($user);

        $response = $this->putJson('/api/offices/' . $office->id, [
        'lat' => 40.09227561,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.approval_status', Office::PENDING_STATUS);
        $this->assertDatabaseHas('offices', [
            'id' => $office->id,
            'approval_status' => Office::PENDING_STATUS
        ]);     
        
        Notification::assertSentTo($admin, OfficePendingApproval::class);
        // $admin->notify(new OfficePendingApproval($office));
    }

    /** @test */
    public function itCanDeleteOffice()
    {
        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();

        $this->actingAs($user);

        $response = $this->deleteJson('api/offices/' . $office->id);

        $response->assertOk();
        $this->assertSoftDeleted($office);
    }

    /** @test */
    public function itCannotDeleteOfficeWithReservations()
    {
        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();
        Reservation::factory()->for($office)->create();

        $this->actingAs($user);

        $response = $this->deleteJson('api/offices/' . $office->id);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertDatabaseHas('offices', [
            'id' => $office->id,
            'deleted_at' => null
        ]);
    }
}
