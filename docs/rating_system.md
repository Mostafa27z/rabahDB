# User Rating System - Updated Documentation

## Overview
The rating system allows any authenticated user to rate other users (agents, leads, admins). Ratings are **private by default** and can be managed by both admins and the rated user.

## Rating Status Types

### 1. **Private** (Default)
- Visible to: Admin and the rated user only
- Not included in public average rating
- Can be changed to public by admin or rated user

### 2. **Public**
- Visible to: Everyone
- Included in public average rating calculation
- Can be changed to private by admin or rated user

### 3. **Hidden**
- Visible to: Admin only
- Not visible to the rated user
- Not included in any calculations
- Can only be set by admin
- Used for inappropriate or flagged ratings

## Permissions

### Any Authenticated User (Including Clients)
- ✅ Can rate any other user (except themselves)
- ✅ Rating is created as **private** by default
- ❌ Cannot rate themselves

### Rated User
- ✅ Can view all their ratings (public and private, but not hidden)
- ✅ Can change rating status between **public** and **private**
- ❌ Cannot hide ratings (only admin can hide)
- ❌ Cannot delete ratings (only admin can delete)

### Admin
- ✅ Can view all ratings (public, private, and hidden)
- ✅ Can change rating status to **public**, **private**, or **hidden**
- ✅ Can delete any rating permanently

## API Endpoints

### 1. Rate a User
```
POST /api/users/{id}/rate
```
**Authentication:** Required (any role)

**Request Body:**
```json
{
  "rating": 5,           // Required: 1-5
  "comment": "Great service!" // Optional: max 1000 chars
}
```

**Response:**
```json
{
  "message": "Rating submitted successfully",
  "rating": {
    "id": 1,
    "rating": 5,
    "comment": "Great service!",
    "created_at": "2026-02-10T10:00:00.000000Z",
    "updated_at": "2026-02-10T10:00:00.000000Z"
  }
}
```

**Notes:**
- Creates a new rating or updates existing rating from the same user
- Status is automatically set to "private"
- Cannot rate yourself (403 error)

---

### 2. View User Profile with Ratings
```
GET /api/users/{id}
```
**Authentication:** Required (admin or team lead)

**Response:**
```json
{
  "id": 1,
  "name": "John Doe",
  "email": "john@example.com",
  "role": "agent",
  "ratings": [
    {
      "id": 1,
      "rating": 5,
      "comment": "Excellent agent!",
      "created_at": "2026-02-10T10:00:00.000000Z",
      "status": "private",  // Only visible to admin and rated user
      "rater": {
        "id": 2,
        "name": "Jane Smith",
        "email": "jane@example.com",
        "role": "client",
        "image": "http://example.com/storage/users/image.jpg"
      }
    }
  ],
  "average_rating": 4.5,    // Based on public ratings only
  "total_ratings": 10       // Count of public ratings only
}
```

**Visibility Rules:**
- **Admin:** Sees all ratings (public, private, hidden) with status field
- **Rated User (viewing own profile):** Sees public and private ratings with status field
- **Other Users:** See only public ratings without status field

---

### 3. Update Rating Status
```
PUT /api/ratings/{ratingId}/status
```
**Authentication:** Required (admin or rated user)

**Request Body (Admin):**
```json
{
  "status": "public"  // Options: public, private, hidden
}
```

**Request Body (Rated User):**
```json
{
  "status": "public"  // Options: public, private (cannot use hidden)
}
```

**Response:**
```json
{
  "message": "Rating status updated successfully",
  "rating": {
    "id": 1,
    "status": "public",
    "rating": 5,
    "comment": "Great service!",
    "user_id": 1,
    "rater_id": 2
  }
}
```

**Notes:**
- Rated users can only toggle between "public" and "private"
- Only admins can set status to "hidden"
- Returns 403 if user is neither admin nor the rated user

---

### 4. Delete Rating (Admin Only)
```
DELETE /api/ratings/{ratingId}
```
**Authentication:** Required (admin only)

**Response:**
```json
{
  "message": "Rating deleted successfully"
}
```

**Notes:**
- Permanently deletes the rating from the database
- Only admins can delete ratings
- Returns 403 for non-admin users

---

### 5. Get All Ratings (Admin Only)
```
GET /api/ratings?page=1&per_page=20&status=private
```
**Authentication:** Required (admin only)

**Query Parameters:**
- `page`: Page number (default: 1)
- `per_page`: Items per page (default: 20)
- `status`: Filter by status (optional: public, private, hidden)

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "rating": 5,
      "comment": "Excellent!",
      "status": "private",
      "created_at": "2026-02-10T10:00:00.000000Z",
      "user": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com",
        "role": "agent"
      },
      "rater": {
        "id": 2,
        "name": "Jane Smith",
        "email": "jane@example.com",
        "role": "client"
      }
    }
  ],
  "total": 50,
  "page": 1,
  "per_page": 20
}
```

---

## Use Cases

### Use Case 1: Client Rates an Agent
1. Client authenticates and gets token
2. Client submits rating: `POST /api/users/{agentId}/rate`
3. Rating is created with status "private"
4. Only admin and the agent can see this rating
5. Agent can choose to make it public or keep it private

### Use Case 2: Agent Makes Rating Public
1. Agent views their profile: `GET /api/users/{ownId}`
2. Agent sees all public and private ratings
3. Agent updates rating status: `PUT /api/ratings/{ratingId}/status` with `{"status": "public"}`
4. Rating is now visible to everyone and included in average rating

### Use Case 3: Admin Hides Inappropriate Rating
1. Admin reviews all ratings: `GET /api/ratings`
2. Admin finds inappropriate rating
3. Admin hides it: `PUT /api/ratings/{ratingId}/status` with `{"status": "hidden"}`
4. Rating is now only visible to admin
5. If needed, admin can delete it: `DELETE /api/ratings/{ratingId}`

### Use Case 4: User Updates Their Rating
1. User rates an agent: `POST /api/users/{agentId}/rate` with rating 3
2. Later, user rates the same agent again: `POST /api/users/{agentId}/rate` with rating 5
3. The existing rating is updated (not duplicated)
4. Status remains "private" unless previously changed

---

## Database Schema

### user_ratings table
```
- id (primary key)
- user_id (foreign key to users - the person being rated)
- rater_id (foreign key to users - the person giving the rating)
- rating (integer 1-5)
- comment (text, nullable)
- status (enum: 'public', 'private', 'hidden', default: 'private')
- created_at
- updated_at
```

---

## Summary of Changes

1. **Default Status:** All new ratings are created with status "private"
2. **Visibility:** Private ratings are visible to admin and the rated user
3. **Management:** Both admin and rated user can manage rating visibility
4. **Restrictions:** 
   - Rated users can only toggle between public/private
   - Only admins can hide or delete ratings
5. **Average Rating:** Only public ratings are included in calculations
