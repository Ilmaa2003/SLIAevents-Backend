<?php

namespace Tests\Feature;

use Tests\TestCase;

class DisableRegistrationTest extends TestCase
{
    /**
     * Test that member verification is disabled.
     *
     * @return void
     */
    public function test_verify_member_is_disabled()
    {
        $response = $this->get('/api/conference/verify-member/12345');

        $response->assertStatus(403);
        $response->assertJson([
            'status' => 'error',
            'message' => 'Conference registration is currently closed.'
        ]);
    }

    /**
     * Test that student ID validation is disabled.
     *
     * @return void
     */
    public function test_validate_student_id_is_disabled()
    {
        $response = $this->postJson('/api/conference/validate-student-id', [
            'student_id' => 'ST12345'
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'valid' => false,
            'message' => 'Conference registration is currently closed.'
        ]);
    }

    /**
     * Test that payment initiation (registration) is disabled.
     *
     * @return void
     */
    public function test_initiate_payment_is_disabled()
    {
        $response = $this->postJson('/api/conference/initiate-payment', [
            'full_name' => 'John Doe',
            'email' => 'john@example.com'
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'message' => 'Conference registration is currently closed.'
        ]);
    }
}
