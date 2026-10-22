# User Rating System Implementation

## Overview
Implemented a comprehensive rating and comment system for users with the following features:
- Users can rate other users with 1-5 stars
- Users can leave optional comments with ratings
- Only non-client users can rate others
- Users cannot rate themselves
- Average rating and total ratings count are calculated and returned
- Ratings are included when fetching user data

## Database Changes

### New Table: `user_ratings`
- `id` - Primary key
- `user_id` - Foreign key to users table (user being rated)
- `rater_id` - Foreign key to users table (user giving the rating)
- `rating` - TinyInteger (1-5 stars)
- `comment` - Text (nullable)
- `timestamps` - Created at and updated at
- **Unique constraint** on `(user_id, rater_id)` to prevent duplicate ratings

## New Files Created

### 1. Migration
**File**: `database/migrations/2026_02_09_095100_create_user_ratings_table.php`
- Creates the `user_ratings` table with proper relationships and constraints

### 2. Model
**File**: `app/Models/UserRating.php`
- Defines relationships to User model
- Handles rating data

## Modified Files

### 1. User Model (`app/Models/User.php`)
**Added:**
- `ratingsReceived()` - Relationship to get all ratings received by the user
- `ratingsGiven()` - Relationship to get all ratings given by the user
- `averageRating()` - Helper method to calculate average rating

### 2. UserManagementController (`app/Http/Controllers/Api/UserManagementController.php`)
**Added:**
- Import for `UserRating` model
- `rateUser($request, $id)` - New endpoint to rate a user
  - Validates rating (1-5) and optional comment
  - Prevents clients from rating
  - Prevents self-rating
  - Uses `updateOrCreate` to allow updating existing ratings

**Modified:**
- `show($id)` - Now returns:
  - `ratings` - Array of all ratings with rater information
  - `average_rating` - Calculated average (rounded to 2 decimals)
  - `total_ratings` - Count of ratings received

### 3. RealEstateController (`app/Http/Controllers/Api/RealEstateController.php`)
**Modified:**
- `portfolio($ownerId)` - Now includes in owner data:
  - `ratings` - Array of all ratings with rater information
  - `average_rating` - Calculated average (rounded to 2 decimals)
  - `total_ratings` - Count of ratings received

### 4. API Routes (`routes/api.php`)
**Added:**
- `POST /api/users/{id}/rate` - Rate a user (requires authentication)

## API Endpoints

### Rate a User
**Endpoint**: `POST /api/users/{id}/rate`
**Authentication**: Required (Sanctum)
**Restrictions**: 
- Only non-client users can rate
- Cannot rate yourself

**Request Body**:
```json
{
  "rating": 5,
  "comment": "Excellent service and very professional!"
}
```

**Validation**:
- `rating`: Required, integer, min: 1, max: 5
- `comment`: Optional, string, max: 1000 characters

**Response** (201 Created):
```json
{
  "message": "Rating submitted successfully",
  "rating": {
    "id": 1,
    "rating": 5,
    "comment": "Excellent service and very professional!",
    "created_at": "2026-02-09T09:51:23.000000Z",
    "updated_at": "2026-02-09T09:51:23.000000Z"
  }
}
```

### Get User with Ratings
**Endpoint**: `GET /api/users/{id}`
**Authentication**: Required (Sanctum)

**Response**:
```json
{
  "id": 1,
  "name": "John Doe",
  "email": "john@example.com",
  "role": "agent",
  "phone": "+1234567890",
  "image": "http://example.com/storage/users/image.jpg",
  "ratings": [
    {
      "id": 1,
      "rating": 5,
      "comment": "Excellent service!",
      "created_at": "2026-02-09T09:51:23.000000Z",
      "rater": {
        "id": 2,
        "name": "Jane Smith",
        "email": "jane@example.com",
        "role": "team_lead",
        "image": "http://example.com/storage/users/jane.jpg"
      }
    }
  ],
  "average_rating": 4.75,
  "total_ratings": 12
}
```

### Get User Portfolio with Ratings
**Endpoint**: `GET /api/real-estates/portfolio/{userId}`
**Authentication**: Not required (public endpoint)

**Response**:
```json
{
  "data": [...],
  "page": 1,
  "limit": 10,
  "total": 25,
  "last_page": 3,
  "owner": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "role": "agent",
    "image": "http://example.com/storage/users/image.jpg",
    "phone": "+1234567890",
    "instagram_link": "https://instagram.com/johndoe",
    "tiktok_link": "https://tiktok.com/@johndoe",
    "whatsapp_link": "https://wa.me/1234567890",
    "ratings": [...],
    "average_rating": 4.75,
    "total_ratings": 12
  }
}
```

## Business Rules

1. **Who Can Rate**:
   - Admins can rate anyone
   - Team leads can rate anyone
   - Agents can rate anyone
   - Clients **CANNOT** rate anyone

2. **Rating Restrictions**:
   - Users cannot rate themselves
   - Each user can only rate another user once (updating is allowed)

3. **Rating Range**:
   - Minimum: 1 star
   - Maximum: 5 stars

4. **Comments**:
   - Optional
   - Maximum length: 1000 characters

## Migration Status
✅ Migration successfully run - `user_ratings` table created
