<?php

namespace Database\Factories;

use App\Models\ProspectiveApplication;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProspectiveApplicationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'status' => ProspectiveApplication::STATUS_PENDING,
            'events_attended' => '2023;2024',
            'salesforce_name' => 'R-' . $this->faker->uuid(),
            'salesforce_id' => $this->faker->uuid(),
            'sfuid' => $this->faker->uuid(),
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'street' => $this->faker->streetAddress(),
            'city' => $this->faker->city(),
            'state' => $this->faker->stateAbbr(),
            'country' => 'US',
            'postal_code' => $this->faker->postcode(),
            'phone' => $this->faker->phoneNumber(),
            'year' => date('Y'),
            'email' => $this->faker->email(),
            'bpguid' => $this->faker->uuid(),
            'person_id' => null,
            'why_volunteer' => $this->faker->words(10, true),
            'why_volunteer_review' => $this->faker->words(5, true),
            'review_person_id' => null,
            'reviewed_at' => null,
            'known_rangers' => $this->faker->words(5, true),
            'known_applicants' => $this->faker->words(5, true),
            'is_over_18' => true,
            'handles' => $this->faker->words(5, true),
            'approved_handle' => $this->faker->words(2, true),
            'rejected_handles' => null,
            'regional_experience' => $this->faker->words(5, true),
            'regional_callsign' => $this->faker->words(2, true),
            'experience' => ProspectiveApplication::EXPERIENCE_BRC2,
            'emergency_contact' => $this->faker->words(10, true),
            'assigned_person_id' => null,
            'updated_by_person_id' => null,
            'updated_by_person_at' => null,
        ];
    }
}
