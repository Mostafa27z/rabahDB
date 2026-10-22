# Admin Rating Management System - Implementation Summary

## Overview
Implemented a comprehensive admin moderation system for user ratings with three visibility states: **public**, **private**, and **hidden**. Admins can now control which ratings are visible to the public and can permanently delete inappropriate ratings.

## Database Changes

### New Migration: `2026_02_09_101200_add_status_to_user_ratings_table.php`
Added `status` column to `user_ratings` table:
- **Type**: ENUM('public', 'private', 'hidden')
- **Default**: 'public'
- **Purpose**: Control visibility of ratings

### Status Definitions
1. **public** - ✅ Visible to everyone, included in average rating calculations
2. **private** - 🔒 Only visible to admins, not included in public averages
3. **hidden** - 👁️ Only visible to admins, semantically different from private for admin UI

## Code Changes

### 1. UserRating Model (`app/Models/UserRating.php`)
**Modified:**
- Added `status` to `$fillable` array

### 2. UserManagementController (`app/Http/Controllers/Api/UserManagementController.php`)

#### Modified Methods:

**`show($id)` - Updated to filter ratings by status**
- Non-admin users only see `public` ratings
- Admins see all ratings regardless of status
- Status field included in response only for admins
- Average rating calculated from `public` ratings only
- Total ratings count based on `public` ratings only

#### New Methods:

**`updateRatingStatus(Request $request, $ratingId)` - Admin only**
- Update rating visibility status
- Validates status: must be 'public', 'private', or 'hidden'
- Returns updated rating with new status

**`deleteRating($ratingId)` - Admin only**
- Permanently delete a rating from database
- Admin-only access

**`getAllRatings(Request $request)` - Admin only**
- Get paginated list of all ratings in system
- Filter by status (optional)
- Includes both user and rater information
- Ordered by creation date (newest first)
- Default pagination: 20 per page

### 3. RealEstateController (`app/Http/Controllers/Api/RealEstateController.php`)

**Modified `portfolio($ownerId)` method:**
- Only loads `public` ratings for portfolio display
- Average rating calculated from `public` ratings only
- Ensures consistency with public-facing data

### 4. API Routes (`routes/api.php`)

**Added three new admin-only routes:**
```php
Route::get('/ratings', [UserManagementController::class, 'getAllRatings']);
Route::patch('/ratings/{id}/status', [UserManagementController::class, 'updateRatingStatus']);
Route::delete('/ratings/{id}', [UserManagementController::class, 'deleteRating']);
```

## API Endpoints

### 1. Get All Ratings (Admin Only)
**Endpoint**: `GET /api/ratings`
**Authentication**: Required (Admin only)

**Query Parameters**:
- `page` (integer, default: 1)
- `per_page` (integer, default: 20)
- `status` (string, optional) - Filter by: public, private, or hidden

**Response**:
```json
{
    "data": [
        {
            "id": 1,
            "rating": 5,
            "comment": "Excellent service!",
            "status": "public",
            "created_at": "2026-02-09T09:51:23.000000Z",
            "user": {
                "id": 5,
                "name": "Jane Doe",
                "email": "jane@example.com",
                "role": "agent"
            },
            "rater": {
                "id": 2,
                "name": "John Smith",
                "email": "john@example.com",
                "role": "team_lead"
            }
        }
    ],
    "total": 45,
    "page": 1,
    "per_page": 20
}
```

### 2. Update Rating Status (Admin Only)
**Endpoint**: `PATCH /api/ratings/{id}/status`
**Authentication**: Required (Admin only)

**Request Body**:
```json
{
    "status": "hidden"
}
```

**Validation**:
- `status`: required, must be one of: public, private, hidden

**Response**:
```json
{
    "message": "Rating status updated successfully",
    "rating": {
        "id": 2,
        "status": "hidden",
        "rating": 3,
        "comment": "Inappropriate content...",
        "user_id": 7,
        "rater_id": 8
    }
}
```

### 3. Delete Rating (Admin Only)
**Endpoint**: `DELETE /api/ratings/{id}`
**Authentication**: Required (Admin only)

**Response**:
```json
{
    "message": "Rating deleted successfully"
}
```

## Rating Visibility Rules

### For Public Users (Non-Admins):
- ✅ Can see ratings with `status = 'public'`
- ❌ Cannot see ratings with `status = 'private'` or `'hidden'`
- 📊 Average rating calculated from `public` ratings only
- 🔢 Total ratings count includes only `public` ratings

### For Admins:
- ✅ Can see ALL ratings regardless of status
- 📝 Status field included in rating objects
- 🛠️ Can change rating status via PATCH endpoint
- 🗑️ Can permanently delete ratings via DELETE endpoint
- 📊 See true average including all ratings in admin view

## Admin Workflow

### Typical Moderation Workflow:
1. **User submits rating** → Status automatically set to `public`
2. **Admin reviews ratings** → Use `GET /api/ratings` to view all ratings
3. **Admin finds inappropriate content**:
   - **Option A**: Set to `hidden` to hide from public (preserves data)
     ```
     PATCH /api/ratings/{id}/status
     { "status": "hidden" }
     ```
   - **Option B**: Permanently delete
     ```
     DELETE /api/ratings/{id}
     ```
4. **Public users** → Only see ratings with `public` status
5. **Admins** → See all ratings with status indicators

### When to Use Each Status:

**Public** (default):
- Rating is appropriate and should be visible
- Contributes to user's public average rating

**Private**:
- Rating needs review but should be preserved
- Temporarily hidden while under investigation
- Can be made public later if deemed appropriate

**Hidden**:
- Rating contains inappropriate content
- Should not be visible to public
- Preserved for record-keeping/audit purposes

**Delete**:
- Rating is spam or severely inappropriate
- No need to keep for records
- ⚠️ **Permanent action** - cannot be undone

## Documentation Updates

### Updated Files:
1. **`f:\keen\api doc tamleek\users managment.html`**
   - Added three new admin endpoint sections
   - Updated GET /api/users/{id} to show status field for admins
   - Added Rating Status Workflow section
   - Updated Rating System information

## Migration Status
✅ Migration successfully run - `status` column added to `user_ratings` table

## Security Considerations

1. **Admin-Only Access**: All rating management endpoints check `$user->isAdmin()`
2. **403 Forbidden**: Non-admin users receive clear error message
3. **Data Preservation**: Hidden/private ratings preserved for audit trail
4. **Public Safety**: Only public ratings affect user reputation scores

## Testing Recommendations

### Test Cases:
1. ✅ Non-admin user cannot access admin endpoints (403)
2. ✅ Admin can view all ratings regardless of status
3. ✅ Non-admin user only sees public ratings
4. ✅ Average rating excludes private/hidden ratings
5. ✅ Status can be changed: public → hidden → public
6. ✅ Deleted ratings are permanently removed
7. ✅ Default status for new ratings is 'public'
8. ✅ Filter by status works correctly in getAllRatings

## Benefits

1. **Content Moderation**: Admins can hide inappropriate content
2. **Data Preservation**: Option to hide instead of delete
3. **Transparency**: Clear status indicators for admins
4. **User Protection**: Bad ratings can be hidden while investigated
5. **Audit Trail**: Hidden ratings preserved for review
6. **Flexibility**: Three-tier system (public/private/hidden) provides options

## Future Enhancements (Optional)

1. Add reason field for status changes (audit log)
2. Email notifications to users when their rating is hidden
3. Appeal system for hidden ratings
4. Bulk status update for multiple ratings
5. Rating moderation dashboard
6. Auto-hide ratings based on keywords/AI detection
