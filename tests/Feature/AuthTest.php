<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use App\Mail\SendOtpMail;
use App\Models\PasswordResetOtp;
use Carbon\Carbon;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * User can register as a client
     */
    public function test_user_can_register()
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Client',
            'email' => 'client@test.com',
            'password' => 'password123',
            'phone' => '01123456789',
        ]);

        $response->assertStatus(201)
                 ->assertJsonStructure([
                     'message',
                     'user' => ['id', 'name', 'email', 'role', 'phone', 'team_lead_id']
                 ]);

        $this->assertDatabaseHas('users', [
            'email' => 'client@test.com',
            'role' => 'client'
        ]);
    }

    /**
     * User cannot login without email verification
     */
    public function test_login_fails_if_email_not_verified()
    {
        $user = User::factory()->create([
            'email' => 'client@test.com',
            'password' => Hash::make('password123'),
            'role' => 'client',
            'email_verified_at' => null
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'client@test.com',
            'password' => 'password123'
        ]);

        $response->assertStatus(403)
                 ->assertJson(['message' => 'Please verify your email before login']);
    }

    /**
     * User can login after verifying email
     */
    public function test_user_can_login_after_verification()
    {
        $user = User::factory()->create([
            'email' => 'client@test.com',
            'password' => Hash::make('password123'),
            'role' => 'client',
            'email_verified_at' => now()
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'client@test.com',
            'password' => 'password123'
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'token',
                     'user' => ['id', 'name', 'email', 'role']
                 ]);
    }

    /**
     * User can logout
     */
    public function test_user_can_logout()
    {
        $user = User::factory()->create([
            'email_verified_at' => now()
        ]);

        $token = $user->createToken('test_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
                         ->postJson('/api/logout');

        $response->assertStatus(200)
                 ->assertJson(['message' => 'Logged out']);
    }

    /**
     * User can request password reset OTP
     */
    public function test_user_can_request_password_reset_otp()
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'user@test.com'
        ]);

        $response = $this->postJson('/api/forgot-password', [
            'email' => 'user@test.com'
        ]);

        $response->assertStatus(200)
                 ->assertJson(['message' => 'OTP sent to email']);

        $this->assertDatabaseHas('password_reset_otps', [
            'email' => 'user@test.com'
        ]);

        Mail::assertSent(SendOtpMail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }

    /**
     * User can verify OTP
     */
    public function test_user_can_verify_otp()
    {
        $user = User::factory()->create(['email' => 'user@test.com']);

        PasswordResetOtp::create([
            'email' => 'user@test.com',
            'otp' => '123456',
            'expires_at' => now()->addMinutes(10)
        ]);

        $response = $this->postJson('/api/verify-otp', [
            'email' => 'user@test.com',
            'otp' => '123456'
        ]);

        $response->assertStatus(200)
                 ->assertJson(['message' => 'OTP verified']);
    }

    /**
     * User can reset password using OTP
     */
    public function test_user_can_reset_password_using_otp()
    {
        $user = User::factory()->create([
            'email' => 'user@test.com',
            'password' => Hash::make('oldpassword')
        ]);

        PasswordResetOtp::create([
            'email' => 'user@test.com',
            'otp' => '654321',
            'expires_at' => now()->addMinutes(10)
        ]);

        $response = $this->postJson('/api/reset-password', [
            'email' => 'user@test.com',
            'otp' => '654321',
            'password' => 'newpassword123'
        ]);

        $response->assertStatus(200)
                 ->assertJson(['message' => 'Password reset successful']);

        $this->assertTrue(
            Hash::check('newpassword123', $user->fresh()->password)
        );

        $this->assertDatabaseMissing('password_reset_otps', [
            'email' => 'user@test.com'
        ]);
    }

    /**
     * Reset fails with wrong OTP
     */
    public function test_reset_fails_with_invalid_otp()
    {
        $user = User::factory()->create(['email' => 'user@test.com']);

        PasswordResetOtp::create([
            'email' => 'user@test.com',
            'otp' => '111111',
            'expires_at' => now()->addMinutes(10)
        ]);

        $response = $this->postJson('/api/reset-password', [
            'email' => 'user@test.com',
            'otp' => '999999',
            'password' => 'newpassword'
        ]);

        $response->assertStatus(400)
                 ->assertJson(['message' => 'Invalid or expired OTP']);
    }

    /**
     * User can verify email
     */
    public function test_user_can_verify_email()
    {
        $user = User::factory()->create([
            'email' => 'verify@test.com',
            'email_verified_at' => null
        ]);

        $hash = sha1($user->getEmailForVerification());

        $url = URL::signedRoute('verification.verify', ['id' => $user->id, 'hash' => $hash]);

        $response = $this->getJson($url);

        $response->assertStatus(200)
                 ->assertJson(['message' => 'Email verified successfully']);

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    /**
     * Verification fails with invalid hash
     */
    public function test_email_verification_fails_with_invalid_hash()
    {
        $user = User::factory()->create([
            'email' => 'verify@test.com',
            'email_verified_at' => null
        ]);

        $url = URL::signedRoute('verification.verify', ['id' => $user->id, 'hash' => 'wronghash']);

        $response = $this->getJson($url);

        $response->assertStatus(403)
                 ->assertJson(['message' => 'Invalid verification link']);
    }
}
