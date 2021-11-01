<?php

namespace Tests\Feature;

use App\Models\Image;
use App\Models\Office;
use App\Models\Reservation;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class OfficeControllerTest extends TestCase
{
    use RefreshDatabase;
    /** @test */

    public function itListPaginatedOffices()
    {
        Office::factory(3)->create();
        $response = $this->get('/api/offices');
        
        // $response->assertOk()->dump();
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
       $response = $this->get('/api/offices?host_id=' . $host->id);

       $response->assertJsonCount(2, 'data');
    }

    /** @test */
    public function itFiltersOfficesByUsers()
    {
        Office::factory(3)->create();
        $user = User::factory()->create();

        $office = Office::factory()->create();
        Reservation::factory()->for($office)->create(['user_id' => $user->id]);
        Reservation::factory()->for($office)->create();

        $response = $this->get('/api/offices?user_id=' . $user->id);
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
        // $response->dump();
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
}
