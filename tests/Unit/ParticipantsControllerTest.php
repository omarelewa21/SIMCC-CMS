<?php

namespace Tests\Feature\Api;

use App\Http\Requests\Participant\EliminateFromComputeRequest;
use App\Http\Requests\ParticipantReportWithCertificateRequest;
use App\Models\Competition;
use App\Models\CompetitionOrganization;
use App\Models\CompetitionOrganizationDate;
use App\Models\CompetitionParticipantsResults;
use App\Models\Countries;
use App\Models\EliminatedCheatingParticipants;
use App\Models\Organization;
use App\Models\Participants;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class ParticipantsControllerTest extends TestCase
{
    use DatabaseTransactions;
    use WithFaker;
    public $user;
    private $participant;
    protected function setUp(): void
    {
        parent::setUp();

        // Create a new user without the email_verified_at column
        $user = User::factory()->make([
            'email_verified_at' => null,
        ]);

        // Save the user to the database
        $user->save();

        $this->user = $user;

        // Authenticate the user
        $this->actingAs($user);
    }

    /**
     * Test the create method of ParticipantsController
     *
     * @return void
     */
    public function testCreate()
    {
        $country = Countries::where('id', 202)->first();
        $competition = Competition::factory()->create();

        $organization = Organization::factory([
            'country_id' => $country->id
        ])->create();

        $competition_organization = CompetitionOrganization::factory()->create([
            'country_id' => $country->id,
            'competition_id' => $competition->id,
            'organization_id' => $organization->id
        ]);
        // Assert that the competition organization was created successfully
        $this->assertDatabaseHas('competition_organization', [
            'id' => $competition_organization->id,
            'country_id' => $country->id,
            'competition_id' => $competition->id,
            'organization_id' => $organization->id,
        ]);

        $competition_organization_date = CompetitionOrganizationDate::factory()->create([
            'competition_organization_id' => $competition_organization->id
        ]);

        // Assert that the competition organization date was created successfully
        $this->assertDatabaseHas('competition_organization_date', [
            'id' => $competition_organization_date->id,
            'competition_organization_id' => $competition_organization->id,
        ]);


        $school = School::factory()->create([
            'country_id' => $country->id,
            'organization_id' => $organization->id,
        ]);

        // Assert that the school was created successfully
        $this->assertDatabaseHas('schools', [
            'id' => $school->id,
            'country_id' => $country->id,
            'organization_id' => $organization->id,
        ]);

        $data = [
            'participant' => [
                [
                    'competition_id' => $competition->id,
                    'country_id' => $country->id,
                    'for_partner' => 0,
                    'grade' => 1,
                    'organization_id' => $organization->id,
                    'school_id' => $school->id,
                    // 'competition_organization_id' => $competition_organization->id,
                    'name' => $this->faker->name,
                    'class' => 'NA',
                    'email' => $this->faker->email,
                    "tuition_centre_id" => null
                ],
            ],
        ];


        $response = $this->postJson('/api/participant', $data);
        // $responseArray = $response->decodeResponseJson(); 
        // $errorMessage = $responseArray['error'];
        // $this->expectExceptionMessage(json_encode($errorMessage));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'competition_organization_id',
                        'country_id',
                        'name',
                        'class',
                        'grade',
                        // 'for_partner',
                        'school_id',
                        'tuition_centre_id',
                        'email',
                        'created_by_userid',
                        'index_no',
                        'certificate_no',
                        // 'passkey',
                        'updated_at',
                        'created_at',
                    ],
                ],
            ]);
        $this->participant = Participants::find($response['data'][0]['id']);
        $this->assertDatabaseHas('participants', [
            'competition_organization_id' => $competition_organization->id,
            'country_id' => $data['participant'][0]['country_id'],
            'name' => $data['participant'][0]['name'],
            'grade' => $data['participant'][0]['grade'],
            'school_id' => $data['participant'][0]['school_id'],
        ]);
    }

    /**
     * Test the list method of ParticipantsController
     *
     * @return void
     */
    public function testList()
    {
        $response = $this->getJson('/api/participant?limits=2');

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'filterOptions' => [
                        'status',
                        'organization',
                        'grade',
                        'private',
                        'countries',
                        'competition',
                    ],
                    'participantList' => [
                        'data' => [
                            '*' => [
                                'id',
                                'competition_organization_id',
                                'country_id',
                                'name',
                                'class',
                                'grade',
                                // 'for_partner',
                                'school_id',
                                'tuition_centre_id',
                                'email',
                                'created_by_userid',
                                'index_no',
                                'certificate_no',
                                // 'passkey',
                                'updated_at',
                                'created_at',
                                'country_name',
                                'private',
                                'school_name',
                                'tuition_centre_name',
                                'competition_name',
                                'status',
                            ],
                        ],
                        'links' => [
                            '*' => [
                                'url',
                                'label',
                                'active'
                            ]
                        ],
                        'next_page_url',
                        'path',
                        'per_page',
                        'prev_page_url',
                        'to',
                        'total'
                    ],
                ],
            ]);
    }

    /**
     * Test the update method of ParticipantsController
     *
     * @return void
     */
    public function testUpdateParticipant()
    {
        $country = Countries::where('id', 202)->first();
        $competition = Competition::factory()->create();

        $organization = Organization::factory([
            'country_id' => $country->id
        ])->create();

        $competition_organization = CompetitionOrganization::factory()->create([
            'country_id' => $country->id,
            'competition_id' => $competition->id,
            'organization_id' => $organization->id
        ]);

        $competition_organization_date = CompetitionOrganizationDate::factory()->create([
            'competition_organization_id' => $competition_organization->id
        ]);

        $school = School::factory()->create([
            'country_id' => $country->id,
            'organization_id' => $organization->id,
        ]);

        $participant = Participants::factory()->create([
            'competition_organization_id' => $competition_organization->id,
            'school_id' => $school->id,
            // 'organization_id' => $organization->id,
            // 'for_partner' => 0,
            'country_id' => $country->id,
        ]);
        $password = 'Aa@1243435';

        $data = [
            'id' => $participant->id,
            'name' => $this->faker->name,
            'grade' => 1,
            'class' => 'NA',
            'email' => $this->faker->email,
            // 'for_partner' => 0,
            'tuition_centre_id' => null,
            'school_id' => $school->id,
            'password' => $password,
            'password_confirmation' => $password
        ];

        $response = $this->actingAs($this->user)->patchJson('/api/participant', $data);

        $response->assertStatus(Response::HTTP_OK);
        $this->assertDatabaseHas('participants', [
            'id' => $participant->id,
            'name' => $data['name'],
            'grade' => $data['grade'],
            'class' => $data['class'],
            'email' => $data['email'],
            // 'for_partner' => $data['for_partner'],
            'tuition_centre_id' => $data['tuition_centre_id'],
            'school_id' => $data['school_id'],
        ]);
    }
    /**
     * Test the delete participants method of ParticipantsController
     *
     * @return void
     */
    public function testDeleteParticipant()
    {
        // Create a new participant
        $country = Countries::where('id', 202)->first();
        $competition = Competition::factory()->create();

        $organization = Organization::factory([
            'country_id' => $country->id
        ])->create();

        $competition_organization = CompetitionOrganization::factory()->create([
            'country_id' => $country->id,
            'competition_id' => $competition->id,
            'organization_id' => $organization->id
        ]);

        $school = School::factory()->create([
            'country_id' => $country->id,
            'organization_id' => $organization->id,
        ]);

        // Get the IDs of the participants
        $participants = Participants::factory(1)->create([
            'competition_organization_id' => $competition_organization->id,
            'school_id' => $school->id,
            // 'organization_id' => $organization->id,
            // 'for_partner' => 0,
            'country_id' => $country->id,
        ]);
        // Get the IDs of the participants
        $ids = $participants->pluck('id')->toArray();

        // Send delete request with array of IDs
        $response = $this->json('DELETE', route('participant.delete'), ['id' => $ids]);

        // Assert response status is 200
        $response->assertStatus(200);

        // Assert participants are deleted from database
        foreach ($participants as $participant) {
            $this->assertDatabaseMissing('participants', ['id' => $participant->id]);
        }
    }

    /**
     * Test the swapIndex method of ParticipantsController
     *
     * @return void
     */

    public function testSwapIndex()
    {
        // Create a new participant
        $country = Countries::where('id', 202)->first();
        $competition = Competition::factory()->create();

        $organization = Organization::factory([
            'country_id' => $country->id
        ])->create();

        $competition_organization = CompetitionOrganization::factory()->create([
            'country_id' => $country->id,
            'competition_id' => $competition->id,
            'organization_id' => $organization->id
        ]);

        $school = School::factory()->create([
            'country_id' => $country->id,
            'organization_id' => $organization->id,
        ]);

        // Get the IDs of the participants
        $participants = Participants::factory()
            ->times(2)
            ->create([
                'competition_organization_id' => $competition_organization->id,
                'school_id' => $school->id,
                'country_id' => $country->id,
            ]);

        // Send swap index request
        $response = $this->json('PATCH', route('participant.swapIndex'), [
            'index' => $participants[0]->index_no,
            'indexToSwap' => $participants[1]->index_no,
        ]);

        // Assert response status is 200
        $response->assertStatus(200);

        // Assert response message
        $response->assertJson([
            'status'  => 200,
            'message' => 'Participant index number swap successful',
        ]);

        // Assert participant index numbers are swapped
        $this->assertDatabaseHas('participants', [
            'id' => $participants[0]->id,
            'index_no' => $participants[1]->index_no,
        ]);
        $this->assertDatabaseHas('participants', [
            'id' => $participants[1]->id,
            'index_no' => $participants[0]->index_no,
        ]);
    }


    // public function testEliminateParticipantsFromCompute()
    // {
    //     //  Create a new participants
    //     $country = Countries::where('id', 202)->first();
    //     $competition = Competition::factory()->create();

    //     $organization = Organization::factory([
    //         'country_id' => $country->id
    //     ])->create();

    //     $competition_organization = CompetitionOrganization::factory()->create([
    //         'country_id' => $country->id,
    //         'competition_id' => $competition->id,
    //         'organization_id' => $organization->id
    //     ]);

    //     $school = School::factory()->create([
    //         'country_id' => $country->id,
    //         'organization_id' => $organization->id,
    //     ]);

    //     // Get the IDs of the participants
    //     $participants = Participants::factory()
    //         ->times(2)
    //         ->create([
    //             'competition_organization_id' => $competition_organization->id,
    //             'school_id' => $school->id,
    //             'country_id' => $country->id,
    //         ]);


    //     // create a mock request object with participants and reason fields
    //     $request = new EliminateFromComputeRequest([
    //         'participants' => [$participants[0]['index_no'], $participants[1]['index_no']],
    //         'reason' => 'Cheating',
    //     ]);

    //     // mock the updateOrCreate() method for EliminatedCheatingParticipants model
    //     $mockECP = Mockery::mock('EliminatedCheatingParticipants');
    //     $mockECP->shouldReceive('updateOrCreate')->once()
    //         ->with(['participant_index' => $participants[0]['index_no']], ['reason' => 'Cheating'])
    //         ->andReturn(new EliminatedCheatingParticipants());
    //     $mockECP->shouldReceive('updateOrCreate')->once()
    //         ->with(['participant_index' => $participants[1]['index_no']], ['reason' => 'Cheating'])
    //         ->andReturn(new EliminatedCheatingParticipants());
    //     $this->app->instance('EliminatedCheatingParticipants', $mockECP);

    //     // call the eliminateParticipantsFromCompute() method
    //     $response = $this->post('/api/participant/compute/cheaters/eliminate', [
    //         'participants' => $request->participants,
    //         'reason' => $request->reason,
    //     ]);

    //     // check the response status code and content
    //     $response->assertStatus(200);
    //     // $response->assertJson([
    //     //     'status' => 200,
    //     //     'message' => 'Participants eliminatedsuccessfully',
    //     // ]);
    // }

    // public function testDeleteEliminatedParticipantsFromCompute()
    // {
    //     // Create some participants to eliminate
    //     $participants = factory(EliminatedCheatingParticipants::class, 5)->create();

    //     // Make the request to delete the participants
    //     $request = new EliminateFromComputeRequest(['participants' => $participants->pluck('participant_index')->toArray()]);
    //     $response = $this->postJson('/eliminate-from-compute', $request->toArray());

    //     // Check that the response has a 200 status code
    //     $response->assertStatus(200);

    //     // Check that the participants were deleted from the database
    //     $this->assertCount(0, EliminatedCheatingParticipants::whereIn('participant_index', $participants->pluck('participant_index'))->get());
    // }
}
